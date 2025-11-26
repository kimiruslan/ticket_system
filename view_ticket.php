<?php
session_start();
include('config.php');

// Check if technician is logged in
if (!isset($_SESSION['technician_id'])) {
    header('Location: index.php');
    exit();
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';
$ticket = null;

// Fetch ticket with device and technician information
if ($ticket_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, d.serial_number, d.device_type, d.brand, d.model, d.customer_name, d.customer_phone, d.customer_email,
               tech.name as assigned_technician_name, creator.name as created_by_name
        FROM tickets t
        LEFT JOIN devices d ON t.device_id = d.id
        LEFT JOIN technicians tech ON t.assigned_technician_id = tech.id
        LEFT JOIN technicians creator ON t.created_by = creator.id
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $technician_id = $_SESSION['technician_id'];
    
    $stmt = $conn->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("si", $new_status, $ticket_id);
    
    if ($stmt->execute()) {
        // Add update record
        $update_msg = "Status changed to: " . ucfirst(str_replace('_', ' ', $new_status));
        $update_stmt = $conn->prepare("INSERT INTO ticket_updates (ticket_id, technician_id, update_type, update_message) VALUES (?, ?, 'status_change', ?)");
        $update_stmt->bind_param("iis", $ticket_id, $technician_id, $update_msg);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = 'Ticket status updated successfully!';
        // Refresh ticket data
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    } else {
        $error = 'Failed to update status.';
    }
    $stmt->close();
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_ticket'])) {
    $assigned_tech_id = intval($_POST['assigned_technician_id']);
    $technician_id = $_SESSION['technician_id'];
    
    $stmt = $conn->prepare("UPDATE tickets SET assigned_technician_id = ?, status = 'assigned', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("ii", $assigned_tech_id, $ticket_id);
    
    if ($stmt->execute()) {
        // Add update record
        $tech_stmt = $conn->prepare("SELECT name FROM technicians WHERE id = ?");
        $tech_stmt->bind_param("i", $assigned_tech_id);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();
        $tech = $tech_result->fetch_assoc();
        $tech_stmt->close();
        
        $update_msg = "Ticket assigned to: " . $tech['name'];
        $update_stmt = $conn->prepare("INSERT INTO ticket_updates (ticket_id, technician_id, update_type, update_message) VALUES (?, ?, 'assignment', ?)");
        $update_stmt->bind_param("iis", $ticket_id, $technician_id, $update_msg);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = 'Ticket assigned successfully!';
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    } else {
        $error = 'Failed to assign ticket.';
    }
    $stmt->close();
}

// Fetch technicians for assignment
$technicians_stmt = $conn->query("SELECT id, name FROM technicians ORDER BY name");
$technicians = $technicians_stmt->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ticket - Ticket System</title>
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
                        <!-- Ticket Header -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></h2>
                                    <p class="text-sm text-gray-500 mt-1">Created: <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'assigned' => 'bg-blue-100 text-blue-800',
                                        'in_progress' => 'bg-purple-100 text-purple-800',
                                        'parts_needed' => 'bg-orange-100 text-orange-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'closed' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $status_color = $status_colors[$ticket['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Priority</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $ticket['priority'] == 'urgent' ? 'bg-red-100 text-red-800' : 
                                                       ($ticket['priority'] == 'high' ? 'bg-orange-100 text-orange-800' : 
                                                       ($ticket['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Assigned Technician</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php echo $ticket['assigned_technician_name'] ? htmlspecialchars($ticket['assigned_technician_name']) : 'Not assigned'; ?>
                                    </dd>
                                </div>
                            </div>
                        </div>

                        <!-- Device Information -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Device Information</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['serial_number']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Device Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['device_type']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Brand/Model</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['brand'] . ' ' . $ticket['model']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Customer</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['customer_name']); ?></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Issue Description -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Issue Description</h3>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['issue_description']); ?></p>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                            
                            <?php if (!$ticket['assigned_technician_id']): ?>
                                <!-- Assign Ticket -->
                                <form method="POST" action="" class="mb-4">
                                    <div class="flex items-end space-x-4">
                                        <div class="flex-1">
                                            <label for="assigned_technician_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                Assign to Technician
                                            </label>
                                            <select id="assigned_technician_id" name="assigned_technician_id" required
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="">Select technician...</option>
                                                <?php foreach ($technicians as $tech): ?>
                                                    <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="assign_ticket"
                                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                            Assign
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- Update Status -->
                            <?php if ($ticket['assigned_technician_id']): ?>
                                <form method="POST" action="" class="mb-4">
                                    <div class="flex items-end space-x-4">
                                        <div class="flex-1">
                                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                                Update Status
                                            </label>
                                            <select id="status" name="status" required
                                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="assigned" <?php echo $ticket['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                                <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="parts_needed" <?php echo $ticket['status'] == 'parts_needed' ? 'selected' : ''; ?>>Parts Needed</option>
                                                <option value="completed" <?php echo $ticket['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="update_status"
                                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                            Update
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- Navigation Buttons -->
                            <div class="flex space-x-4 mt-6">
                                <?php if ($ticket['status'] == 'parts_needed'): ?>
                                    <a href="parts_management.php?ticket_id=<?php echo $ticket_id; ?>"
                                       class="flex-1 flex justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700">
                                        Record Parts Usage
                                    </a>
                                <?php elseif ($ticket['status'] == 'completed'): ?>
                                    <a href="service_feedback.php?ticket_id=<?php echo $ticket_id; ?>"
                                       class="flex-1 flex justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                        Post Service Feedback
                                    </a>
                                <?php elseif ($ticket['status'] == 'in_progress'): ?>
                                    <a href="parts_management.php?ticket_id=<?php echo $ticket_id; ?>"
                                       class="flex-1 flex justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                        Check Parts Needed
                                    </a>
                                <?php endif; ?>
                                <a href="dashboard.php"
                                   class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Back to Dashboard
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


