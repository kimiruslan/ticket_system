<?php
// Include the database configuration
include('config.php');

// Test the connection
if ($conn->ping()) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed: " . $conn->error;
}

// Close the connection
$conn->close();
?>
