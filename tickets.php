<?php
session_start();
include('config.php');

// Check if technician is logged in
if (!isset($_SESSION['technician_id'])) {
    header('Location: index.php');
    exit();
}

$technician_id = $_SESSION['technician_id'];
$technician_name = $_SESSION['technician_name'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get technician's email
$tech_info = $conn->prepare("SELECT email FROM technicians WHERE id = ?");
$tech_info->bind_param("i", $technician_id);
$tech_info->execute();
$tech_result = $tech_info->get_result();
$tech_data = $tech_result->fetch_assoc();
$tech_info->close();

// Build query based on status filter
if ($status_filter == 'pending') {
    $tickets_query = "
        SELECT t.*, d.serial_number, d.model, d.location, d.os,
               ta.first_name, ta.last_name, ta.email as tech_email,
               'pending' as status,
               NULL as feedback_status, NULL as date_solved
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        LEFT JOIN technican_assignment ta ON t.id_technican_assignment = ta.id_technican_assignment
        LEFT JOIN post_service_feedback psf ON t.id_technican_assignment = psf.id_technican_assignment
        WHERE psf.id_post_service_feedback IS NULL
        ORDER BY t.date DESC
    ";
} elseif ($status_filter == 'completed') {
    $tickets_query = "
        SELECT t.*, d.serial_number, d.model, d.location, d.os,
               ta.first_name, ta.last_name, ta.email as tech_email,
               'completed' as status,
               psf.status as feedback_status, psf.date_solved
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        LEFT JOIN technican_assignment ta ON t.id_technican_assignment = ta.id_technican_assignment
        INNER JOIN post_service_feedback psf ON t.id_technican_assignment = psf.id_technican_assignment
        ORDER BY t.date DESC
    ";
} else {
    $tickets_query = "
        SELECT t.*, d.serial_number, d.model, d.location, d.os,
               ta.first_name, ta.last_name, ta.email as tech_email,
               CASE WHEN psf.id_post_service_feedback IS NOT NULL THEN 'completed' ELSE 'pending' END as status,
               psf.status as feedback_status, psf.date_solved
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        LEFT JOIN technican_assignment ta ON t.id_technican_assignment = ta.id_technican_assignment
        LEFT JOIN post_service_feedback psf ON t.id_technican_assignment = psf.id_technican_assignment
        ORDER BY t.date DESC
    ";
}

// Get tickets with status
$tickets_result = $conn->query($tickets_query);
$tickets = $tickets_result->fetch_all(MYSQLI_ASSOC);

// Get counts
$total_count = $conn->query("SELECT COUNT(*) as count FROM ticket_intake")->fetch_assoc()['count'];
$pending_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM ticket_intake t
    LEFT JOIN post_service_feedback psf ON t.id_technican_assignment = psf.id_technican_assignment
    WHERE psf.id_post_service_feedback IS NULL
")->fetch_assoc()['count'];
$completed_count = $conn->query("
    SELECT COUNT(*) as count 
    FROM ticket_intake t
    INNER JOIN post_service_feedback psf ON t.id_technican_assignment = psf.id_technican_assignment
")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($status_filter); ?> Tickets - Ticket System</title>
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
                        <span class="text-sm text-gray-700">Welcome, <span class="font-medium"><?php echo htmlspecialchars($technician_name); ?></span></span>
                        <a href="dashboard.php" class="text-sm text-indigo-600 hover:text-indigo-500">Dashboard</a>
                        <a href="logout.php" class="text-sm text-indigo-600 hover:text-indigo-500">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <?php 
                        echo $status_filter == 'all' ? 'All Tickets' : 
                            ($status_filter == 'pending' ? 'Pending Tickets' : 'Completed Tickets'); 
                        ?>
                    </h1>
                    <p class="mt-2 text-sm text-gray-600">
                        <?php 
                        if ($status_filter == 'all') {
                            echo "Total: {$total_count} tickets";
                        } elseif ($status_filter == 'pending') {
                            echo "{$pending_count} pending tickets";
                        } else {
                            echo "{$completed_count} completed tickets";
                        }
                        ?>
                    </p>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <a href="tickets.php?status=all"
                           class="<?php echo $status_filter == 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            All (<?php echo $total_count; ?>)
                        </a>
                        <a href="tickets.php?status=pending"
                           class="<?php echo $status_filter == 'pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Pending (<?php echo $pending_count; ?>)
                        </a>
                        <a href="tickets.php?status=completed"
                           class="<?php echo $status_filter == 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Completed (<?php echo $completed_count; ?>)
                        </a>
                    </nav>
                </div>

                <!-- Tickets Table -->
                <div class="bg-white shadow rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <?php if ($status_filter == 'completed'): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Solved</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="<?php echo $status_filter == 'completed' ? '8' : '7'; ?>" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No <?php echo $status_filter == 'all' ? '' : $status_filter; ?> tickets found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #<?php echo $ticket['id_ticket_intake']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($ticket['model']); ?> (<?php echo htmlspecialchars($ticket['serial_number']); ?>)
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($ticket['reported_by']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php $is_completed = isset($ticket['status']) && $ticket['status'] == 'completed'; ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $is_completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                    <?php 
                                                    if ($is_completed && $ticket['feedback_status']) {
                                                        echo htmlspecialchars($ticket['feedback_status']);
                                                    } else {
                                                        echo $is_completed ? 'Completed' : 'Pending';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($ticket['date'])); ?>
                                            </td>
                                            <?php if ($status_filter == 'completed'): ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $ticket['date_solved'] ? date('M d, Y', strtotime($ticket['date_solved'])) : 'N/A'; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="view_ticket.php?id=<?php echo $ticket['id_ticket_intake']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

