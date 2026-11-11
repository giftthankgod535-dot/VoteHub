<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure only logged-in administrators can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Get administrator information

$admin_name = $_SESSION['admin_name'];

// Count registered students

$users_count = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];

// Count elections

$elections_count = $conn->query("SELECT COUNT(*) AS total FROM elections")->fetch_assoc()['total'];

// Count candidates

$candidates_count = $conn->query("SELECT COUNT(*) AS total FROM candidates")->fetch_assoc()['total'];

// Count votes

$votes_count = $conn->query("SELECT COUNT(*) AS total FROM votes")->fetch_assoc()['total'];

// Retrieve all elections with dynamically calculated status

$sql = "SELECT *,

        CASE

            WHEN NOW() < start_date THEN 'upcoming'

            WHEN NOW() <= end_date THEN 'ongoing'

            ELSE 'completed'

        END AS current_status

        FROM elections

        ORDER BY created_at DESC";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - VoteHub</title>
<style>

body {

    font-family: Arial, sans-serif;

    background: #f2f4f8;

    margin: 0;

}

header {

    background: #174ea6;

    color: white;

    padding: 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

header h1 {

    margin: 0;

}

.header-links {

    display: flex;

    gap: 10px;

    align-items: center;

}

.header-links a {

    padding: 10px 15px;

    text-decoration: none;

    border-radius: 5px;

}

.home {

    background: white;

    color: #174ea6;

}

.logout {

    background: #dc3545;

    color: white;

}

.header-links a:hover {

    opacity: 0.85;

}

.container {

    width: 85%;

    margin: 30px auto;

}

.welcome {

    background: white;

    padding: 25px;

    border-radius: 8px;

    margin-bottom: 25px;

}

.stats {

    display: grid;

    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));

    gap: 20px;

    margin-bottom: 30px;

}

.stat-card {

    background: white;

    padding: 22px;

    border-radius: 8px;

    text-align: center;

    box-shadow: 0 2px 6px rgba(0,0,0,0.08);

}

.stat-card h3 {

    color: #555;

    margin-top: 0;

}

.stat-card p {

    font-size: 30px;

    font-weight: bold;

    color: #174ea6;

    margin-bottom: 0;

}

.actions {

    margin-bottom: 30px;

    display: flex;

    gap: 12px;

    flex-wrap: wrap;

}

.button {

    display: inline-block;

    background: #174ea6;

    color: white;

    padding: 12px 18px;

    text-decoration: none;

    border-radius: 5px;

}

.button:hover {

    background: #123d82;

}

.manage {

    background: #198754;

}

.manage:hover {

    background: #146c43;

}

.candidates {

    background: #6f42c1;

}

.candidates:hover {

    background: #59339d;

}

/* Review Candidate Applications Button */

.review {

    background: #fd7e14;

}

.review:hover {

    background: #dc6505;

}

.election {

    background: white;

    padding: 20px;

    margin-bottom: 15px;

    border-radius: 8px;

}

.status {

    font-weight: bold;

    text-transform: capitalize;

}

.empty {

    background: white;

    padding: 20px;

    border-radius: 8px;

}

@media (max-width: 600px) {

    header {

        flex-wrap: wrap;

        gap: 15px;

    }

    header h1 {

        font-size: 20px;

    }

    .container {

        width: 90%;

    }

}
</style>
</head>
<body>
<header>
<h1>VoteHub Administration</h1>
<div class="header-links">
<a href="../index.php" class="home">

            Home
</a>
<a href="logout.php" class="logout">

            Logout
</a>
</div>
</header>
<div class="container">
<div class="welcome">
<h2>

            Welcome, <?php echo htmlspecialchars($admin_name); ?>!
</h2>
<p>

            Manage elections, candidates and voting activities here.
</p>
</div>
<!-- Dashboard Statistics -->
<h2>Dashboard Overview</h2>
<div class="stats">
<div class="stat-card">
<h3>Registered Students</h3>
<p><?php echo $users_count; ?></p>
</div>
<div class="stat-card">
<h3>Total Elections</h3>
<p><?php echo $elections_count; ?></p>
</div>
<div class="stat-card">
<h3>Total Candidates</h3>
<p><?php echo $candidates_count; ?></p>
</div>
<div class="stat-card">
<h3>Total Votes</h3>
<p><?php echo $votes_count; ?></p>
</div>
</div>
<!-- Dashboard Actions -->
<div class="actions">
<a href="create_election.php" class="button">

            + Create New Election
</a>
<a href="manage_elections.php" class="button manage">

            Manage Elections
</a>
<a href="manage_candidates.php" class="button candidates">

            Manage Candidates
</a>
<a href="review_applications.php" class="button review">

            Review Candidate Applications
</a>
</div>
<!-- All Elections -->
<h2>All Elections</h2>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($election = $result->fetch_assoc()): ?>
<div class="election">
<h3>
<?php echo htmlspecialchars($election['title']); ?>
</h3>
<p>
<?php echo htmlspecialchars($election['description'] ?? ''); ?>
</p>
<p>

                    Status:
<span class="status">
<?php echo htmlspecialchars($election['current_status']); ?>
</span>
</p>
<p>

                    Start Date:
<?php echo htmlspecialchars($election['start_date']); ?>
</p>
<p>

                    End Date:
<?php echo htmlspecialchars($election['end_date']); ?>
</p>
</div>
<?php endwhile; ?>
<?php else: ?>
<div class="empty">
<p>No elections have been created yet.</p>
</div>
<?php endif; ?>
</div>
</body>
</html>
 