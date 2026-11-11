<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Ensure only logged-in administrators can access this page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

// Create CSRF token

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

$error = "";

// Get candidate ID

$candidate_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Retrieve candidate details

$stmt = $conn->prepare(

    "SELECT * FROM candidates WHERE candidate_id = ?"

);

$stmt->bind_param("i", $candidate_id);

$stmt->execute();

$result = $stmt->get_result();

$candidate = $result->fetch_assoc();

$stmt->close();

// Check whether candidate exists

if (!$candidate) {

    die("Candidate not found.");

}

// Process form submission

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token

    if (

        !isset($_POST['csrf_token']) ||

        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

    ) {

        die("Invalid request. Please refresh the page and try again.");

    }

    $full_name = trim($_POST['full_name']);

    $description = trim($_POST['description']);

    if ($full_name === "") {

        $error = "Candidate name is required.";

    } else {

        $photo_path = $candidate['photo'];

        // Check whether a new photo was uploaded

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

                $error = "Photo upload failed. Please try again.";

            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {

                $error = "Photo must not exceed 2MB.";

            } else {

                // Validate image type

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime = $finfo->file($_FILES['photo']['tmp_name']);

                $allowed_types = [

                    'image/jpeg' => 'jpg',

                    'image/png' => 'png',

                    'image/webp' => 'webp'

                ];

                if (!isset($allowed_types[$mime])) {

                    $error = "Only JPG, PNG and WEBP images are allowed.";

                } else {

                    $extension = $allowed_types[$mime];

                    $new_filename = bin2hex(random_bytes(16)) . "." . $extension;

                    $upload_directory = __DIR__ . '/../images/candidates/';

                    $destination = $upload_directory . $new_filename;

                    if (!is_dir($upload_directory)) {

                        $error = "Candidate photo folder was not found.";

                    } elseif (

                        move_uploaded_file(

                            $_FILES['photo']['tmp_name'],

                            $destination

                        )

                    ) {

                        $photo_path = 'images/candidates/' . $new_filename;

                    } else {

                        $error = "Unable to save the uploaded photo.";

                    }

                }

            }

        }

        // Update candidate details

        if ($error === "") {

            $update = $conn->prepare(

                "UPDATE candidates

                 SET full_name = ?, description = ?, photo = ?

                 WHERE candidate_id = ?"

            );

            $update->bind_param(

                "sssi",

                $full_name,

                $description,

                $photo_path,

                $candidate_id

            );

            if ($update->execute()) {

                $update->close();

                header("Location: manage_candidates.php?updated=1");

                exit();

            } else {

                $error = "Unable to update candidate details.";

                $update->close();

            }

        }

    }

    // Keep entered details if an error occurs

    $candidate['full_name'] = $full_name;

    $candidate['description'] = $description;

}

?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Candidate - VoteHub</title>
<style>

        body {

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            padding: 30px;

        }

        .container {

            max-width: 600px;

            margin: auto;

            background: white;

            padding: 25px;

            border-radius: 10px;

        }

        h2 {

            color: #222;

        }

        label {

            display: block;

            margin-top: 15px;

            font-weight: bold;

        }

        input, textarea {

            width: 100%;

            padding: 10px;

            margin-top: 7px;

            border: 1px solid #ccc;

            border-radius: 5px;

            box-sizing: border-box;

        }

        button {

            margin-top: 20px;

            background: #222;

            color: white;

            padding: 12px 20px;

            border: none;

            border-radius: 5px;

            cursor: pointer;

        }

        button:hover {

            background: #444;

        }

        img {

            width: 100px;

            height: 100px;

            object-fit: cover;

            margin-top: 10px;

        }

        .error {

            color: red;

            margin-top: 10px;

        }

        .back {

            display: inline-block;

            margin-top: 20px;

            color: #222;

            text-decoration: none;

        }
</style>
</head>
<body>
<div class="container">
<h2>Edit Candidate</h2>
<p>Update the candidate's information below.</p>
<?php if ($error !== ""): ?>
<p class="error">
<?php echo htmlspecialchars($error); ?>
</p>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input

            type="hidden"

            name="csrf_token"

            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
>
<label>Candidate Name</label>
<input

            type="text"

            name="full_name"

            value="<?php echo htmlspecialchars($candidate['full_name']); ?>"

            required
>
<label>Description</label>
<textarea

            name="description"

            rows="5"
><?php echo htmlspecialchars($candidate['description'] ?? ''); ?></textarea>
<label>Current Photograph</label>
<?php if (!empty($candidate['photo'])): ?>
<img

                src="../<?php echo htmlspecialchars($candidate['photo']); ?>"

                alt="Candidate photograph"
>
<?php else: ?>
<p>No photograph uploaded.</p>
<?php endif; ?>
<label>Upload New Photograph (Optional)</label>
<input

            type="file"

            name="photo"

            accept=".jpg,.jpeg,.png,.webp"
>
<p>Maximum file size: 2MB.</p>
<button type="submit">Save Changes</button>
</form>
<a href="manage_candidates.php" class="back">

        Back to Manage Candidates
</a>
</div>
</body>
</html>
 