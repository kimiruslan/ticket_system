<?php
include('config.php');

// Create all required tables
$tables = [
    // Technicians table
    "CREATE TABLE IF NOT EXISTS technicians (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Devices table
    "CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(100) NOT NULL UNIQUE,
        device_type VARCHAR(50) NOT NULL,
        brand VARCHAR(50),
        model VARCHAR(100),
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100),
        customer_phone VARCHAR(20),
        customer_address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Tickets table
    "CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_number VARCHAR(20) NOT NULL UNIQUE,
        device_id INT NOT NULL,
        issue_description TEXT NOT NULL,
        status ENUM('pending', 'assigned', 'in_progress', 'parts_needed', 'completed', 'closed') DEFAULT 'pending',
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        assigned_technician_id INT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES technicians(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Parts table
    "CREATE TABLE IF NOT EXISTS parts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        part_number VARCHAR(50),
        description TEXT,
        quantity_in_stock INT DEFAULT 0,
        unit_price DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Ticket Parts (parts used in tickets)
    "CREATE TABLE IF NOT EXISTS ticket_parts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        part_id INT NOT NULL,
        quantity_used INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10, 2) NOT NULL,
        total_cost DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Service Feedback table
    "CREATE TABLE IF NOT EXISTS service_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL UNIQUE,
        technician_notes TEXT,
        service_completed_at TIMESTAMP,
        customer_satisfaction INT CHECK (customer_satisfaction >= 1 AND customer_satisfaction <= 5),
        customer_feedback TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Ticket History/Updates
    "CREATE TABLE IF NOT EXISTS ticket_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        technician_id INT,
        update_type VARCHAR(50) NOT NULL,
        update_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
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


