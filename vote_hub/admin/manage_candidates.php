<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure only logged-in administrators can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Generate CSRF token

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

// Retrieve all candidates and their corresponding elections

$sql = "SELECT candidates.*, elections.title AS election_title

        FROM candidates

        INNER JOIN elections

        ON candidates.election_id = elections.election_id

        ORDER BY candidates.created_at DESC";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Candidates - VoteHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>

    * {

        box-sizing: border-box;

    }

    body {

        font-family: Arial, sans-serif;

        background: linear-gradient(135deg, #e8f1ff, #dce9fa, #f5f8ff);

        margin: 0;

        padding: 30px 20px;

        min-height: 100vh;

    }

    .container {

        max-width: 1150px;

        margin: auto;

        background: white;

        padding: 30px;

        border-radius: 15px;

        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);

    }

    h2 {

        color: #173b70;

        margin-bottom: 8px;

        font-size: 28px;

    }

    .subtitle {

        color: #667085;

        margin-bottom: 25px;

        line-height: 1.6;

    }

    .table-wrapper {

        width: 100%;

        overflow-x: auto;

        border-radius: 10px;

    }

    table {

        width: 100%;

        border-collapse: collapse;

        margin-top: 15px;

        min-width: 750px;

    }

    th, td {

        padding: 15px;

        border-bottom: 1px solid #e5eaf2;

        text-align: left;

        vertical-align: middle;

    }

    th {

        background: #173b70;

        color: white;

        font-size: 14px;

        letter-spacing: 0.3px;

    }

    tr:nth-child(even) {

        background: #f5f8ff;

    }

    tr:hover {

        background: #eaf2ff;

    }

    td {

        color: #344054;

        font-size: 14px;

        line-height: 1.5;

    }

    img {

        width: 70px;

        height: 70px;

        object-fit: cover;

        border-radius: 10px;

        border: 2px solid #e0eaff;

    }

    .no-photo {

        color: #98a2b3;

        font-size: 13px;

    }

    .edit-btn {

        display: inline-block;

        background: #2878d0;

        color: white;

        padding: 9px 14px;

        text-decoration: none;

        border-radius: 6px;

        margin-bottom: 7px;

        font-size: 13px;

        transition: 0.2s;

    }

    .delete-btn {

        background: #e05260;

        color: white;

        padding: 9px 14px;

        border: none;

        border-radius: 6px;

        cursor: pointer;

        font-size: 13px;

        transition: 0.2s;

    }

    .edit-btn:hover {

        background: #155ba8;

    }

    .delete-btn:hover {

        background: #bd3544;

    }

    .back {

        display: inline-block;

        margin-top: 25px;

        text-decoration: none;

        background: #173b70;

        color: white;

        padding: 12px 18px;

        border-radius: 7px;

        transition: 0.2s;

    }

    .back:hover {

        background: #0e2850;

    }

    .message {

        margin: 15px 0;

        padding: 13px 15px;

        border-radius: 7px;

        font-size: 14px;

    }

    .success {

        background: #e2f5e9;

        color: #176b37;

        border: 1px solid #b7e4c7;

    }

    .error {

        background: #fde8e8;

        color: #a12626;

        border: 1px solid #f5c2c2;

    }

    .empty {

        text-align: center;

        padding: 25px;

        color: #667085;

    }

    form {

        display: inline-block;

        margin: 0;

    }

    @media (max-width: 600px) {

        body {

            padding: 15px 10px;

        }

        .container {

            padding: 20px 15px;

        }

        h2 {

            font-size: 23px;

        }

        .subtitle {

            font-size: 14px;

        }

    }
</style>
</head>
<body>
<div class="container">
<h2>Manage Candidates</h2>
<p class="subtitle">

        View and manage all candidates registered for elections on VoteHub.
</p>
<?php if (isset($_GET['updated'])): ?>
<p class="message success">

            Candidate details updated successfully!
</p>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<p class="message success">

            Candidate deleted successfully!
</p>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<p class="message error">
<?php

            $errors = [

                'invalid' => 'Invalid candidate selection.',

                'notfound' => 'Candidate was not found.',

                'hasvotes' => 'This candidate cannot be deleted because they have already received votes.',

                'deletefailed' => 'Unable to delete candidate. Please try again.'

            ];

            echo htmlspecialchars(

                $errors[$_GET['error']] ?? 'An error occurred.'

            );

            ?>
</p>
<?php endif; ?>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>Photo</th>
<th>Candidate Name</th>
<th>Description</th>
<th>Election</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($candidate = $result->fetch_assoc()): ?>
<tr>
<td>

<?php if (!empty($candidate['photo'])): ?>
<img
       src="../images/candidates/<?php echo rawurlencode(basename($candidate['photo'])); ?>"
       alt="Candidate Photo"
>
<?php else: ?>
<span class="no-photo">No photo</span>
<?php endif; ?>

</td>
<td>
<?php echo htmlspecialchars($candidate['full_name']); ?>
</td>
<td>
<?php echo htmlspecialchars($candidate['description'] ?? ''); ?>
</td>
<td>
<?php echo htmlspecialchars($candidate['election_title']); ?>
</td>
<td>
<a

                                    href="edit_candidate.php?id=<?php echo (int)$candidate['candidate_id']; ?>"

                                    class="edit-btn"
>

                                    Edit
</a>
<form

                                    method="POST"

                                    action="delete_candidate.php"

                                    onsubmit="return confirm('Are you sure you want to delete this candidate?');"
>
<input

                                        type="hidden"

                                        name="candidate_id"

                                        value="<?php echo (int)$candidate['candidate_id']; ?>"
>
<input

                                        type="hidden"

                                        name="csrf_token"

                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
>
<button type="submit" class="delete-btn">

                                        Delete
</button>
</form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="5" class="empty">

                            No candidates have been added yet.
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<a href="dashboard.php" class="back">

        Back to Admin Dashboard
</a>
</div>
</body>
</html>
 