<?php

session_start();

require_once "includes/db.php";

// Only logged-in students can apply

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}

$user_id = (int) $_SESSION['user_id'];

$message = "";

$message_type = "";

// Generate CSRF token

if (empty($_SESSION['application_csrf'])) {

    $_SESSION['application_csrf'] = bin2hex(random_bytes(32));

}

// Handle application submission

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(

        $_SESSION['application_csrf'],

        $_POST['csrf_token'] ?? ''

    )) {

        die("Invalid request. Please refresh the page.");

    }

    $election_id = filter_var(

        $_POST['election_id'] ?? '',

        FILTER_VALIDATE_INT

    );

    $position_id = filter_var(

        $_POST['position_id'] ?? '',

        FILTER_VALIDATE_INT

    );

    $description = trim($_POST['description'] ?? '');

    $manifesto = trim($_POST['manifesto'] ?? '');

    if (

        !$election_id ||

        !$position_id ||

        $description === '' ||

        $manifesto === ''

    ) {

        $message = "Please complete all required fields.";

        $message_type = "error";

    } elseif (

        !isset($_FILES['photo']) ||

        $_FILES['photo']['error'] !== UPLOAD_ERR_OK

    ) {

        $message = "Please upload a valid candidate photograph.";

        $message_type = "error";

    } else {

        // Confirm the election and position belong together

        $check = $conn->prepare(

            "SELECT e.election_id, ep.position_id

             FROM elections e

             JOIN election_positions ep

                 ON ep.election_id = e.election_id

             WHERE e.election_id = ?

             AND ep.position_id = ?

             AND e.status != 'completed'"

        );

        $check->bind_param(

            "ii",

            $election_id,

            $position_id

        );

        $check->execute();

        $valid_position = $check->get_result();

        if ($valid_position->num_rows === 0) {

            $message = "Invalid election or position selected.";

            $message_type = "error";

        } else {

            // Check whether the student has already applied

            $existing = $conn->prepare(

                "SELECT application_id

                 FROM candidate_applications

                 WHERE user_id = ? AND election_id = ?"

            );

            $existing->bind_param(

                "ii",

                $user_id,

                $election_id

            );

            $existing->execute();

            if ($existing->get_result()->num_rows > 0) {

                $message = "You have already submitted an application for this election.";

                $message_type = "error";

            } else {

                // Validate image size and type

                $max_size = 2 * 1024 * 1024;

                $file_size = $_FILES['photo']['size'];

                $allowed_types = [

                    'image/jpeg' => 'jpg',

                    'image/png' => 'png',

                    'image/webp' => 'webp'

                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime_type = $finfo->file(

                    $_FILES['photo']['tmp_name']

                );

                if (

                    $file_size > $max_size ||

                    !isset($allowed_types[$mime_type])

                ) {

                    $message = "Photo must be JPG, PNG or WEBP and not exceed 2MB.";

                    $message_type = "error";

                } else {

                    $upload_dir = __DIR__ . "/images/candidate_applications/";

                    if (!is_dir($upload_dir)) {

                        mkdir($upload_dir, 0755, true);

                    }

                    $extension = $allowed_types[$mime_type];

                    $photo_name = bin2hex(random_bytes(16))

                                . "." . $extension;

                    $photo_path = $upload_dir . $photo_name;

                    if (

                        move_uploaded_file(

                            $_FILES['photo']['tmp_name'],

                            $photo_path

                        )

                    ) {

                        $stmt = $conn->prepare(

                            "INSERT INTO candidate_applications

                             (user_id, election_id, position_id,

                              full_name, description, manifesto, photo)

                             SELECT ?, ?, ?, full_name, ?, ?, ?

                             FROM users

                             WHERE user_id = ?"

                        );

                        $stmt->bind_param(

                            "iiisssi",

                            $user_id,

                            $election_id,

                            $position_id,

                            $description,

                            $manifesto,

                            $photo_name,

                            $user_id

                        );

                        if ($stmt->execute()) {

                            // Store success notification

                            $_SESSION['application_success'] =

                                "Your candidate application has been submitted successfully! Your application is now awaiting admin approval.";

                            // Redirect to student dashboard

                            header("Location: dashboard.php?application=submitted");

                            exit();

                        } else {

                            if ($stmt->errno == 1062) {

                                $message = "You have already applied for this election.";

                            } else {

                                $message = "Unable to submit your application. Please try again.";

                            }

                            $message_type = "error";

                            if (file_exists($photo_path)) {

                                unlink($photo_path);

                            }

                        }

                        $stmt->close();

                    } else {

                        $message = "Photo upload failed. Please try again.";

                        $message_type = "error";

                    }

                }

            }

            $existing->close();

        }

        $check->close();

    }

}

// Fetch elections available for applications

$elections = $conn->query(

    "SELECT election_id, title

     FROM elections

     WHERE status != 'completed'

     ORDER BY election_id DESC"

);

// Get the logged-in student's name

$user_query = $conn->prepare(

    "SELECT full_name FROM users WHERE user_id = ?"

);

$user_query->bind_param("i", $user_id);

$user_query->execute();

$user_result = $user_query->get_result();

$student = $user_result->fetch_assoc();

$student_name = $student['full_name'] ?? 'Student';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply as Candidate | VoteHub</title>
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

    max-width: 750px;

    margin: auto;

    background: white;

    padding: 30px;

    border-radius: 15px;

    box-shadow: 0 5px 20px rgba(0,0,0,0.08);

}

h1 {

    color: #173b70;

}

.form-group {

    margin-bottom: 20px;

}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;

}

input, select, textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ccd5e2;

    border-radius: 7px;

    font-size: 15px;

}

textarea {

    min-height: 120px;

    resize: vertical;

}

button {

    background: #2457a6;

    color: white;

    padding: 13px 20px;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    font-size: 15px;

}

button:hover {

    background: #173b70;

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

.back-link {

    display: inline-block;

    margin-bottom: 20px;

    color: #2457a6;

    text-decoration: none;

    font-weight: bold;

}

.note {

    background: #eef4ff;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;

    color: #173b70;

}
</style>
</head>
<body>
<div class="container">
<a href="dashboard.php" class="back-link">

    ← Back to Dashboard
</a>
<h1>Candidate Application</h1>
<p>

    Welcome, <?php echo htmlspecialchars($student_name); ?>!
</p>
<div class="note">

    You may apply for only one position per election.

    Your application will be reviewed by an administrator.
</div>
<?php if ($message !== ""): ?>
<div class="message <?php echo $message_type; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input

    type="hidden"

    name="csrf_token"

    value="<?php echo htmlspecialchars($_SESSION['application_csrf']); ?>"
>
<div class="form-group">
<label>Select Election</label>
<select name="election_id" id="election_id" required>
<option value="">-- Choose Election --</option>
<?php while ($election = $elections->fetch_assoc()): ?>
<option value="<?php echo $election['election_id']; ?>">
<?php echo htmlspecialchars($election['title']); ?>
</option>
<?php endwhile; ?>
</select>
</div>
<div class="form-group">
<label>Select Position</label>
<select name="position_id" id="position_id" required disabled>
<option value="">-- Select an Election First --</option>
</select>
</div>
<div class="form-group">
<label>Candidate Description</label>
<textarea

    name="description"

    placeholder="Introduce yourself..."

    required
></textarea>
</div>
<div class="form-group">
<label>Your Manifesto</label>
<textarea

    name="manifesto"

    placeholder="Explain your plans and what you hope to achieve if elected..."

    required
></textarea>
</div>
<div class="form-group">
<label>Candidate Photograph</label>
<input

    type="file"

    name="photo"

    accept=".jpg,.jpeg,.png,.webp"

    required
>
<small>JPG, PNG or WEBP. Maximum size: 2MB.</small>
</div>
<button type="submit">

    Submit Application
</button>
</form>
</div>
<script>

const electionSelect = document.getElementById("election_id");

const positionSelect = document.getElementById("position_id");

electionSelect.addEventListener("change", function () {

    const electionId = this.value;

    positionSelect.innerHTML =

        '<option value="">Loading positions...</option>';

    positionSelect.disabled = true;

    if (!electionId) {

        positionSelect.innerHTML =

            '<option value="">-- Select an Election First --</option>';

        return;

    }

    fetch("get_positions.php?election_id=" + encodeURIComponent(electionId))

        .then(response => {

            if (!response.ok) {

                throw new Error("Unable to load positions.");

            }

            return response.json();

        })

        .then(data => {

            positionSelect.innerHTML =

                '<option value="">-- Choose Position --</option>';

            if (data.length === 0) {

                positionSelect.innerHTML =

                    '<option value="">No positions available</option>';

                return;

            }

            data.forEach(position => {

                const option = document.createElement("option");

                option.value = position.position_id;

                option.textContent = position.position_name;

                positionSelect.appendChild(option);

            });

            positionSelect.disabled = false;

        })

        .catch(error => {

            positionSelect.innerHTML =

                '<option value="">Unable to load positions</option>';

            console.error(error);

        });

});
</script>
</body>
</html>
 