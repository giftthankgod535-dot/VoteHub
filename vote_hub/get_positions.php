<?php

session_start();

require_once "includes/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([]);

    exit();

}

$election_id = filter_var(

    $_GET['election_id'] ?? '',

    FILTER_VALIDATE_INT

);

if (!$election_id) {

    echo json_encode([]);

    exit();

}

$stmt = $conn->prepare(

    "SELECT position_id, position_name

     FROM election_positions

     WHERE election_id = ?

     ORDER BY position_name ASC"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$positions = [];

while ($row = $result->fetch_assoc()) {

    $positions[] = $row;

}

echo json_encode($positions);

$stmt->close();

$conn->close();

?>
 