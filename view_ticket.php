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
        SELECT t.*, d.serial_number, d.model, d.location, d.os, d.data_issued,
               ta.first_name, ta.last_name, ta.email as tech_email, ta.contact as tech_contact
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        LEFT JOIN technican_assignment ta ON t.id_technican_assignment = ta.id_technican_assignment
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

// Check if feedback exists (to determine if ticket is completed)
$feedback_exists = false;
if ($ticket_id > 0) {
    $feedback_stmt = $conn->prepare("SELECT * FROM post_service_feedback WHERE id_technican_assignment = (SELECT id_technican_assignment FROM ticket_intake WHERE id_ticket_intake = ?)");
    $feedback_stmt->bind_param("i", $ticket_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    $feedback_exists = $feedback_result->num_rows > 0;
    $feedback_stmt->close();
}

// Fetch technicians for assignment
$technicians_stmt = $conn->query("SELECT id, name, email FROM technicians ORDER BY name");
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
                                    <h2 class="text-2xl font-bold text-gray-900">Ticket #<?php echo $ticket['id_ticket_intake']; ?></h2>
                                    <p class="text-sm text-gray-500 mt-1">Date: <?php echo date('M d, Y', strtotime($ticket['date'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <?php if ($feedback_exists): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                            In Progress
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reported By</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['reported_by']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Assigned Technician</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
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
                                    <dt class="text-sm font-medium text-gray-500">Model</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['model']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Location</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['location']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">OS</dt>
                                    <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($ticket['os']); ?></dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Issue Description -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Issue Description</h3>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['issues_description']); ?></p>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                            
                            <!-- Navigation Buttons -->
                            <div class="flex space-x-4">
                                <?php if (!$feedback_exists): ?>
                                    <a href="parts_management.php?ticket_id=<?php echo $ticket_id; ?>"
                                       class="flex-1 flex justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                        Check Parts Needed
                                    </a>
                                <?php else: ?>
                                    <a href="service_feedback.php?ticket_id=<?php echo $ticket_id; ?>"
                                       class="flex-1 flex justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                        View Service Feedback
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
