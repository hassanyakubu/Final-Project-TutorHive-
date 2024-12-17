<?php
if (!isset($_SESSION)) {
    session_start();
}

// Get profile picture path
$profile_picture = isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture']) 
    ? '/FINAL_PROJECT/uploads/profile_pictures/' . $_SESSION['profile_picture']
    : '/FINAL_PROJECT/assets/images/default-profile.png';
?>

<header>
    <h1><a href="dashboard.php">TutorHive</a></h1>
    <nav>
        <ul>
            <li class="user-info">
                <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile Picture" class="profile-picture">
                Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>
                (<?= ucfirst(htmlspecialchars($_SESSION['user_role'])) ?>)
            </li>
        </ul>
    </nav>
</header>

<style>
header {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

header h1 {
    margin: 0;
}

header h1 a {
    color: #ffcc00;
    text-decoration: none;
    font-size: 1.5em;
    font-weight: bold;
}

header nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

header nav ul li {
    color: #fff;
    font-size: 1.1em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-picture {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ffcc00;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>
