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

// Fetch ticket information
if ($ticket_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, d.serial_number, d.model, d.location
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        WHERE t.id_ticket_intake = ?
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

// Fetch parts already used in this ticket
$used_parts_stmt = $conn->prepare("
    SELECT * FROM part_usage
    WHERE id_ticket_intake = ?
    ORDER BY date DESC
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
    $success = 'No parts needed. Redirecting to feedback...';
    header("refresh:2;url=service_feedback.php?ticket_id=" . $ticket_id);
}

// Handle parts usage recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_parts'])) {
    $part_name = trim($_POST['part_name']);
    $quantity = intval($_POST['quantity']);
    $cost_price = floatval($_POST['cost_price']);
    
    if (!empty($part_name) && $quantity > 0 && $cost_price >= 0) {
        // Record part usage
        $date = date('Y-m-d');
        $insert_stmt = $conn->prepare("INSERT INTO part_usage (id_ticket_intake, part_name, quantity, cost_price, date) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isids", $ticket_id, $part_name, $quantity, $cost_price, $date);
        
        if ($insert_stmt->execute()) {
            $success = 'Part usage recorded successfully!';
            header("Location: parts_management.php?ticket_id=" . $ticket_id);
            exit();
        } else {
            $error = 'Failed to record part usage.';
        }
        $insert_stmt->close();
    } else {
        $error = 'Please fill in all fields correctly.';
    }
}

// Handle "Finish recording parts"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finish_parts'])) {
    $success = 'Parts recorded. Redirecting to feedback...';
    header("refresh:2;url=service_feedback.php?ticket_id=" . $ticket_id);
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
                            <p class="text-sm text-gray-600">Ticket #<?php echo $ticket['id_ticket_intake']; ?> - <?php echo htmlspecialchars($ticket['model']); ?> (<?php echo htmlspecialchars($ticket['serial_number']); ?>)</p>
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
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php 
                                            $total_cost = 0;
                                            foreach ($used_parts as $used_part): 
                                                $part_total = $used_part['quantity'] * $used_part['cost_price'];
                                                $total_cost += $part_total;
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($used_part['part_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $used_part['quantity']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($used_part['cost_price'], 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($part_total, 2); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($used_part['date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="bg-gray-50">
                                                <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900">Total:</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">$<?php echo number_format($total_cost, 2); ?></td>
                                                <td></td>
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
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label for="part_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Part Name
                                        </label>
                                        <input type="text" id="part_name" name="part_name" required
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                               placeholder="e.g., RAM 8GB, Hard Drive 1TB">
                                    </div>
                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                            Quantity
                                        </label>
                                        <input type="number" id="quantity" name="quantity" min="1" required
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="cost_price" class="block text-sm font-medium text-gray-700 mb-2">
                                            Cost Price (per unit)
                                        </label>
                                        <input type="number" id="cost_price" name="cost_price" min="0" step="0.01" required
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                               placeholder="0.00">
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
