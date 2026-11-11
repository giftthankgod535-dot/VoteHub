<?php

session_start();

require_once "../includes/db.php";

// Ensure only logged-in admins can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Generate CSRF token

if (empty($_SESSION['positions_csrf'])) {

    $_SESSION['positions_csrf'] = bin2hex(random_bytes(32));

}

$message = "";

$message_type = "";

// Add a new position

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_position'])) {

    if (!hash_equals($_SESSION['positions_csrf'], $_POST['csrf_token'] ?? '')) {

        die("Invalid request. Please refresh the page and try again.");

    }

    $election_id = filter_var($_POST['election_id'] ?? '', FILTER_VALIDATE_INT);

    $position_name = trim($_POST['position_name'] ?? '');

    $description = trim($_POST['description'] ?? '');

    if (!$election_id || $position_name === '') {

        $message = "Please select an election and enter a position name.";

        $message_type = "error";

    } else {

        // Check that the election exists and is not completed

        $check = $conn->prepare(

            "SELECT election_id FROM elections

             WHERE election_id = ? AND status != 'completed'"

        );

        $check->bind_param("i", $election_id);

        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows === 0) {

            $message = "The selected election is unavailable or completed.";

            $message_type = "error";

        } else {

            $stmt = $conn->prepare(

                "INSERT INTO election_positions

                 (election_id, position_name, description)

                 VALUES (?, ?, ?)"

            );

            $stmt->bind_param(

                "iss",

                $election_id,

                $position_name,

                $description

            );

            if ($stmt->execute()) {

                $message = "Position added successfully!";

                $message_type = "success";

            } elseif ($conn->errno == 1062) {

                $message = "This position already exists in the selected election.";

                $message_type = "error";

            } else {

                $message = "Unable to add position. Please try again.";

                $message_type = "error";

            }

            $stmt->close();

        }

        $check->close();

    }

}

// Delete a position

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_position'])) {

    if (!hash_equals($_SESSION['positions_csrf'], $_POST['csrf_token'] ?? '')) {

        die("Invalid request. Please refresh the page and try again.");

    }

    $position_id = filter_var($_POST['position_id'] ?? '', FILTER_VALIDATE_INT);

    if ($position_id) {

        $stmt = $conn->prepare(

            "DELETE FROM election_positions WHERE position_id = ?"

        );

        $stmt->bind_param("i", $position_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {

            $message = "Position deleted successfully.";

            $message_type = "success";

        } else {

            $message = "This position could not be deleted. It may already be in use.";

            $message_type = "error";

        }

        $stmt->close();

    } else {

        $message = "Invalid position selected.";

        $message_type = "error";

    }

}

// Fetch elections that are not completed

$elections = $conn->query(

    "SELECT election_id, title

     FROM elections

     WHERE status != 'completed'

     ORDER BY election_id DESC"

);

// Fetch all positions

$positions = $conn->query(

    "SELECT ep.position_id, ep.position_name, ep.description,

            e.title AS election_title

     FROM election_positions ep

     JOIN elections e ON ep.election_id = e.election_id

     ORDER BY ep.position_id DESC"

);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Positions | VoteHub</title>
<style>

        * {

            box-sizing: border-box;

        }

        body {

            font-family: Arial, sans-serif;

            background: linear-gradient(135deg, #e8f0ff, #f5f8ff);

            margin: 0;

            padding: 30px 15px;

            color: #243247;

        }

        .container {

            max-width: 1000px;

            margin: auto;

            background: white;

            padding: 30px;

            border-radius: 15px;

            box-shadow: 0 5px 20px rgba(0,0,0,0.08);

        }

        h1, h2 {

            color: #173b70;

        }

        .form-group {

            margin-bottom: 18px;

        }

        label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

        }

        input, select, textarea {

            width: 100%;

            padding: 12px;

            border: 1px solid #ccd5e2;

            border-radius: 7px;

            font-size: 15px;

        }

        textarea {

            resize: vertical;

            min-height: 90px;

        }

        button {

            background: #2457a6;

            color: white;

            padding: 11px 18px;

            border: none;

            border-radius: 7px;

            cursor: pointer;

            font-size: 14px;

        }

        button:hover {

            background: #173b70;

        }

        .delete-btn {

            background: #d94343;

        }

        .delete-btn:hover {

            background: #ad2929;

        }

        .message {

            padding: 12px;

            border-radius: 7px;

            margin: 18px 0;

        }

        .success {

            background: #e2f7e9;

            color: #176b35;

        }

        .error {

            background: #ffe7e7;

            color: #a32323;

        }

        .table-wrapper {

            overflow-x: auto;

            margin-top: 20px;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 600px;

        }

        th, td {

            padding: 13px;

            text-align: left;

            border-bottom: 1px solid #e2e8f0;

        }

        th {

            background: #173b70;

            color: white;

        }

        tr:nth-child(even) {

            background: #f3f7ff;

        }

        .back-link {

            display: inline-block;

            margin-bottom: 20px;

            color: #2457a6;

            text-decoration: none;

            font-weight: bold;

        }

        @media (max-width: 600px) {

            .container {

                padding: 20px 15px;

            }

        }
</style>
</head>
<body>
<div class="container">
<a href="dashboard.php" class="back-link">← Back to Admin Dashboard</a>
<h1>Manage Election Positions</h1>
<p>Create and manage the positions available in each election.</p>
<?php if ($message !== ""): ?>
<div class="message <?php echo $message_type; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<h2>Add a New Position</h2>
<form method="POST">
<input type="hidden" name="csrf_token"

               value="<?php echo htmlspecialchars($_SESSION['positions_csrf']); ?>">
<div class="form-group">
<label>Select Election</label>
<select name="election_id" required>
<option value="">-- Choose an Election --</option>
<?php while ($election = $elections->fetch_assoc()): ?>
<option value="<?php echo $election['election_id']; ?>">
<?php echo htmlspecialchars($election['title']); ?>
</option>
<?php endwhile; ?>
</select>
</div>
<div class="form-group">
<label>Position Name</label>
<input type="text" name="position_name"

                   placeholder="e.g. President" maxlength="100" required>
</div>
<div class="form-group">
<label>Position Description</label>
<textarea name="description"

                      placeholder="Describe the responsibilities of this position..."></textarea>
</div>
<button type="submit" name="add_position">

            Add Position
</button>
</form>
<hr style="margin: 35px 0; border: 0; border-top: 1px solid #ddd;">
<h2>Existing Positions</h2>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>Position</th>
<th>Election</th>
<th>Description</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if ($positions->num_rows > 0): ?>
<?php while ($position = $positions->fetch_assoc()): ?>
<tr>
<td>
<?php echo htmlspecialchars($position['position_name']); ?>
</td>
<td>
<?php echo htmlspecialchars($position['election_title']); ?>
</td>
<td>
<?php echo htmlspecialchars($position['description'] ?? ''); ?>
</td>
<td>
<form method="POST"

                                      onsubmit="return confirm('Are you sure you want to delete this position?');">
<input type="hidden" name="csrf_token"

                                           value="<?php echo htmlspecialchars($_SESSION['positions_csrf']); ?>">
<input type="hidden" name="position_id"

                                           value="<?php echo $position['position_id']; ?>">
<button type="submit" name="delete_position"

                                            class="delete-btn">

                                        Delete
</button>
</form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="4">No positions have been created yet.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
 