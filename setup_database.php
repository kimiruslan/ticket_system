<?php
include('config.php');

// Create all required tables matching existing schema
$tables = [
    // Technicians table (already exists, but ensure it's correct)
    "CREATE TABLE IF NOT EXISTS technicians (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Device Tracking table
    "CREATE TABLE IF NOT EXISTS device_tracking (
        id_device_tracking INT(25) AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(255) NOT NULL,
        model VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        data_issued DATE NOT NULL,
        os VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Technician Assignment table
    "CREATE TABLE IF NOT EXISTS technican_assignment (
        id_technican_assignment INT(25) AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        last_name VARCHAR(255) NOT NULL,
        contact VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Ticket Intake table
    "CREATE TABLE IF NOT EXISTS ticket_intake (
        id_ticket_intake INT(25) AUTO_INCREMENT PRIMARY KEY,
        id_device_tracking INT(25) NOT NULL,
        id_technican_assignment INT(25) NOT NULL,
        reported_by VARCHAR(255) NOT NULL,
        issues_description VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        FOREIGN KEY (id_device_tracking) REFERENCES device_tracking(id_device_tracking) ON DELETE CASCADE,
        FOREIGN KEY (id_technican_assignment) REFERENCES technican_assignment(id_technican_assignment) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Part Usage table
    "CREATE TABLE IF NOT EXISTS part_usage (
        id_part_usage INT(25) AUTO_INCREMENT PRIMARY KEY,
        id_ticket_intake INT(25) NOT NULL,
        part_name VARCHAR(255) NOT NULL,
        quantity INT(25) NOT NULL,
        cost_price INT(11) NOT NULL,
        date DATE NOT NULL,
        FOREIGN KEY (id_ticket_intake) REFERENCES ticket_intake(id_ticket_intake) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Post Service Feedback table
    "CREATE TABLE IF NOT EXISTS post_service_feedback (
        id_post_service_feedback INT(11) AUTO_INCREMENT PRIMARY KEY,
        id_technican_assignment INT(11) NOT NULL,
        comment VARCHAR(255) NOT NULL,
        remark VARCHAR(255) NOT NULL,
        status TEXT NOT NULL,
        date_solved DATE NOT NULL,
        FOREIGN KEY (id_technican_assignment) REFERENCES technican_assignment(id_technican_assignment) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "Setting up database tables...\n";

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
        echo "✓ Table created/verified successfully\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
    }
}

echo "\nDatabase setup completed!\n";
$conn->close();
?>
