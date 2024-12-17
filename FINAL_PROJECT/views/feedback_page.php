<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db/connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$sessions = [];
$session_id = '';
$rating = '';
$comments = '';
$student_id = $_SESSION['user_id'];

// Fetch the tutor and session details to display available sessions for feedback
$sql_sessions = "SELECT S.session_id, U.name as tutor_name, C.course_name, S.schedule_date
                 FROM Sessions S
                 JOIN Users U ON S.tutor_id = U.user_id
                 JOIN Courses C ON S.course_id = C.course_id
                 WHERE S.student_id = ? AND S.status = 'completed' AND S.session_id NOT IN (SELECT session_id FROM Reviews)
                 ORDER BY S.schedule_date DESC";
$stmt = $conn->prepare($sql_sessions);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_sessions = $stmt->get_result();

if ($result_sessions->num_rows > 0) {
    while ($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    if (empty($session_id) || empty($rating) || empty($comments)) {
        $error = "All fields are required.";
    } else {
        // Insert review into the database
        $insert_sql = "INSERT INTO Reviews (student_id, tutor_id, session_id, rating, comments) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiiss", $student_id, $_POST['tutor_id'], $session_id, $rating, $comments);

        if ($stmt->execute()) {
            $success = "Your feedback has been submitted successfully!";
            // Refresh the page to update the available sessions
            header('Location: feedback_page.php');
            exit();
        } else {
            $error = "Error submitting your feedback. Please try again.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Feedback - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidemenu.php'; ?>

    <main>
        <h1>Leave Feedback for Tutor</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <p>You don't have any completed sessions available for feedback.</p>
                <p>Once you complete a tutoring session, you can provide feedback here.</p>
                <a href="tutor_list.php" class="action-button change">Find a Tutor</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2>Submit Feedback</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="session_id">Select Session:</label>
                        <select name="session_id" id="session_id" required>
                            <option value="">Choose a session...</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= htmlspecialchars($session['session_id']) ?>">
                                    <?= htmlspecialchars(date('Y-m-d H:i', strtotime($session['schedule_date']))) ?> - 
                                    <?= htmlspecialchars($session['tutor_name']) ?> - 
                                    <?= htmlspecialchars($session['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rating">Rating:</label>
                        <select name="rating" id="rating" required>
                            <option value="">Select rating...</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Very Good</option>
                            <option value="3">3 - Good</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="comments">Comments:</label>
                        <textarea name="comments" id="comments" rows="5" required 
                                placeholder="Please share your experience with the tutor..."></textarea>
                    </div>

                    <button type="submit" class="action-button change">Submit Feedback</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
