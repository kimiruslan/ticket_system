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
$feedback_exists = false;
$assignment_id = null;

// Fetch ticket information
if ($ticket_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, d.serial_number, d.model, d.location, d.os
        FROM ticket_intake t
        LEFT JOIN device_tracking d ON t.id_device_tracking = d.id_device_tracking
        WHERE t.id_ticket_intake = ?
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();
    
    if ($ticket) {
        $assignment_id = $ticket['id_technican_assignment'];
        
        // Check if feedback already exists
        $feedback_stmt = $conn->prepare("SELECT * FROM post_service_feedback WHERE id_technican_assignment = ?");
        $feedback_stmt->bind_param("i", $assignment_id);
        $feedback_stmt->execute();
        $feedback_result = $feedback_stmt->get_result();
        $existing_feedback = $feedback_result->fetch_assoc();
        $feedback_stmt->close();
        
        if ($existing_feedback) {
            $feedback_exists = true;
        }
    } else {
        $error = 'Ticket not found.';
    }
} else {
    $error = 'Invalid ticket ID.';
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $comment = trim($_POST['comment']);
    $remark = trim($_POST['remark']);
    $status = trim($_POST['status']);
    $date_solved = date('Y-m-d');
    
    if (empty($comment) || empty($status)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($feedback_exists) {
            // Update existing feedback
            $stmt = $conn->prepare("UPDATE post_service_feedback SET comment = ?, remark = ?, status = ?, date_solved = ? WHERE id_technican_assignment = ?");
            $stmt->bind_param("ssssi", $comment, $remark, $status, $date_solved, $assignment_id);
        } else {
            // Insert new feedback
            $stmt = $conn->prepare("INSERT INTO post_service_feedback (id_technican_assignment, comment, remark, status, date_solved) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $assignment_id, $comment, $remark, $status, $date_solved);
        }
        
        if ($stmt->execute()) {
            $success = 'Service feedback submitted successfully! Ticket is now completed.';
            header("refresh:2;url=dashboard.php");
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
        $stmt->close();
    }
}

// Get parts used for this ticket
$parts_stmt = $conn->prepare("
    SELECT * FROM part_usage
    WHERE id_ticket_intake = ?
    ORDER BY date DESC
");
if ($ticket_id > 0) {
    $parts_stmt->bind_param("i", $ticket_id);
    $parts_stmt->execute();
    $parts_result = $parts_stmt->get_result();
    $used_parts = $parts_result->fetch_all(MYSQLI_ASSOC);
    $parts_stmt->close();
} else {
    $used_parts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Feedback - Ticket System</title>
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
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">Service Feedback</h2>
                            <p class="text-sm text-gray-600">Ticket #<?php echo $ticket['id_ticket_intake']; ?> - <?php echo htmlspecialchars($ticket['model']); ?></p>
                        </div>

                        <?php if ($error): ?>
                            <div class="rounded-md bg-red-50 p-4">
                                <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="rounded-md bg-green-50 p-4">
                                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success); ?></p>
                                <p class="text-sm text-green-700 mt-1">Redirecting to dashboard...</p>
                            </div>
                        <?php endif; ?>

                        <!-- Parts Used Summary -->
                        <?php if (!empty($used_parts)): ?>
                            <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Parts Used</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Part Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php 
                                            $total_cost = 0;
                                            foreach ($used_parts as $part): 
                                                $part_total = $part['quantity'] * $part['cost_price'];
                                                $total_cost += $part_total;
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($part['part_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $part['quantity']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($part_total, 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="bg-gray-50">
                                                <td colspan="2" class="px-6 py-4 text-right text-sm font-medium text-gray-900">Total:</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">$<?php echo number_format($total_cost, 2); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Feedback Form -->
                        <div class="bg-white shadow rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Service Completion Details</h3>
                            <form method="POST" action="" class="space-y-6">
                                <div>
                                    <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">
                                        Comment <span class="text-red-500">*</span>
                                    </label>
                                    <textarea id="comment" name="comment" rows="5" required
                                              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                              placeholder="Describe the work performed, issues resolved, and any additional notes..."><?php echo isset($existing_feedback['comment']) ? htmlspecialchars($existing_feedback['comment']) : ''; ?></textarea>
                                </div>

                                <div>
                                    <label for="remark" class="block text-sm font-medium text-gray-700 mb-2">
                                        Remark
                                    </label>
                                    <textarea id="remark" name="remark" rows="3"
                                              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                              placeholder="Any additional remarks or observations..."><?php echo isset($existing_feedback['remark']) ? htmlspecialchars($existing_feedback['remark']) : ''; ?></textarea>
                                </div>

                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select id="status" name="status" required
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Select status...</option>
                                        <option value="Fixed" <?php echo (isset($existing_feedback['status']) && $existing_feedback['status'] == 'Fixed') ? 'selected' : ''; ?>>Fixed</option>
                                        <option value="Resolved" <?php echo (isset($existing_feedback['status']) && $existing_feedback['status'] == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="Completed" <?php echo (isset($existing_feedback['status']) && $existing_feedback['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Pending Review" <?php echo (isset($existing_feedback['status']) && $existing_feedback['status'] == 'Pending Review') ? 'selected' : ''; ?>>Pending Review</option>
                                    </select>
                                </div>

                                <div class="flex justify-end space-x-4">
                                    <a href="view_ticket.php?id=<?php echo $ticket_id; ?>"
                                       class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Cancel
                                    </a>
                                    <button type="submit" name="submit_feedback"
                                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Submit Feedback & Complete Ticket
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
