<?php

session_start();

require_once 'includes/db.php';

// Ensure the student is logged in

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}

$message = "";

$message_type = "";

// Generate CSRF security token

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

// Get election and candidate IDs

$election_id = filter_input(INPUT_GET, 'election_id', FILTER_VALIDATE_INT);

$candidate_id = filter_input(INPUT_GET, 'candidate_id', FILTER_VALIDATE_INT);

// Process submitted vote

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_election_id = filter_input(INPUT_POST, 'election_id', FILTER_VALIDATE_INT);

    $posted_candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);

    // Verify CSRF token

    if (

        !isset($_POST['csrf_token']) ||

        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

    ) {

        $message = "Invalid request. Please refresh the page.";

        $message_type = "error";

    } elseif (!$posted_election_id || !$posted_candidate_id) {

        $message = "Invalid election or candidate.";

        $message_type = "error";

    } else {

        // Check that the election is open

        $stmt = $conn->prepare(

            "SELECT election_id

             FROM elections

             WHERE election_id = ?

             AND start_date <= NOW()

             AND end_date >= NOW()"

        );

        $stmt->bind_param("i", $posted_election_id);

        $stmt->execute();

        $election_open = $stmt->get_result()->num_rows === 1;

        $stmt->close();

        if (!$election_open) {

            $message = "This election is not currently open for voting.";

            $message_type = "error";

        } else {

            // Retrieve candidate and their position

            $stmt = $conn->prepare(

                "SELECT candidate_id, position_id

                 FROM candidates

                 WHERE candidate_id = ?

                 AND election_id = ?"

            );

            $stmt->bind_param(

                "ii",

                $posted_candidate_id,

                $posted_election_id

            );

            $stmt->execute();

            $candidate_data = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$candidate_data) {

                $message = "Invalid candidate selected.";

                $message_type = "error";

            } elseif (empty($candidate_data['position_id'])) {

                $message = "This candidate does not have an assigned position and cannot receive votes.";

                $message_type = "error";

            } else {

                $position_id = (int) $candidate_data['position_id'];

                // Save the student's vote

                $stmt = $conn->prepare(

                    "INSERT INTO votes

                     (user_id, election_id, position_id, candidate_id)

                     VALUES (?, ?, ?, ?)"

                );

                $stmt->bind_param(

                    "iiii",

                    $_SESSION['user_id'],

                    $posted_election_id,

                    $position_id,

                    $posted_candidate_id

                );

                try {

                    if ($stmt->execute()) {

                        $message = "Your vote has been submitted successfully!";

                        $message_type = "success";

                    }

                } catch (mysqli_sql_exception $e) {

                    if ($e->getCode() == 1062) {

                        $message = "You have already voted for this position in this election.";

                        $message_type = "error";

                    } else {

                        $message = "Unable to submit your vote. Please try again.";

                        $message_type = "error";

                    }

                }

                $stmt->close();

            }

        }

    }

    // Use submitted IDs to display the selected candidate

    $election_id = $posted_election_id;

    $candidate_id = $posted_candidate_id;

}

// Check that the IDs are valid

if (!$election_id || !$candidate_id) {

    die("Invalid election or candidate selected.");

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

// Retrieve candidate details and position

$stmt = $conn->prepare(

    "SELECT c.*, p.position_name

     FROM candidates c

     LEFT JOIN election_positions p

     ON c.position_id = p.position_id

     WHERE c.candidate_id = ?

     AND c.election_id = ?"

);

$stmt->bind_param("ii", $candidate_id, $election_id);

$stmt->execute();

$candidate = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$candidate) {

    die("Candidate not found.");

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cast Your Vote - VoteHub</title>
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

    width: 400px;

    max-width: 85%;

    background: white;

    margin: 40px auto;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

    text-align: center;

}

.candidate-photo {

    width: 160px;

    height: 160px;

    object-fit: cover;

    border-radius: 50%;

}

h2 {

    color: #174ea6;

}

.message {

    padding: 12px;

    border-radius: 5px;

}

.success {

    background: #d4edda;

    color: #155724;

}

.error {

    background: #f8d7da;

    color: #721c24;

}

button {

    width: 100%;

    padding: 13px;

    margin-top: 20px;

    background: #174ea6;

    color: white;

    border: none;

    border-radius: 5px;

    cursor: pointer;

}

button:hover {

    background: #123d82;

}

.back {

    display: block;

    margin-top: 20px;

    color: #174ea6;

    text-decoration: none;

}
</style>
</head>
<body>
<header>
<h1>VoteHub</h1>
</header>
<div class="container">
<h2>Confirm Your Vote</h2>
<h3>
<?php echo htmlspecialchars($election['title']); ?>
</h3>
<?php if (!empty($candidate['position_name'])): ?>
<p>
<strong>Position:</strong>
<?php echo htmlspecialchars($candidate['position_name']); ?>
</p>
<?php endif; ?>
<?php if (!empty($candidate['photo'])): ?>
<img

            src="images/candidates/<?php echo rawurlencode(basename($candidate['photo'])); ?>"

            alt="Candidate Photo"

            class="candidate-photo"
>
<?php endif; ?>
<h2>
<?php echo htmlspecialchars($candidate['full_name']); ?>
</h2>
<p>
<?php echo htmlspecialchars($candidate['description']); ?>
</p>
<?php if (!empty($candidate['manifesto'])): ?>
<p>
<strong>Manifesto:</strong><br>
<?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?>
</p>
<?php endif; ?>
<?php if (!empty($message)): ?>
<p class="message <?php echo htmlspecialchars($message_type); ?>">
<?php echo htmlspecialchars($message); ?>
</p>
<?php endif; ?>
<?php if ($message_type !== "success"): ?>
<form method="POST" action="">
<input

                type="hidden"

                name="csrf_token"

                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
>
<input

                type="hidden"

                name="election_id"

                value="<?php echo $election_id; ?>"
>
<input

                type="hidden"

                name="candidate_id"

                value="<?php echo $candidate_id; ?>"
>
<p>Are you sure you want to vote for this candidate?</p>
<button type="submit">

                Confirm My Vote
</button>
</form>
<?php endif; ?>
<a

        href="candidates.php?election_id=<?php echo $election_id; ?>"

        class="back"
>

        Back to Candidates
</a>
</div>
</body>
</html>
 