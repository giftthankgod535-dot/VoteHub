<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure only logged-in administrators can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Only accept POST requests

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: manage_elections.php");

    exit();

}

// Verify CSRF token

if (

    !isset($_POST['csrf_token']) ||

    !isset($_SESSION['csrf_token']) ||

    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

) {

    header("Location: manage_elections.php?error=invalid");

    exit();

}

// Get election ID

$election_id = isset($_POST['election_id'])

    ? (int) $_POST['election_id']

    : 0;

if ($election_id <= 0) {

    header("Location: manage_elections.php?error=invalid");

    exit();

}

// Check whether the election exists

$stmt = $conn->prepare(

    "SELECT election_id FROM elections WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$election = $result->fetch_assoc();

$stmt->close();

if (!$election) {

    header("Location: manage_elections.php?error=notfound");

    exit();

}

// Check whether the election has votes

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_votes

     FROM votes

     WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$vote_data = $result->fetch_assoc();

$stmt->close();

if ($vote_data['total_votes'] > 0) {

    header("Location: manage_elections.php?error=hasvotes");

    exit();

}

// Check whether the election has candidates

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_candidates

     FROM candidates

     WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$candidate_data = $result->fetch_assoc();

$stmt->close();

if ($candidate_data['total_candidates'] > 0) {

    header("Location: manage_elections.php?error=hascandidates");

    exit();

}

// Delete the election

$stmt = $conn->prepare(

    "DELETE FROM elections WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

if ($stmt->execute() && $stmt->affected_rows === 1) {

    $stmt->close();

    header("Location: manage_elections.php?deleted=1");

    exit();

} else {

    $stmt->close();

    header("Location: manage_elections.php?error=deletefailed");

    exit();

}

?>
 