<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidemenu">
    <ul>
        <li><a href="dashboard.php" <?= $current_page === 'dashboard.php' ? 'class="active"' : '' ?>>Dashboard</a></li>
        <?php if ($_SESSION['user_role'] === 'student'): ?>
            <li><a href="tutor_list.php" <?= $current_page === 'tutor_list.php' ? 'class="active"' : '' ?>>Find a Tutor</a></li>
            <li><a href="session_booking.php" <?= $current_page === 'session_booking.php' ? 'class="active"' : '' ?>>Book Session</a></li>
        <?php endif; ?>
        <?php if ($_SESSION['user_role'] === 'tutor'): ?>
            <li><a href="session_booking.php" <?= $current_page === 'session_booking.php' ? 'class="active"' : '' ?>>Manage Sessions</a></li>
        <?php endif; ?>
        <li><a href="track_progress.php" <?= $current_page === 'track_progress.php' ? 'class="active"' : '' ?>>Track Progress</a></li>
        <li><a href="feedback_page.php" <?= $current_page === 'feedback_page.php' ? 'class="active"' : '' ?>>Feedback</a></li>
        <li><a href="../actions/logout.php">Logout</a></li>
    </ul>
</div>

<style>
.sidemenu .active {
    background-color: #ffcc00;
    color: #333;
}
</style>
