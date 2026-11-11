<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

$sql = "SELECT elections.*,

               admins.full_name AS admin_name,

               CASE

                   WHEN NOW() < elections.start_date THEN 'upcoming'

                   WHEN NOW() <= elections.end_date THEN 'ongoing'

                   ELSE 'completed'

               END AS current_status

        FROM elections

        INNER JOIN admins

        ON elections.created_by = admins.admin_id

        ORDER BY elections.created_at DESC";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Elections - VoteHub</title>
<style>

* {

    box-sizing: border-box;

}

body {

    font-family: Arial, sans-serif;

    background: linear-gradient(135deg, #e8f1ff, #dce9fa, #f5f8ff);

    margin: 0;

    padding: 35px 20px;

    color: #334155;

}

.container {

    max-width: 1250px;

    margin: auto;

    background: rgba(255, 255, 255, 0.97);

    padding: 30px;

    border-radius: 16px;

    box-shadow: 0 8px 30px rgba(36, 59, 100, 0.12);

    border: 1px solid #e0eaf8;

}

h2 {

    color: #243b64;

    margin-bottom: 8px;

    font-size: 27px;

}

.subtitle {

    color: #718096;

    margin-bottom: 25px;

    line-height: 1.6;

}

table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 25px;

    overflow: hidden;

    border-radius: 10px;

}

th, td {

    padding: 15px;

    text-align: left;

    border-bottom: 1px solid #e5edf7;

}

th {

    background: #243b64;

    color: white;

    font-size: 14px;

}

td {

    font-size: 14px;

    line-height: 1.6;

}

tbody tr:nth-child(even) {

    background: #f2f7ff;

}

tbody tr:hover {

    background: #e5efff;

}

.status {

    padding: 7px 12px;

    border-radius: 20px;

    display: inline-block;

    font-size: 12px;

    font-weight: bold;

}

.upcoming {

    background: #fff1c7;

    color: #8a6500;

}

.ongoing {

    background: #d9f5e4;

    color: #176b3a;

}

.completed {

    background: #ffe0e0;

    color: #a52a2a;

}

.edit {

    display: inline-block;

    background: #2878d0;

    color: white;

    padding: 8px 13px;

    text-decoration: none;

    border-radius: 6px;

    border: none;

    transition: 0.3s;

}

.edit:hover {

    background: #185ba5;

}

.delete {

    display: inline-block;

    background: #e34b58;

    color: white;

    padding: 8px 13px;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    transition: 0.3s;

}

.delete:hover {

    background: #bd2635;

}

.back, .create {

    display: inline-block;

    margin-top: 25px;

    text-decoration: none;

    padding: 12px 17px;

    border-radius: 7px;

    color: white;

    transition: 0.3s;

}

.back {

    background: #243b64;

}

.back:hover {

    background: #172b4d;

}

.create {

    background: #2878d0;

    margin-left: 10px;

}

.create:hover {

    background: #185ba5;

}

.empty {

    text-align: center;

    padding: 25px;

    color: #718096;

}

.success {

    background: #d9f5e4;

    color: #176b3a;

    padding: 13px;

    border-radius: 7px;

    margin-top: 15px;

    border-left: 4px solid #28a35a;

}

.error {

    background: #ffe0e0;

    color: #a52a2a;

    padding: 13px;

    border-radius: 7px;

    margin-top: 15px;

    border-left: 4px solid #dc3545;

}

.action-form {

    display: inline-block;

    margin: 0;

}

@media (max-width: 900px) {

    .container {

        overflow-x: auto;

    }

    table {

        min-width: 900px;

    }

}

@media (max-width: 600px) {

    body {

        padding: 15px 8px;

    }

    .container {

        padding: 18px;

    }

    h2 {

        font-size: 23px;

    }

    .back, .create {

        margin-left: 0;

        margin-right: 5px;

    }

}
</style>
</head>
<body>
<div class="container">
<h2>Manage Elections</h2>
<p class="subtitle">

View and manage all elections created on VoteHub.
</p>
<!-- Success messages -->
<?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
<div class="success">

    Election updated successfully!
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
<div class="success">

    Election deleted successfully!
</div>
<?php endif; ?>
<!-- Error messages -->
<?php if (isset($_GET['error'])): ?>
<div class="error">
<?php

$error_messages = [

    'invalid' => 'Invalid request. Please try again.',

    'notfound' => 'The election could not be found.',

    'hasvotes' => 'This election cannot be deleted because votes have already been submitted.',

    'hascandidates' => 'Remove all candidates from this election before deleting it.',

    'deletefailed' => 'Unable to delete the election. Please try again.'

];

$error_code = $_GET['error'];

echo htmlspecialchars(

    $error_messages[$error_code] ?? 'An unexpected error occurred.'

);

?>
</div>
<?php endif; ?>
<table>
<thead>
<tr>
<th>Election Title</th>
<th>Description</th>
<th>Start Date</th>
<th>End Date</th>
<th>Status</th>
<th>Created By</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($election = $result->fetch_assoc()): ?>
<tr>
<td>
<?php echo htmlspecialchars($election['title']); ?>
</td>
<td>
<?php echo htmlspecialchars($election['description'] ?? ''); ?>
</td>
<td>
<?php echo htmlspecialchars($election['start_date']); ?>
</td>
<td>
<?php echo htmlspecialchars($election['end_date']); ?>
</td>
<td>
<span class="status <?php echo htmlspecialchars($election['current_status']); ?>">
<?php echo htmlspecialchars(ucfirst($election['current_status'])); ?>
</span>
</td>
<td>
<?php echo htmlspecialchars($election['admin_name']); ?>
</td>
<td>
<a

    href="edit_election.php?id=<?php echo (int)$election['election_id']; ?>"

    class="edit">

    Edit
</a>
<form

    action="delete_election.php"

    method="POST"

    class="action-form"

    onsubmit="return confirm('Are you sure you want to delete this election? This action cannot be undone.');">
<input

        type="hidden"

        name="election_id"

        value="<?php echo (int)$election['election_id']; ?>">
<input

        type="hidden"

        name="csrf_token"

        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<button type="submit" class="delete">

        Delete
</button>
</form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="7" class="empty">

No elections have been created yet.
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
<a href="dashboard.php" class="back">

Back to Admin Dashboard
</a>
<a href="create_election.php" class="create">

Create New Election
</a>
</div>
</body>
</html>
 