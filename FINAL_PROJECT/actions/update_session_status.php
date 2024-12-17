<?php
session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure user is logged in and is a tutor
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'tutor') {
    $_SESSION['error'] = 'Access denied. Only tutors can perform this action.';
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
        if (!isset($_POST['session_id']) || !isset($_POST['status'])) {
            throw new Exception('Missing required parameters.');
        }

        $session_id = filter_var($_POST['session_id'], FILTER_SANITIZE_NUMBER_INT);
        $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);

        // Validate status
        if (!in_array($status, ['accepted', 'declined'])) {
            throw new Exception('Invalid status value.');
        }

        // Verify the session exists and belongs to this tutor
        $verify_sql = "SELECT session_id FROM Sessions WHERE session_id = ? AND tutor_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($verify_sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("ii", $session_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Invalid session or session already processed.');
        }

        // Update the session status
        $update_sql = "UPDATE Sessions SET status = ? WHERE session_id = ? AND tutor_id = ?";
        $stmt = $conn->prepare($update_sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("sii", $status, $session_id, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update session status.');
        }

        // Set success message
        $_SESSION['success'] = 'Session ' . $status . ' successfully.';
        
        // Redirect back to dashboard
        header('Location: ../views/dashboard.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../views/dashboard.php');
        exit();
    }
} else {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: ../views/dashboard.php');
    exit();
}
?>
