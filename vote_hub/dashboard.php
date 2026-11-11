<?php

session_start();

require_once 'includes/db.php';

// Ensure only logged-in students can access this page

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}

// Retrieve elections with dynamically calculated status

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
<title>Dashboard | VoteHub</title>
<style>

body {

    font-family: Arial, sans-serif;

    background: #f1f4f9;

    margin: 0;

}

header {

    background: #243b64;

    color: white;

    padding: 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

header h2 {

    margin: 0;

}

.header-links {

    display: flex;

    gap: 10px;

    align-items: center;

}

header a {

    color: white;

    text-decoration: none;

    padding: 10px 15px;

    border-radius: 5px;

}

.home-btn {

    background: #28a745;

}

.logout-btn {

    background: #dc3545;

}

header a:hover {

    opacity: 0.85;

}

.container {

    padding: 30px;

}

.welcome {

    margin-bottom: 30px;

}

.election-card {

    background: white;

    padding: 20px;

    margin-bottom: 20px;

    border-radius: 8px;

    box-shadow: 0 3px 10px rgba(0,0,0,0.08);

}

.election-card h3 {

    color: #243b64;

}

.status {

    font-weight: bold;

    text-transform: capitalize;

}

.vote-btn {

    display: inline-block;

    background: #243b64;

    color: white;

    padding: 10px 15px;

    text-decoration: none;

    border-radius: 5px;

    margin-top: 10px;

    margin-right: 8px;

}

.vote-btn:hover {

    background: #174ea6;

}

.empty {

    background: white;

    padding: 20px;

    border-radius: 8px;

    text-align: center;

}
</style>
</head>
<body>
<header>
<h2>VoteHub</h2>
<div class="header-links">
<a href="index.php" class="home-btn">Home</a>
<a href="logout.php" class="logout-btn">Logout</a>
</div>
</header>
<div class="container">
<div class="welcome">
<h2>

            Welcome,
<?php echo htmlspecialchars($_SESSION['full_name']); ?>!
</h2>
<p>Explore available elections and make your voice count.</p>
</div>
<h2>Available Elections</h2>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($election = $result->fetch_assoc()): ?>
<div class="election-card">
<h3>
<?php echo htmlspecialchars($election['title']); ?>
</h3>
<p>
<?php echo htmlspecialchars($election['description'] ?? ''); ?>
</p>
<p>
<strong>Start Date:</strong>
<?php echo htmlspecialchars($election['start_date']); ?>
</p>
<p>
<strong>End Date:</strong>
<?php echo htmlspecialchars($election['end_date']); ?>
</p>
<p class="status">

                    Status:
<?php echo htmlspecialchars($election['current_status']); ?>
</p>
<?php if ($election['current_status'] === 'ongoing'): ?>
<a class="vote-btn"

                       href="candidates.php?election_id=<?php echo (int)$election['election_id']; ?>">

                        Vote Now
</a>
<a class="vote-btn"

                       href="results.php?election_id=<?php echo (int)$election['election_id']; ?>">

                        View Results
</a>
<?php elseif ($election['current_status'] === 'completed'): ?>
<a class="vote-btn"

                       href="results.php?election_id=<?php echo (int)$election['election_id']; ?>">

                        View Results
</a>
<?php else: ?>
<p>Voting has not started yet.</p>
<?php endif; ?>
</div>
<?php endwhile; ?>
<?php else: ?>
<div class="empty">
<h3>No Elections Available</h3>
<p>There are currently no elections on the platform.</p>
</div>
<?php endif; ?>
</div>
</body>
</html>
 