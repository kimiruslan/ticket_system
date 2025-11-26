<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$database = "ticketing_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
