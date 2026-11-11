<?php

session_start();

require_once 'includes/db.php';

// Ensure the student is logged in

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}

// Get the election ID from the URL

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

// Retrieve candidates together with their positions

$stmt = $conn->prepare(

    "SELECT c.*, p.position_name

     FROM candidates c

     LEFT JOIN election_positions p

     ON c.position_id = p.position_id

     WHERE c.election_id = ?

     ORDER BY p.position_id ASC, c.full_name ASC"

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Candidates - VoteHub</title>
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

    margin: 30px auto;

}

h2 {

    text-align: center;

    color: #222;

}

.position-section {

    margin-top: 40px;

}

.position-title {

    color: #174ea6;

    border-bottom: 2px solid #174ea6;

    padding-bottom: 10px;

}

.candidate-list {

    display: grid;

    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));

    gap: 25px;

    margin-top: 20px;

}

.candidate-card {

    background: white;

    padding: 20px;

    border-radius: 10px;

    text-align: center;

    box-shadow: 0 4px 12px rgba(0,0,0,0.1);

}

.candidate-card img {

    width: 150px;

    height: 150px;

    object-fit: cover;

    border-radius: 50%;

}

.candidate-card h3 {

    color: #174ea6;

}

.candidate-card p {

    color: #555;

}

.vote-button {

    display: inline-block;

    background: #174ea6;

    color: white;

    padding: 10px 20px;

    text-decoration: none;

    border-radius: 5px;

    margin-top: 10px;

}

.vote-button:hover {

    background: #123d82;

}

.back {

    display: block;

    text-align: center;

    margin: 30px;

    color: #174ea6;

    text-decoration: none;

}

.empty {

    text-align: center;

    color: #666;

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
<h3>Meet the Candidates</h3>
<?php if (!empty($candidates_by_position)): ?>
<?php foreach ($candidates_by_position as $position => $position_candidates): ?>
<section class="position-section">
<h2 class="position-title">
<?php echo htmlspecialchars($position); ?>
</h2>
<div class="candidate-list">
<?php foreach ($position_candidates as $candidate): ?>
<div class="candidate-card">
<?php if (!empty($candidate['photo'])): ?>
<img

                                    src="images/candidates/<?php echo rawurlencode(basename($candidate['photo'])); ?>"

                                    alt="Candidate Photo"
>
<?php endif; ?>
<h3>
<?php echo htmlspecialchars($candidate['full_name']); ?>
</h3>
<p>
<?php echo htmlspecialchars($candidate['description']); ?>
</p>
<?php if (!empty($candidate['manifesto'])): ?>
<p>
<strong>Manifesto:</strong><br>
<?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?>
</p>
<?php endif; ?>
<a

                                href="vote.php?election_id=<?php echo $election_id; ?>&candidate_id=<?php echo $candidate['candidate_id']; ?>"

                                class="vote-button"
>

                                Vote for Candidate
</a>
</div>
<?php endforeach; ?>
</div>
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
 