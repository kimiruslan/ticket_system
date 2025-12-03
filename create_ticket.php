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
    $stmt = $conn->prepare("SELECT * FROM device_tracking WHERE id_device_tracking = ?");
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
    $issue_description = trim($_POST['issues_description']);
    $reported_by = trim($_POST['reported_by']);
    $technician_id = $_SESSION['technician_id'];
    
    if (empty($issue_description) || empty($reported_by)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Get or create technican_assignment for the logged-in technician
        $tech_stmt = $conn->prepare("SELECT * FROM technicians WHERE id = ?");
        $tech_stmt->bind_param("i", $technician_id);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();
        $technician = $tech_result->fetch_assoc();
        $tech_stmt->close();
        
        if ($technician) {
            // Check if technican_assignment exists for this technician
            $assign_stmt = $conn->prepare("SELECT id_technican_assignment FROM technican_assignment WHERE email = ?");
            $assign_stmt->bind_param("s", $technician['email']);
            $assign_stmt->execute();
            $assign_result = $assign_stmt->get_result();
            $assignment = $assign_result->fetch_assoc();
            $assign_stmt->close();
            
            if (!$assignment) {
                // Create technican_assignment
                $name_parts = explode(' ', $technician['name'], 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                $contact = $technician['phone'] ?? '';
                
                $create_assign_stmt = $conn->prepare("INSERT INTO technican_assignment (first_name, last_name, contact, email) VALUES (?, ?, ?, ?)");
                $create_assign_stmt->bind_param("ssss", $first_name, $last_name, $contact, $technician['email']);
                $create_assign_stmt->execute();
                $assignment_id = $conn->insert_id;
                $create_assign_stmt->close();
            } else {
                $assignment_id = $assignment['id_technican_assignment'];
            }
            
            // Create ticket
            $date = date('Y-m-d');
            $stmt = $conn->prepare("INSERT INTO ticket_intake (id_device_tracking, id_technican_assignment, reported_by, issues_description, date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $device_id, $assignment_id, $reported_by, $issue_description, $date);
            
            if ($stmt->execute()) {
                $ticket_id = $conn->insert_id;
                $success = 'Ticket created successfully!';
                // Redirect to ticket detail after 2 seconds
                header("refresh:2;url=view_ticket.php?id=" . $ticket_id);
            } else {
                $error = 'Failed to create ticket. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Technician not found.';
        }
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
                                    <dt class="text-sm font-medium text-gray-500">Model</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['model']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Location</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['location']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">OS</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($device['os']); ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
                        
                        <div>
                            <label for="reported_by" class="block text-sm font-medium text-gray-700 mb-2">
                                Reported By <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="reported_by" name="reported_by" required
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                   placeholder="Name of person reporting the issue"
                                   value="<?php echo isset($_POST['reported_by']) ? htmlspecialchars($_POST['reported_by']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="issues_description" class="block text-sm font-medium text-gray-700 mb-2">
                                Issue Description <span class="text-red-500">*</span>
                            </label>
                            <textarea id="issues_description" name="issues_description" rows="6" required
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                      placeholder="Describe the issue or problem with the device..."><?php echo isset($_POST['issues_description']) ? htmlspecialchars($_POST['issues_description']) : ''; ?></textarea>
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


