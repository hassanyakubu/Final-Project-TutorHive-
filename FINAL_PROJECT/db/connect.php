<?php
// Check if we're on the production server
$is_production = isset($_SERVER['SERVER_NAME']) && 
                $_SERVER['SERVER_NAME'] !== 'localhost';

if ($is_production) {
    // Production database credentials for college server
    $servername = "localhost";
    $username = "hassan.yakubu";
    $password = "yakubuhassan";
    $dbname = "webtech_fall2024_hassan_yakubu";
} else {
    // Local database credentials
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "tutorplatform_db";
}

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");

    // Remove this in production
    if (!$is_production) {
        error_log("Database connected successfully");
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Database connection failed. Please try again later. Error: " . $e->getMessage());
}
?>
