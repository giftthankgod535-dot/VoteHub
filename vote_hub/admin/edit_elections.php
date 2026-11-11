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

$error = "";

$success = "";

// Get election ID

$election_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($election_id <= 0) {

    die("Invalid election ID.");

}

// Retrieve election details

$stmt = $conn->prepare(

    "SELECT * FROM elections WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$result = $stmt->get_result();

$election = $result->fetch_assoc();

$stmt->close();

if (!$election) {

    die("Election not found.");

}

// Check whether the election has received votes

$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_votes

     FROM votes

     WHERE election_id = ?"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$vote_result = $stmt->get_result();

$vote_data = $vote_result->fetch_assoc();

$stmt->close();

$has_votes = (int) $vote_data['total_votes'] > 0;


// Process form submission

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token

    if (

        !isset($_POST['csrf_token']) ||

        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

    ) {

        die("Invalid request. Please refresh the page and try again.");

    }

    $title = trim($_POST['title'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $start_date = $election['start_date'];

    $end_date = $election['end_date'];

    $status = $election['status'];

    // Validate election information

    if ($title === "") {

        $error = "Election title is required.";

    } elseif (!$has_votes) {

        $start_date = trim($_POST['start_date'] ?? '');

        $end_date = trim($_POST['end_date'] ?? '');

        $start_timestamp = strtotime($start_date);

        $end_timestamp = strtotime($end_date);

        if (

            $start_timestamp === false ||

            $end_timestamp === false ||

            $start_timestamp >= $end_timestamp

        ) {

            $error = "Please enter valid dates. The end date must be after the start date.";

        } else {

            $now = time();

            if ($now < $start_timestamp) {

                $status = "upcoming";

            } elseif ($now <= $end_timestamp) {

                $status = "ongoing";

            } else {

                $status = "completed";

            }

        }

    }

    // Prepare category information

    $position_ids = $_POST['position_id'] ?? [];

    $position_names = $_POST['position_name'] ?? [];

    $position_descriptions = $_POST['position_description'] ?? [];

    $delete_positions = $_POST['delete_position'] ?? [];

    // Check for duplicate category names

    $category_names = [];

    foreach ($position_names as $name) {

        $name = trim($name);

        if ($name !== "") {

            $normalized_name = strtolower($name);

            if (in_array($normalized_name, $category_names, true)) {

                $error = "You cannot have duplicate category names.";

                break;

            }

            $category_names[] = $normalized_name;

        }

    }

    // Validate new category names

    if ($error === "") {

        foreach ($position_names as $name) {

            $name = trim($name);

            if ($name !== "" && strlen($name) > 100) {

                $error = "Category names cannot exceed 100 characters.";

                break;

            }

        }

    }

    // Continue only if validation passed

    if ($error === "") {

        $conn->begin_transaction();

        try {

            // Update election information

            if ($has_votes) {

                $update = $conn->prepare(

                    "UPDATE elections

                     SET title = ?, description = ?

                     WHERE election_id = ?"

                );

                $update->bind_param(

                    "ssi",

                    $title,

                    $description,

                    $election_id

                );

            } else {

                $update = $conn->prepare(

                    "UPDATE elections

                     SET title = ?,

                         description = ?,

                         start_date = ?,

                         end_date = ?,

                         status = ?

                     WHERE election_id = ?"

                );

                $update->bind_param(

                    "sssssi",

                    $title,

                    $description,

                    $start_date,

                    $end_date,

                    $status,

                    $election_id

                );

            }

            if (!$update->execute()) {

                throw new Exception("Unable to update election information.");

            }

            $update->close();


            // Process existing categories

            foreach ($position_ids as $index => $position_id) {

                $position_id = (int) $position_id;

                $name = trim($position_names[$index] ?? '');

                $category_description = trim(

                    $position_descriptions[$index] ?? ''

                );

                if ($position_id <= 0) {

                    continue;

                }

                // Confirm category belongs to this election

                $check = $conn->prepare(

                    "SELECT position_id

                     FROM election_positions

                     WHERE position_id = ? AND election_id = ?"

                );

                $check->bind_param(

                    "ii",

                    $position_id,

                    $election_id

                );

                $check->execute();

                $category_result = $check->get_result();

                $category_exists = $category_result->num_rows > 0;

                $check->close();

                if (!$category_exists) {

                    throw new Exception("An invalid category was submitted.");

                }

                // Check whether applications or candidates use this category

                $reference_check = $conn->prepare(

                    "SELECT

                        (SELECT COUNT(*)

                         FROM candidate_applications

                         WHERE position_id = ?) AS application_count,

                        (SELECT COUNT(*)

                         FROM candidates

                         WHERE position_id = ?) AS candidate_count"

                );

                $reference_check->bind_param(

                    "ii",

                    $position_id,

                    $position_id

                );

                $reference_check->execute();

                $reference_result = $reference_check->get_result();

                $references = $reference_result->fetch_assoc();

                $reference_check->close();

                $application_count = (int) $references['application_count'];

                $candidate_count = (int) $references['candidate_count'];

                $is_delete_requested = in_array(

                    $position_id,

                    array_map('intval', $delete_positions),

                    true

                );

                // Safely delete unused categories

                if ($is_delete_requested) {

                    if (

                        $application_count > 0 ||

                        $candidate_count > 0

                    ) {

                        throw new Exception(

                            "A category cannot be deleted because it already has applications or registered candidates."

                        );

                    }

                    $delete = $conn->prepare(

                        "DELETE FROM election_positions

                         WHERE position_id = ? AND election_id = ?"

                    );

                    $delete->bind_param(

                        "ii",

                        $position_id,

                        $election_id

                    );

                    if (!$delete->execute()) {

                        throw new Exception("Unable to delete category.");

                    }

                    $delete->close();

                    continue;

                }

                if ($name === "") {

                    throw new Exception("Existing category names cannot be left empty.");

                }

                // Do not rename categories already in use

                $old_name_stmt = $conn->prepare(

                    "SELECT position_name

                     FROM election_positions

                     WHERE position_id = ?"

                );

                $old_name_stmt->bind_param("i", $position_id);

                $old_name_stmt->execute();

                $old_name_result = $old_name_stmt->get_result();

                $old_category = $old_name_result->fetch_assoc();

                $old_name_stmt->close();

                if (

                    ($application_count > 0 || $candidate_count > 0) &&

                    $name !== $old_category['position_name']

                ) {

                    throw new Exception(

                        "You cannot rename a category that already has applications or registered candidates. You can still edit its description."

                    );

                }

                // Update category

                $update_category = $conn->prepare(

                    "UPDATE election_positions

                     SET position_name = ?, description = ?

                     WHERE position_id = ? AND election_id = ?"

                );

                $update_category->bind_param(

                    "ssii",

                    $name,

                    $category_description,

                    $position_id,

                    $election_id

                );

                if (!$update_category->execute()) {

                    throw new Exception(

                        "Unable to update category. Check that its name is not duplicated."

                    );

                }

                $update_category->close();

            }


            // Add new categories

            if (isset($_POST['new_position_name'])) {

                $new_names = $_POST['new_position_name'];

                $new_descriptions = $_POST['new_position_description'] ?? [];

                foreach ($new_names as $index => $new_name) {

                    $new_name = trim($new_name);

                    $new_description = trim(

                        $new_descriptions[$index] ?? ''

                    );

                    // Ignore completely blank new rows

                    if ($new_name === "") {

                        continue;

                    }

                    if (strlen($new_name) > 100) {

                        throw new Exception(

                            "Category names cannot exceed 100 characters."

                        );

                    }

                    // Check for duplicate category in this election

                    $duplicate = $conn->prepare(

                        "SELECT position_id

                         FROM election_positions

                         WHERE election_id = ?

                         AND LOWER(position_name) = LOWER(?)"

                    );

                    $duplicate->bind_param(

                        "is",

                        $election_id,

                        $new_name

                    );

                    $duplicate->execute();

                    $duplicate_result = $duplicate->get_result();

                    $is_duplicate = $duplicate_result->num_rows > 0;

                    $duplicate->close();

                    if ($is_duplicate) {

                        throw new Exception(

                            "The category '" . $new_name . "' already exists in this election."

                        );

                    }

                    // Insert new category

                    $insert = $conn->prepare(

                        "INSERT INTO election_positions

                         (election_id, position_name, description)

                         VALUES (?, ?, ?)"

                    );

                    $insert->bind_param(

                        "iss",

                        $election_id,

                        $new_name,

                        $new_description

                    );

                    if (!$insert->execute()) {

                        throw new Exception("Unable to add new category.");

                    }

                    $insert->close();

                }

            }


            // Save all changes

            $conn->commit();

            header("Location: manage_elections.php?updated=1");

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            $error = $e->getMessage();

        }

    }

    // Preserve entered election information

    $election['title'] = $title;

    $election['description'] = $description;

    if (!$has_votes) {

        $election['start_date'] = $start_date;

        $election['end_date'] = $end_date;

    }

}


// Retrieve categories for display

$positions = [];

$stmt = $conn->prepare(

    "SELECT

        ep.position_id,

        ep.position_name,

        ep.description,

        (SELECT COUNT(*)

         FROM candidate_applications ca

         WHERE ca.position_id = ep.position_id) AS application_count,

        (SELECT COUNT(*)

         FROM candidates c

         WHERE c.position_id = ep.position_id) AS candidate_count

     FROM election_positions ep

     WHERE ep.election_id = ?

     ORDER BY ep.position_name ASC"

);

$stmt->bind_param("i", $election_id);

$stmt->execute();

$position_result = $stmt->get_result();

while ($row = $position_result->fetch_assoc()) {

    $positions[] = $row;

}

$stmt->close();

?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Election - VoteHub</title>
<style>

body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    padding: 30px;

}

.container {

    max-width: 750px;

    margin: auto;

    background: white;

    padding: 25px;

    border-radius: 10px;

}

h2, h3 {

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

    margin-top: 15px;

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

.error {

    color: red;

    background: #ffecec;

    padding: 12px;

    border-radius: 5px;

}

.notice {

    background: #fff3cd;

    color: #856404;

    padding: 12px;

    border-radius: 5px;

    margin-top: 15px;

}

.category-box {

    border: 1px solid #ddd;

    padding: 15px;

    border-radius: 7px;

    margin-top: 15px;

    background: #fafafa;

}

.category-box small {

    display: block;

    margin-top: 8px;

    color: #666;

}

.delete-option {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 12px;

    font-weight: normal;

}

.delete-option input {

    width: auto;

    margin: 0;

}

.back {

    display: inline-block;

    margin-top: 20px;

    color: #222;

    text-decoration: none;

}

.section {

    border-top: 1px solid #ddd;

    margin-top: 30px;

    padding-top: 20px;

}
</style>
</head>
<body>
<div class="container">
<h2>Edit Election</h2>
<p>Update the election information and its categories below.</p>

<?php if ($error !== ""): ?>
<p class="error">
<?php echo htmlspecialchars($error); ?>
</p>
<?php endif; ?>

<?php if ($has_votes): ?>
<div class="notice">

    This election has already received votes.

    Its start and end dates cannot be changed.

    Categories already in use cannot be renamed or deleted.
</div>
<?php endif; ?>

<form method="POST">
<input

    type="hidden"

    name="csrf_token"

    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
>

<h3>Election Information</h3>
<label>Election Title</label>
<input

    type="text"

    name="title"

    value="<?php echo htmlspecialchars($election['title']); ?>"

    required
>

<label>Election Description</label>
<textarea

    name="description"

    rows="5"
><?php echo htmlspecialchars($election['description'] ?? ''); ?></textarea>

<label>Start Date</label>
<input

    type="datetime-local"

    name="start_date"

    value="<?php echo date('Y-m-d\TH:i', strtotime($election['start_date'])); ?>"
<?php echo $has_votes ? 'disabled' : ''; ?>
>

<label>End Date</label>
<input

    type="datetime-local"

    name="end_date"

    value="<?php echo date('Y-m-d\TH:i', strtotime($election['end_date'])); ?>"
<?php echo $has_votes ? 'disabled' : ''; ?>
>

<div class="section">
<h3>Election Categories</h3>
<p>

    Manage the positions or awards available in this election.
</p>

<?php if (count($positions) > 0): ?>
<?php foreach ($positions as $position): ?>
<div class="category-box">
<input

            type="hidden"

            name="position_id[]"

            value="<?php echo (int) $position['position_id']; ?>"
>
<label>Category Name</label>
<input

            type="text"

            name="position_name[]"

            value="<?php echo htmlspecialchars($position['position_name']); ?>"

            required
>
<label>Category Description</label>
<textarea

            name="position_description[]"

            rows="2"
><?php echo htmlspecialchars($position['description'] ?? ''); ?></textarea>
<small>

            Applications: <?php echo (int) $position['application_count']; ?>

            |

            Registered candidates: <?php echo (int) $position['candidate_count']; ?>
</small>
<?php if (

            (int) $position['application_count'] === 0 &&

            (int) $position['candidate_count'] === 0

        ): ?>
<label class="delete-option">
<input

                type="checkbox"

                name="delete_position[]"

                value="<?php echo (int) $position['position_id']; ?>"
>

            Delete this category
</label>
<?php else: ?>
<small>

            This category cannot be deleted because it is already in use.
</small>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<p>No categories have been added to this election yet.</p>
<?php endif; ?>

<h3>Add New Categories</h3>
<div id="newCategories">
<div class="category-box">
<label>New Category Name</label>
<input

            type="text"

            name="new_position_name[]"

            placeholder="e.g. Best Dressed"
>
<label>Category Description</label>
<textarea

            name="new_position_description[]"

            rows="2"

            placeholder="Enter category description"
></textarea>
</div>
</div>

<button type="button" onclick="addCategory()">

    + Add Another Category
</button>
</div>

<button type="submit">

    Save Changes
</button>
</form>

<a href="manage_elections.php" class="back">

    Back to Manage Elections
</a>
</div>

<script>

function addCategory() {

    const container = document.getElementById("newCategories");

    const category = document.createElement("div");

    category.className = "category-box";

    category.innerHTML = `
<label>New Category Name</label>
<input

            type="text"

            name="new_position_name[]"

            placeholder="e.g. Most Influential Student"
>
<label>Category Description</label>
<textarea

            name="new_position_description[]"

            rows="2"

            placeholder="Enter category description"
></textarea>
<button type="button" onclick="this.parentElement.remove()">

            Remove Row
</button>

    `;

    container.appendChild(category);

}
</script>
</body>
</html>
 