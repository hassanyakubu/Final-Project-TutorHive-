<?php
session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Set secure headers
set_secure_headers();

$error = '';
$results = [];
$search_query = '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$total_results = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    try {
        // Check rate limiting
        if (!rate_limit_check('search_' . $_SERVER['REMOTE_ADDR'], 20, 60)) {
            throw new Exception('Too many search attempts. Please try again later.');
        }

        $search_query = trim($_GET['query']);

        // Validate search query
        if (strlen($search_query) > 100) {
            throw new Exception('Search query too long. Maximum 100 characters allowed.');
        }

        if (!empty($search_query)) {
            // Calculate offset for pagination
            $offset = ($page - 1) * $per_page;

            // Get total count for pagination
            $count_sql = "SELECT COUNT(*) as total FROM Users 
                         WHERE role = 'tutor' 
                         AND status = 'active'
                         AND (name LIKE ? OR bio LIKE ?)";
            $stmt = $conn->prepare($count_sql);
            $like_query = "%" . $conn->real_escape_string($search_query) . "%";
            $stmt->bind_param("ss", $like_query, $like_query);
            $stmt->execute();
            $total_results = $stmt->get_result()->fetch_assoc()['total'];

            // Prepare SQL query to search for tutors with pagination
            $sql = "SELECT u.user_id, u.name, u.email, u.bio, u.profile_picture,
                          GROUP_CONCAT(c.course_name SEPARATOR ', ') as courses
                   FROM Users u
                   LEFT JOIN TutorCourses tc ON u.user_id = tc.tutor_id
                   LEFT JOIN Courses c ON tc.course_id = c.course_id
                   WHERE u.role = 'tutor' 
                   AND u.status = 'active'
                   AND (u.name LIKE ? OR u.bio LIKE ?)
                   GROUP BY u.user_id
                   ORDER BY u.name
                   LIMIT ? OFFSET ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $like_query, $like_query, $per_page, $offset);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // Validate and sanitize profile picture path
                    if (!empty($row['profile_picture'])) {
                        $profile_path = realpath($row['profile_picture']);
                        if ($profile_path === false || !file_exists($profile_path)) {
                            $row['profile_picture'] = '../assets/images/default_profile.png';
                        }
                    } else {
                        $row['profile_picture'] = '../assets/images/default_profile.png';
                    }
                    $results[] = $row;
                }
            } else {
                throw new Exception('Error executing search query. Please try again later.');
            }
        } else {
            throw new Exception('Search query cannot be empty.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Calculate pagination details
$total_pages = ceil($total_results / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Tutors - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <header>
        <h1>Search Tutors</h1>
    </header>

    <main class="content">
        <section class="search-section">
            <h2>Find the Best Tutors for Your Needs</h2>

            <form action="search_tutor.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="query">Search by Name or Bio:</label>
                    <input type="text" id="query" name="query" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           required maxlength="100"
                           placeholder="Enter tutor name or keywords">
                    <button type="submit">Search</button>
                </div>
            </form>
        </section>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <section class="results-section">
                <h3>Search Results (<?php echo $total_results; ?> tutors found)</h3>
                <div class="tutor-grid">
                    <?php foreach ($results as $tutor): ?>
                        <div class="tutor-card">
                            <img src="<?php echo htmlspecialchars($tutor['profile_picture']); ?>" 
                                 alt="<?php echo htmlspecialchars($tutor['name']); ?>" 
                                 class="profile-image">
                            <div class="tutor-info">
                                <h4><?php echo htmlspecialchars($tutor['name']); ?></h4>
                                <p class="email"><strong>Email:</strong> <?php echo htmlspecialchars($tutor['email']); ?></p>
                                <p class="courses"><strong>Courses:</strong> <?php echo htmlspecialchars($tutor['courses'] ?: 'No courses assigned'); ?></p>
                                <p class="bio"><?php echo nl2br(htmlspecialchars($tutor['bio'])); ?></p>
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'student'): ?>
                                    <a href="book_session.php?tutor_id=<?php echo htmlspecialchars($tutor['user_id']); ?>" 
                                       class="book-button">Book Session</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?query=<?php echo urlencode($search_query); ?>&page=<?php echo ($page - 1); ?>">&laquo; Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?query=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"
                               class="<?php echo ($i === $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?query=<?php echo urlencode($search_query); ?>&page=<?php echo ($page + 1); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($search_query) && empty($error)): ?>
            <p class="no-results">No tutors found matching "<?php echo htmlspecialchars($search_query); ?>". Please try with different search terms.</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> TutorHive. All rights reserved.</p>
    </footer>
</body>
</html>
