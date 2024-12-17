<?php
session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in to continue.';
    header('Location: ../views/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token'])) {
            throw new Exception('CSRF token missing.');
        }
        verify_csrf_token($_POST['csrf_token']);

        // Validate inputs
        if (!isset($_POST['session_id']) || !isset($_POST['schedule_date'])) {
            throw new Exception('Missing required parameters.');
        }

        $session_id = filter_var($_POST['session_id'], FILTER_SANITIZE_NUMBER_INT);
        $schedule_date = filter_var($_POST['schedule_date'], FILTER_SANITIZE_STRING);
        $status = isset($_POST['status']) ? filter_var($_POST['status'], FILTER_SANITIZE_STRING) : null;

        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $schedule_date);
        if (!$date_obj) {
            throw new Exception('Invalid date format.');
        }

        // Format date for MySQL
        $formatted_date = $date_obj->format('Y-m-d H:i:s');

        // Verify the session exists and user has permission
        $verify_sql = "SELECT * FROM Sessions WHERE session_id = ? AND (student_id = ? OR tutor_id = ?)";
        $stmt = $conn->prepare($verify_sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("iii", $session_id, $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Session not found or you do not have permission to edit it.');
        }

        // Update session
        if ($_SESSION['user_role'] === 'tutor' && isset($_POST['status'])) {
            // Tutors can update both date and status
            $update_sql = "UPDATE Sessions SET schedule_date = ?, status = ? WHERE session_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssi", $formatted_date, $status, $session_id);
        } else {
            // Students can only update date
            $update_sql = "UPDATE Sessions SET schedule_date = ? WHERE session_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $formatted_date, $session_id);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to update session: ' . $stmt->error);
        }

        $_SESSION['notification'] = "Session updated successfully.";
        header('Location: ../views/dashboard.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../views/edit_session.php?session_id=' . $session_id);
        exit();
    }
} else {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: ../views/dashboard.php');
    exit();
}
?>
