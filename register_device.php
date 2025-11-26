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
    $device_type = trim($_POST['device_type']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    
    // Validation
    if (empty($serial_number) || empty($device_type) || empty($customer_name)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if serial number already exists
        $stmt = $conn->prepare("SELECT id FROM devices WHERE serial_number = ?");
        $stmt->bind_param("s", $serial_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Device with this serial number already exists.';
        } else {
            // Insert new device
            $stmt = $conn->prepare("INSERT INTO devices (serial_number, device_type, brand, model, customer_name, customer_email, customer_phone, customer_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $serial_number, $device_type, $brand, $model, $customer_name, $customer_email, $customer_phone, $customer_address);
            
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
                                <label for="device_type" class="block text-sm font-medium text-gray-700">
                                    Device Type <span class="text-red-500">*</span>
                                </label>
                                <select id="device_type" name="device_type" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select device type</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Smartphone">Smartphone</option>
                                    <option value="Printer">Printer</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label for="brand" class="block text-sm font-medium text-gray-700">Brand</label>
                                <input type="text" id="brand" name="brand"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., Dell, HP, Apple">
                            </div>

                            <div>
                                <label for="model" class="block text-sm font-medium text-gray-700">Model</label>
                                <input type="text" id="model" name="model"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                       placeholder="e.g., Inspiron 15, MacBook Pro">
                            </div>

                            <div class="sm:col-span-2 border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Customer Information</h3>
                            </div>

                            <div>
                                <label for="customer_name" class="block text-sm font-medium text-gray-700">
                                    Customer Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="customer_name" name="customer_name" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="customer_email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" id="customer_email" name="customer_email"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="customer_address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea id="customer_address" name="customer_address" rows="3"
                                          class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
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


