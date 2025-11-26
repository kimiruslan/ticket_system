<?php
session_start();
include('config.php');

// Check if technician is logged in
if (!isset($_SESSION['technician_id'])) {
    header('Location: index.php');
    exit();
}

$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
$error = '';
$success = '';
$ticket = null;
$parts_needed = false;

// Fetch ticket information
if ($ticket_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, d.serial_number, d.device_type, d.customer_name
        FROM tickets t
        LEFT JOIN devices d ON t.device_id = d.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ticket) {
        $error = 'Ticket not found.';
    }
} else {
    $error = 'Invalid ticket ID.';
}

// Fetch available parts
$parts_stmt = $conn->query("SELECT * FROM parts ORDER BY name");
$available_parts = $parts_stmt->fetch_all(MYSQLI_ASSOC);

// Fetch parts already used in this ticket
$used_parts_stmt = $conn->prepare("
    SELECT tp.*, p.name as part_name, p.part_number
    FROM ticket_parts tp
    LEFT JOIN parts p ON tp.part_id = p.id
    WHERE tp.ticket_id = ?
");
if ($ticket_id > 0) {
    $used_parts_stmt->bind_param("i", $ticket_id);
    $used_parts_stmt->execute();
    $used_parts_result = $used_parts_stmt->get_result();
    $used_parts = $used_parts_result->fetch_all(MYSQLI_ASSOC);
    $used_parts_stmt->close();
} else {
    $used_parts = [];
}

// Handle "No parts needed" action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['no_parts_needed'])) {
    // Update ticket status to completed
    $stmt = $conn->prepare("UPDATE tickets SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    
    if ($stmt->execute()) {
        // Add update record
        $technician_id = $_SESSION['technician_id'];
        $update_msg = "No parts needed. Moving to completion.";
        $update_stmt = $conn->prepare("INSERT INTO ticket_updates (ticket_id, technician_id, update_type, update_message) VALUES (?, ?, 'status_change', ?)");
        $update_stmt->bind_param("iis", $ticket_id, $technician_id, $update_msg);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = 'Ticket marked as completed. Redirecting to feedback...';
        header("refresh:2;url=service_feedback.php?ticket_id=" . $ticket_id);
    } else {
        $error = 'Failed to update ticket status.';
    }
    $stmt->close();
}

// Handle parts usage recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_parts'])) {
    $part_id = intval($_POST['part_id']);
    $quantity = intval($_POST['quantity']);
    
    if ($part_id > 0 && $quantity > 0) {
        // Get part information
        $part_stmt = $conn->prepare("SELECT * FROM parts WHERE id = ?");
        $part_stmt->bind_param("i", $part_id);
        $part_stmt->execute();
        $part_result = $part_stmt->get_result();
        $part = $part_result->fetch_assoc();
        $part_stmt->close();
        
        if ($part && $part['quantity_in_stock'] >= $quantity) {
            // Calculate costs
            $unit_price = $part['unit_price'];
            $total_cost = $unit_price * $quantity;
            
            // Record part usage
            $insert_stmt = $conn->prepare("INSERT INTO ticket_parts (ticket_id, part_id, quantity_used, unit_price, total_cost) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iiidd", $ticket_id, $part_id, $quantity, $unit_price, $total_cost);
            
            if ($insert_stmt->execute()) {
                // Update stock
                $update_stock_stmt = $conn->prepare("UPDATE parts SET quantity_in_stock = quantity_in_stock - ? WHERE id = ?");
                $update_stock_stmt->bind_param("ii", $quantity, $part_id);
                $update_stock_stmt->execute();
                $update_stock_stmt->close();
                
                // Add update record
                $technician_id = $_SESSION['technician_id'];
                $update_msg = "Recorded part usage: " . $part['name'] . " (Qty: " . $quantity . ")";
                $update_stmt = $conn->prepare("INSERT INTO ticket_updates (ticket_id, technician_id, update_type, update_message) VALUES (?, ?, 'parts_usage', ?)");
                $update_stmt->bind_param("iis", $ticket_id, $technician_id, $update_msg);
                $update_stmt->execute();
                $update_stmt->close();
                
                $success = 'Part usage recorded successfully!';
                header("Location: parts_management.php?ticket_id=" . $ticket_id);
                exit();
            } else {
                $error = 'Failed to record part usage.';
            }
            $insert_stmt->close();
        } else {
            $error = 'Insufficient stock or invalid part.';
        }
    } else {
        $error = 'Please select a part and enter quantity.';
    }
}

// Handle "Finish recording parts"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finish_parts'])) {
    // Update ticket status to completed
    $stmt = $conn->prepare("UPDATE tickets SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    
    if ($stmt->execute()) {
        $success = 'Parts recorded. Redirecting to feedback...';
        header("refresh:2;url=service_feedback.php?ticket_id=" . $ticket_id);
    } else {
        $error = 'Failed to update ticket status.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parts Management - Ticket System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-4">
                        <a href="dashboard.php" class="text-xl font-semibold text-gray-900">Ticket System</a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-700">Welcome, <span class="font-medium"><?php echo htmlspecialchars($_SESSION['technician_name']); ?></span></span>
                        <a href="dashboard.php" class="text-sm text-indigo-600 hover:text-indigo-500">Dashboard</a>
                        <a href="logout.php" class="text-sm text-indigo-600 hover:text-indigo-500">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <?php if ($error && !$ticket): ?>
                    <div class="bg-white shadow rounded-lg p-6">
                        <div class="rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <div class="mt-4">
                            <a href="dashboard.php" class="text-indigo-600 hover:text-indigo-500">Back to Dashboard</a>
                        </div>
                    </div>
                <?php elseif ($ticket): ?>
                    <div class="space-y-6">
                        <!-- Ticket Info -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">Parts Management</h2>
                            <p class="text-sm text-gray-600">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - <?php echo htmlspecialchars($ticket['device_type']); ?> (<?php echo htmlspecialchars($ticket['serial_number']); ?>)</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="rounded-md bg-red-50 p-4">
                                <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="rounded-md bg-green-50 p-4">
                                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Used Parts -->
                        <?php if (!empty($used_parts)): ?>
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Parts Used</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Number</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php 
                                            $total_cost = 0;
                                            foreach ($used_parts as $used_part): 
                                                $total_cost += $used_part['total_cost'];
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($used_part['part_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($used_part['part_number'] ?? 'N/A'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $used_part['quantity_used']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($used_part['unit_price'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">$<?php echo number_format($used_part['total_cost'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="bg-gray-50">
                                                <td colspan="4" class="px-6 py-4 text-right text-sm font-medium text-gray-900">Total:</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">$<?php echo number_format($total_cost, 2); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Record New Part -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Record Part Usage</h3>
                            <form method="POST" action="" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label for="part_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Select Part
                                        </label>
                                        <select id="part_id" name="part_id" required
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option value="">Select a part...</option>
                                            <?php foreach ($available_parts as $part): ?>
                                                <option value="<?php echo $part['id']; ?>" 
                                                        data-stock="<?php echo $part['quantity_in_stock']; ?>"
                                                        data-price="<?php echo $part['unit_price']; ?>">
                                                    <?php echo htmlspecialchars($part['name']); ?> 
                                                    (Stock: <?php echo $part['quantity_in_stock']; ?>, $<?php echo number_format($part['unit_price'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                            Quantity
                                        </label>
                                        <input type="number" id="quantity" name="quantity" min="1" required
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" name="record_parts"
                                                class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                            Record Part
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Next Steps</h3>
                            <div class="flex space-x-4">
                                <?php if (empty($used_parts)): ?>
                                    <form method="POST" action="" class="flex-1">
                                        <button type="submit" name="no_parts_needed"
                                                class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                            No Parts Needed - Continue to Feedback
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="" class="flex-1">
                                        <button type="submit" name="finish_parts"
                                                class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                            Finish Recording Parts - Continue to Feedback
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="view_ticket.php?id=<?php echo $ticket_id; ?>"
                                   class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Back to Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>


