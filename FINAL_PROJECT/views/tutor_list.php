<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../actions/login.php");
    exit;
}

// Include database connection
require_once '../db/connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch user details from session
$user_name = $_SESSION['user_name'];
$user_profile_picture = $_SESSION['user_profile_picture'];

// Initialize variables
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$recommended = isset($_GET['recommended']) && $_GET['recommended'] === '1';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 5;
$offset = ($page - 1) * $results_per_page;

// Fetch tutors based on search or recommendation
$sql = "SELECT user_id, name, email, profile_picture, bio
        FROM Users
        WHERE role = 'tutor'";  // Filter for tutors only

if ($search_query) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR bio LIKE ?)";
}

if ($recommended) {
    $sql .= " ORDER BY name ASC";  // Sort by name or other criteria (e.g., rating if available)
} else {
    $sql .= " ORDER BY name ASC";
}

$sql .= " LIMIT ? OFFSET ?";

// Prepare the query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Statement preparation failed: " . $conn->error);
}

// Bind parameters
if ($search_query) {
    $search_param = "%$search_query%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $results_per_page, $offset);
} else {
    $stmt->bind_param("ii", $results_per_page, $offset);
}

// Execute the query
if (!$stmt->execute()) {
    die("Query execution failed: " . $stmt->error);
}

$tutors_result = $stmt->get_result();

// Get total tutors count for pagination
$count_sql = "SELECT COUNT(*) as total
              FROM Users
              WHERE role = 'tutor'";

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt->execute()) {
    die("Count query execution failed: " . $count_stmt->error);
}

$count_result = $count_stmt->get_result();
$total_tutors = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_tutors / $results_per_page);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Tutor</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <div class="container">
        <!-- Side Menu -->
        <aside class="sidemenu">
            <div class="profile-section">
                <img src="<?php echo $user_profile_picture ? '../uploads/' . htmlspecialchars($user_profile_picture) : '../assets/images/default-profile.png'; ?>" alt="Profile Picture" class="profile-picture">
                <h2><?php echo htmlspecialchars($user_name); ?></h2>
            </div>
            <nav>
                <ul>
                    <li><a href="home.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="tutor_list.php">Find a Tutor</a></li>
                    <li><a href="session_booking.php">Book a Session</a></li>
                    <li><a href="feedback_page.php">Leave Feedback</a></li>
                    <li><a href="progress_page.php" class="active">Track Progress</a></li>
                    <li><a href="../actions/logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header>
                <h1>Find a Tutor</h1>
            </header>

            <!-- Search Bar -->
            <section class="search-section">
                <form method="GET" action="tutor_list.php">
                    <input type="text" name="search" placeholder="Search tutors by name or email" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit">Search</button>
                </form>
            </section>

            <!-- Tutors List -->
            <section class="tutors-section">
                <h2><?php echo $recommended ? 'Recommended Tutors' : 'Available Tutors'; ?></h2>

                <?php if ($tutors_result->num_rows > 0): ?>
                    <ul class="tutors-list">
                        <?php while ($tutor = $tutors_result->fetch_assoc()): ?>
                            <li class="tutor-item">
                                <img src="<?php echo $tutor['profile_picture'] ? '../uploads/' . htmlspecialchars($tutor['profile_picture']) : '../assets/images/default-profile.png'; ?>" alt="Tutor Picture" class="tutor-picture">
                                <div class="tutor-info">
                                    <h3><?php echo htmlspecialchars($tutor['name']); ?></h3>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($tutor['email']); ?></p>
                                    <p><strong>Bio:</strong> <?php echo htmlspecialchars($tutor['bio']); ?></p>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>

                    <!-- Pagination -->
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="tutor_list.php?page=<?php echo $i; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $recommended ? '&recommended=1' : ''; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <p>No tutors found.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
