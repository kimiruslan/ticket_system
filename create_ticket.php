<?php
session_start();
include('config.php');

// Check if technician is logged in
if (!isset($_SESSION['technician_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$device = null;

// Get device_id from URL or POST
$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : (isset($_POST['device_id']) ? intval($_POST['device_id']) : 0);

// Fetch device information
if ($device_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $device = $result->fetch_assoc();
    $stmt->close();
    
    if (!$device) {
        $error = 'Device not found.';
    }
}

// Handle ticket creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_ticket'])) {
    $device_id = intval($_POST['device_id']);
    $issue_description = trim($_POST['issue_description']);
    $priority = $_POST['priority'] ?? 'medium';
    $technician_id = $_SESSION['technician_id'];
    
    if (empty($issue_description)) {
        $error = 'Please provide an issue description.';
    } else {
        // Generate unique ticket number
        $ticket_number = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Create ticket
        $stmt = $conn->prepare("INSERT INTO tickets (ticket_number, device_id, issue_description, priority, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $ticket_number, $device_id, $issue_description, $priority, $technician_id);
        
        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            
            // Add ticket update
            $update_stmt = $conn->prepare("INSERT INTO ticket_updates (ticket_id, technician_id, update_type, update_message) VALUES (?, ?, 'created', 'Ticket created')");
            $update_stmt->bind_param("ii", $ticket_id, $technician_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $success = 'Ticket created successfully!';
            // Redirect to ticket detail after 2 seconds
            header("refresh:2;url=view_ticket.php?id=" . $ticket_id);
        } else {
            $error = 'Failed to create ticket. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket - Ticket System</title>
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
        <main class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Create New Ticket</h2>
                    
                    <?php if ($error): ?>
                        <div class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success); ?></p>
                            <p class="text-sm text-green-700 mt-1">Redirecting to ticket details...</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($device): ?>
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Device Information</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Serial Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['serial_number']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Device Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['device_type']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Customer</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['customer_name']); ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
                        
                        <div>
                            <label for="issue_description" class="block text-sm font-medium text-gray-700 mb-2">
                                Issue Description <span class="text-red-500">*</span>
                            </label>
                            <textarea id="issue_description" name="issue_description" rows="6" required
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                      placeholder="Describe the issue or problem with the device..."><?php echo isset($_POST['issue_description']) ? htmlspecialchars($_POST['issue_description']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                                Priority
                            </label>
                            <select id="priority" name="priority"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="check_device.php"
                               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" name="create_ticket"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Create Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


