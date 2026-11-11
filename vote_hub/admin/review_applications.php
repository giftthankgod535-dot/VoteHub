<?php

session_start();

require_once "../includes/db.php";

// Only logged-in admins can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Generate CSRF token

if (empty($_SESSION['review_csrf'])) {

    $_SESSION['review_csrf'] = bin2hex(random_bytes(32));

}

$message = "";

$message_type = "";

// Handle approval and rejection

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(

        $_SESSION['review_csrf'],

        $_POST['csrf_token'] ?? ''

    )) {

        die("Invalid request. Please refresh the page.");

    }

    $application_id = filter_var(

        $_POST['application_id'] ?? '',

        FILTER_VALIDATE_INT

    );

    $action = $_POST['action'] ?? '';

    $feedback = trim($_POST['admin_feedback'] ?? '');

    if (!$application_id || !in_array($action, ['approve', 'reject'], true)) {

        $message = "Invalid application request.";

        $message_type = "error";

    } else {

        $conn->begin_transaction();

        try {

            // Lock the application while it is being reviewed

            $stmt = $conn->prepare(

                "SELECT application_id, user_id, election_id,

                        position_id, full_name, description,

                        manifesto, photo, status

                 FROM candidate_applications

                 WHERE application_id = ?

                 FOR UPDATE"

            );

            $stmt->bind_param("i", $application_id);

            $stmt->execute();

            $result = $stmt->get_result();

            $application = $result->fetch_assoc();

            $stmt->close();

            if (!$application) {

                throw new Exception("Application not found.");

            }

            if ($application['status'] !== 'pending') {

                throw new Exception("This application has already been reviewed.");

            }

            if ($action === 'approve') {

                // Ensure the position still belongs to the election

                $check = $conn->prepare(

                    "SELECT position_id

                     FROM election_positions

                     WHERE position_id = ? AND election_id = ?"

                );

                $check->bind_param(

                    "ii",

                    $application['position_id'],

                    $application['election_id']

                );

                $check->execute();

                if ($check->get_result()->num_rows === 0) {

                    throw new Exception("The selected position is no longer available.");

                }

                $check->close();

                // Copy the application photograph into the approved candidates folder

                $source_photo = __DIR__ .

                    "/../images/candidate_applications/" .

                    basename($application['photo']);

                $candidate_folder = __DIR__ . "/../images/candidates/";

                if (!is_dir($candidate_folder)) {

                    mkdir($candidate_folder, 0755, true);

                }

                if (

                    empty($application['photo']) ||

                    !is_file($source_photo)

                ) {

                    throw new Exception("The applicant's photograph could not be found.");

                }

                $extension = strtolower(

                    pathinfo($source_photo, PATHINFO_EXTENSION)

                );

                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {

                    throw new Exception("Invalid applicant photograph.");

                }

                $candidate_photo = bin2hex(random_bytes(16))

                                 . "." . $extension;

                $destination_photo = $candidate_folder . $candidate_photo;

                if (!copy($source_photo, $destination_photo)) {

                    throw new Exception("Unable to copy the candidate photograph.");

                }

                // Add the approved applicant to the existing candidates table

                $insert = $conn->prepare(

                    "INSERT INTO candidates

                     (election_id, position_id, full_name,

                      description, manifesto, photo)

                     VALUES (?, ?, ?, ?, ?, ?)"

                );

                $insert->bind_param(

                    "iissss",

                    $application['election_id'],

                    $application['position_id'],

                    $application['full_name'],

                    $application['description'],

                    $application['manifesto'],

                    $candidate_photo

                );

                if (!$insert->execute()) {

                    throw new Exception("Unable to add the approved candidate.");

                }

                $insert->close();

                // Update application status

                $update = $conn->prepare(

                    "UPDATE candidate_applications

                     SET status = 'approved',

                         admin_feedback = ?,

                         reviewed_at = NOW()

                     WHERE application_id = ?"

                );

                $update->bind_param(

                    "si",

                    $feedback,

                    $application_id

                );

                if (!$update->execute()) {

                    throw new Exception("Unable to update application status.");

                }

                $update->close();

                $message = "Application approved! The student has been added to the candidates list.";

            } else {

                // Reject application

                $update = $conn->prepare(

                    "UPDATE candidate_applications

                     SET status = 'rejected',

                         admin_feedback = ?,

                         reviewed_at = NOW()

                     WHERE application_id = ?"

                );

                $update->bind_param(

                    "si",

                    $feedback,

                    $application_id

                );

                if (!$update->execute()) {

                    throw new Exception("Unable to reject application.");

                }

                $update->close();

                $message = "Application rejected successfully.";

            }

            $conn->commit();

            $message_type = "success";

        } catch (Throwable $e) {

            $conn->rollback();

            // Remove an unused copied photo if approval failed

            if (!empty($destination_photo) && file_exists($destination_photo)) {

                unlink($destination_photo);

            }

            $message = $e->getMessage();

            $message_type = "error";

        }

    }

}

// Fetch all applications

$applications = $conn->query(

    "SELECT ca.application_id,

            ca.full_name,

            ca.description,

            ca.manifesto,

            ca.photo,

            ca.status,

            ca.admin_feedback,

            ca.submitted_at,

            e.title AS election_title,

            ep.position_name

     FROM candidate_applications ca

     JOIN elections e ON ca.election_id = e.election_id

     JOIN election_positions ep ON ca.position_id = ep.position_id

     ORDER BY

        CASE WHEN ca.status = 'pending' THEN 0 ELSE 1 END,

        ca.submitted_at DESC"

);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Applications | VoteHub</title>
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

            max-width: 1100px;

            margin: auto;

            background: white;

            padding: 30px;

            border-radius: 15px;

            box-shadow: 0 5px 20px rgba(0,0,0,0.08);

        }

        h1, h2 {

            color: #173b70;

        }

        .back-link {

            display: inline-block;

            margin-bottom: 20px;

            color: #2457a6;

            text-decoration: none;

            font-weight: bold;

        }

        .message {

            padding: 13px;

            border-radius: 7px;

            margin: 20px 0;

        }

        .success {

            background: #e2f7e9;

            color: #176b35;

        }

        .error {

            background: #ffe7e7;

            color: #a32323;

        }

        .application-card {

            border: 1px solid #dce5f1;

            border-radius: 12px;

            padding: 22px;

            margin: 22px 0;

            background: #fff;

        }

        .application-card h3 {

            color: #173b70;

            margin-top: 0;

        }

        .candidate-photo {

            width: 120px;

            height: 120px;

            object-fit: cover;

            border-radius: 10px;

            margin: 12px 0;

        }

        .details {

            background: #f3f7ff;

            padding: 15px;

            border-radius: 8px;

            margin: 15px 0;

        }

        .details p {

            line-height: 1.6;

        }

        .status {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

            text-transform: capitalize;

        }

        .pending {

            background: #fff0c2;

            color: #8a5b00;

        }

        .approved {

            background: #d9f5e2;

            color: #176b35;

        }

        .rejected {

            background: #ffe0e0;

            color: #a32323;

        }

        textarea {

            width: 100%;

            min-height: 75px;

            padding: 12px;

            border: 1px solid #ccd5e2;

            border-radius: 7px;

            margin: 10px 0;

            resize: vertical;

        }

        button {

            padding: 11px 18px;

            border: none;

            border-radius: 7px;

            color: white;

            cursor: pointer;

            margin: 5px 5px 5px 0;

        }

        .approve-btn {

            background: #238447;

        }

        .reject-btn {

            background: #d94343;

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
<a href="dashboard.php" class="back-link">

        ← Back to Admin Dashboard
</a>
<h1>Candidate Applications</h1>
<p>Review student applications and decide whether to approve or reject them.</p>
<?php if ($message !== ""): ?>
<div class="message <?php echo $message_type; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<?php if ($applications->num_rows > 0): ?>
<?php while ($application = $applications->fetch_assoc()): ?>
<div class="application-card">
<h3>
<?php echo htmlspecialchars($application['full_name']); ?>
</h3>
<span class="status <?php echo htmlspecialchars($application['status']); ?>">
<?php echo htmlspecialchars($application['status']); ?>
</span>
<p>
<strong>Election:</strong>
<?php echo htmlspecialchars($application['election_title']); ?>
</p>
<p>
<strong>Position:</strong>
<?php echo htmlspecialchars($application['position_name']); ?>
</p>
<?php if (!empty($application['photo'])): ?>
<img

                        class="candidate-photo"

                        src="../images/candidate_applications/<?php echo rawurlencode(basename($application['photo'])); ?>"

                        alt="Applicant photograph"
>
<?php endif; ?>
<div class="details">
<h4>Candidate Description</h4>
<p>
<?php echo nl2br(htmlspecialchars($application['description'])); ?>
</p>
<h4>Manifesto</h4>
<p>
<?php echo nl2br(htmlspecialchars($application['manifesto'])); ?>
</p>
</div>
<p>
<strong>Submitted:</strong>
<?php echo htmlspecialchars($application['submitted_at']); ?>
</p>
<?php if ($application['status'] === 'pending'): ?>
<form method="POST">
<input type="hidden" name="csrf_token"

                               value="<?php echo htmlspecialchars($_SESSION['review_csrf']); ?>">
<input type="hidden" name="application_id"

                               value="<?php echo $application['application_id']; ?>">
<label><strong>Admin Feedback (optional)</strong></label>
<textarea name="admin_feedback"

                                  placeholder="Enter feedback for the applicant..."></textarea>
<button

                            type="submit"

                            name="action"

                            value="approve"

                            class="approve-btn"

                            onclick="return confirm('Approve this application and add the student to the candidates list?');"
>

                            Approve Application
</button>
<button

                            type="submit"

                            name="action"

                            value="reject"

                            class="reject-btn"

                            onclick="return confirm('Are you sure you want to reject this application?');"
>

                            Reject Application
</button>
</form>
<?php else: ?>
<p>
<strong>Admin Feedback:</strong>
<?php echo htmlspecialchars($application['admin_feedback'] ?? 'No feedback provided.'); ?>
</p>
<?php endif; ?>
</div>
<?php endwhile; ?>
<?php else: ?>
<p>No candidate applications have been submitted yet.</p>
<?php endif; ?>
</div>
</body>
</html>
 