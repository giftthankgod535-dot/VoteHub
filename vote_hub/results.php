<?php

session_start();

require_once 'includes/db.php';

// Ensure the student is logged in

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}

// Get election ID from URL

$election_id = filter_input(INPUT_GET, 'election_id', FILTER_VALIDATE_INT);

if (!$election_id) {

    die("Invalid election selected.");

}

// Retrieve election details

$stmt = $conn->prepare(

    "SELECT * FROM elections WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$election = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$election) {

    die("Election not found.");

}

// Retrieve candidates, positions and their vote counts

$stmt = $conn->prepare(

    "SELECT

        c.candidate_id,

        c.full_name,

        c.description,

        c.photo,

        c.position_id,

        p.position_name,

        COUNT(v.vote_id) AS vote_count

     FROM candidates c

     LEFT JOIN election_positions p

        ON c.position_id = p.position_id

     LEFT JOIN votes v

        ON c.candidate_id = v.candidate_id

        AND c.election_id = v.election_id

        AND v.position_id = c.position_id

     WHERE c.election_id = ?

     GROUP BY

        c.candidate_id,

        c.full_name,

        c.description,

        c.photo,

        c.position_id,

        p.position_name

     ORDER BY

        p.position_id ASC,

        vote_count DESC,

        c.full_name ASC"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$candidates_by_position = [];

while ($candidate = $result->fetch_assoc()) {

    $position = !empty($candidate['position_name'])

        ? $candidate['position_name']

        : "Other Candidates";

    $candidates_by_position[$position][] = $candidate;

}

$stmt->close();

// Calculate total votes for the entire election

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_votes

     FROM votes

     WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$total_result = $stmt->get_result()->fetch_assoc();

$total_votes = (int) $total_result['total_votes'];

$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Election Results - VoteHub</title>
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

}

.container {

    width: 90%;

    max-width: 900px;

    margin: 35px auto;

}

h2 {

    text-align: center;

    color: #222;

}

.total-votes {

    background: white;

    padding: 20px;

    text-align: center;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow: 0 4px 12px rgba(0,0,0,0.08);

}

.total-votes h3 {

    color: #174ea6;

    font-size: 28px;

    margin: 10px 0;

}

.position-section {

    margin-top: 40px;

}

.position-title {

    color: #174ea6;

    border-bottom: 2px solid #174ea6;

    padding-bottom: 10px;

}

.candidate-card {

    background: white;

    padding: 20px;

    margin-bottom: 20px;

    border-radius: 10px;

    box-shadow: 0 4px 12px rgba(0,0,0,0.08);

    display: flex;

    align-items: center;

    gap: 20px;

}

.candidate-card img {

    width: 100px;

    height: 100px;

    object-fit: cover;

    border-radius: 50%;

}

.candidate-info {

    flex: 1;

}

.candidate-info h3 {

    color: #174ea6;

    margin-bottom: 8px;

}

.vote-count {

    font-weight: bold;

    margin-top: 10px;

}

.progress-container {

    background: #ddd;

    height: 12px;

    border-radius: 10px;

    overflow: hidden;

    margin-top: 10px;

}

.progress-bar {

    background: #174ea6;

    height: 100%;

}

.empty {

    text-align: center;

    background: white;

    padding: 25px;

    border-radius: 10px;

}

.back {

    display: block;

    text-align: center;

    margin: 30px;

    color: #174ea6;

    text-decoration: none;

}

@media (max-width: 500px) {

    .candidate-card {

        flex-direction: column;

        text-align: center;

    }

}
</style>
</head>
<body>
<header>
<h1>VoteHub</h1>
</header>
<div class="container">
<h2>
<?php echo htmlspecialchars($election['title']); ?>
</h2>
<div class="total-votes">
<p>Total Votes Cast Across All Positions</p>
<h3>
<?php echo number_format($total_votes); ?>
</h3>
</div>
<h2>Election Results</h2>
<?php if (!empty($candidates_by_position)): ?>
<?php foreach ($candidates_by_position as $position => $position_candidates): ?>
<?php

            // Calculate total votes for this position

            $position_total = 0;

            foreach ($position_candidates as $candidate) {

                $position_total += (int) $candidate['vote_count'];

            }

            ?>
<section class="position-section">
<h2 class="position-title">
<?php echo htmlspecialchars($position); ?>
</h2>
<?php foreach ($position_candidates as $candidate): ?>
<?php

                    $vote_count = (int) $candidate['vote_count'];

                    $percentage = $position_total > 0

                        ? ($vote_count / $position_total) * 100

                        : 0;

                    ?>
<div class="candidate-card">
<?php if (!empty($candidate['photo'])): ?>
<img

                                src="images/candidates/<?php echo rawurlencode(basename($candidate['photo'])); ?>"

                                alt="Candidate Photo"
>
<?php endif; ?>
<div class="candidate-info">
<h3>
<?php echo htmlspecialchars($candidate['full_name']); ?>
</h3>
<p>
<?php echo htmlspecialchars($candidate['description']); ?>
</p>
<p class="vote-count">
<?php echo number_format($vote_count); ?>

                                votes

                                (<?php echo number_format($percentage, 1); ?>%)
</p>
<div class="progress-container">
<div

                                    class="progress-bar"

                                    style="width: <?php echo $percentage; ?>%;"
></div>
</div>
</div>
</div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php else: ?>
<p class="empty">

            No candidates have been added to this election yet.
</p>
<?php endif; ?>
<a href="dashboard.php" class="back">

        Back to Dashboard
</a>
</div>
</body>
</html>
 