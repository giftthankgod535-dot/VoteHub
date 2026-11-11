<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure only logged-in administrators can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Only allow POST requests

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: manage_candidates.php");

    exit();

}

// Verify CSRF token

if (

    !isset($_POST['csrf_token']) ||

    !isset($_SESSION['csrf_token']) ||

    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

) {

    die("Invalid request. Please refresh the page and try again.");

}

// Get candidate ID

$candidate_id = isset($_POST['candidate_id'])

    ? (int) $_POST['candidate_id']

    : 0;

if ($candidate_id <= 0) {

    header("Location: manage_candidates.php?error=invalid");

    exit();

}

// Retrieve candidate information

$stmt = $conn->prepare(

    "SELECT photo FROM candidates WHERE candidate_id = ?"

);

$stmt->bind_param("i", $candidate_id);

$stmt->execute();

$result = $stmt->get_result();

$candidate = $result->fetch_assoc();

$stmt->close();

if (!$candidate) {

    header("Location: manage_candidates.php?error=notfound");

    exit();

}

// Check whether the candidate has received votes

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_votes

     FROM votes

     WHERE candidate_id = ?"

);

$stmt->bind_param("i", $candidate_id);

$stmt->execute();

$vote_result = $stmt->get_result();

$vote_data = $vote_result->fetch_assoc();

$stmt->close();

if ($vote_data['total_votes'] > 0) {

    header("Location: manage_candidates.php?error=hasvotes");

    exit();

}

// Delete candidate

$stmt = $conn->prepare(

    "DELETE FROM candidates WHERE candidate_id = ?"

);

$stmt->bind_param("i", $candidate_id);

if ($stmt->execute()) {

    $stmt->close();

    // Delete candidate photograph if one exists

    if (!empty($candidate['photo'])) {

        $filename = basename($candidate['photo']);

        $photo_path = __DIR__ . '/../images/candidates/' . $filename;

        if (is_file($photo_path)) {

            unlink($photo_path);

        }

    }

    header("Location: manage_candidates.php?deleted=1");

    exit();

} else {

    $stmt->close();

    header("Location: manage_candidates.php?error=deletefailed");

    exit();

}

?>
 