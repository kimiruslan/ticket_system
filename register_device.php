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

// Handle device registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_device'])) {
    $serial_number = trim($_POST['serial_number']);
    $model = trim($_POST['model']);
    $location = trim($_POST['location']);
    $data_issued = trim($_POST['data_issued']);
    $os = trim($_POST['os']);
    
    // Validation
    if (empty($serial_number) || empty($model) || empty($location) || empty($data_issued) || empty($os)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if serial number already exists
        $stmt = $conn->prepare("SELECT id_device_tracking FROM device_tracking WHERE serial_number = ?");
        $stmt->bind_param("s", $serial_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Device with this serial number already exists.';
        } else {
            // Insert new device
            $stmt = $conn->prepare("INSERT INTO device_tracking (serial_number, model, location, data_issued, os) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $serial_number, $model, $location, $data_issued, $os);
            
            if ($stmt->execute()) {
                $device_id = $conn->insert_id;
                $success = 'Device registered successfully!';
                // Redirect to create ticket after 2 seconds
                header("refresh:2;url=create_ticket.php?device_id=" . $device_id);
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}

// Pre-fill serial number if available from session
$prefill_serial = $_SESSION['pending_serial'] ?? '';
unset($_SESSION['pending_serial']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Device - Ticket System</title>
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
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Register New Device</h2>
                    
                    <?php if ($error): ?>
                        <div class="mb-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mb-4 rounded-md bg-green-50 p-4">
                            <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success); ?></p>
                            <p class="text-sm text-green-700 mt-1">Redirecting to create ticket...</p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="serial_number" class="block text-sm font-medium text-gray-700">
                                    Serial Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="serial_number" name="serial_number" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       value="<?php echo htmlspecialchars($prefill_serial); ?>">
                            </div>

                            <div>
                                <label for="model" class="block text-sm font-medium text-gray-700">
                                    Model <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="model" name="model" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., Inspiron 15, MacBook Pro">
                            </div>

                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">
                                    Location <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="location" name="location" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., Office, Warehouse, Department">
                            </div>

                            <div>
                                <label for="data_issued" class="block text-sm font-medium text-gray-700">
                                    Date Issued <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="data_issued" name="data_issued" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="os" class="block text-sm font-medium text-gray-700">
                                    Operating System <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="os" name="os" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., Windows 11, macOS, Linux">
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="check_device.php"
                               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" name="register_device"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Register Device
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


