<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

// Protect the page

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

$message = "";

$message_type = "";

// Generate security token

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (

        !isset($_POST['csrf_token']) ||

        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])

    ) {

        $message = "Invalid request. Please refresh the page.";

        $message_type = "error";

    } else {

        $title = trim($_POST['title'] ?? '');

        $description = trim($_POST['description'] ?? '');

        $start_input = $_POST['start_date'] ?? '';

        $end_input = $_POST['end_date'] ?? '';

        $category_names = $_POST['position_name'] ?? [];

        $category_descriptions = $_POST['position_description'] ?? [];

        // Validate category arrays

        if (!is_array($category_names) || !is_array($category_descriptions)) {

            $category_names = [];

            $category_descriptions = [];

        }

        $positions = [];

        $seen_positions = [];

        foreach ($category_names as $index => $name) {

            $name = trim($name);

            $position_description = trim(

                $category_descriptions[$index] ?? ''

            );

            // Ignore completely empty category rows

            if ($name === '' && $position_description === '') {

                continue;

            }

            if ($name === '') {

                $message = "Please enter a name for every category.";

                $message_type = "error";

                break;

            }

            if (strlen($name) > 100) {

                $message = "Category names cannot exceed 100 characters.";

                $message_type = "error";

                break;

            }

            // Prevent duplicate category names

            $normalized_name = strtolower($name);

            if (in_array($normalized_name, $seen_positions, true)) {

                $message = "You cannot add the same category twice.";

                $message_type = "error";

                break;

            }

            $seen_positions[] = $normalized_name;

            $positions[] = [

                'name' => $name,

                'description' => $position_description

            ];

        }

        if ($message === "") {

            if (

                empty($title) ||

                empty($start_input) ||

                empty($end_input)

            ) {

                $message = "Please fill in all required election fields.";

                $message_type = "error";

            } elseif (empty($positions)) {

                $message = "Please add at least one election category.";

                $message_type = "error";

            } else {

                // Validate dates

                $start = DateTime::createFromFormat(

                    'Y-m-d\TH:i',

                    $start_input

                );

                $end = DateTime::createFromFormat(

                    'Y-m-d\TH:i',

                    $end_input

                );

                if (

                    !$start ||

                    !$end ||

                    $start->format('Y-m-d\TH:i') !== $start_input ||

                    $end->format('Y-m-d\TH:i') !== $end_input

                ) {

                    $message = "Please enter valid election dates.";

                    $message_type = "error";

                } elseif ($end <= $start) {

                    $message = "The ending date must be after the starting date.";

                    $message_type = "error";

                } else {

                    $start_date = $start->format('Y-m-d H:i:s');

                    $end_date = $end->format('Y-m-d H:i:s');

                    // Determine election status

                    $now = new DateTime();

                    if ($now < $start) {

                        $status = "upcoming";

                    } elseif ($now >= $end) {

                        $status = "completed";

                    } else {

                        $status = "ongoing";

                    }

                    $admin_id = (int) $_SESSION['admin_id'];

                    try {

                        // Start database transaction

                        $conn->begin_transaction();

                        // Create election

                        $stmt = $conn->prepare(

                            "INSERT INTO elections

                            (title, description, start_date, end_date, status, created_by)

                            VALUES (?, ?, ?, ?, ?, ?)"

                        );

                        $stmt->bind_param(

                            "sssssi",

                            $title,

                            $description,

                            $start_date,

                            $end_date,

                            $status,

                            $admin_id

                        );

                        if (!$stmt->execute()) {

                            throw new Exception("Election creation failed.");

                        }

                        // Get newly created election ID

                        $election_id = $conn->insert_id;

                        $stmt->close();

                        // Insert categories

                        $position_stmt = $conn->prepare(

                            "INSERT INTO election_positions

                            (election_id, position_name, description)

                            VALUES (?, ?, ?)"

                        );

                        foreach ($positions as $position) {

                            $position_name = $position['name'];

                            $position_description = $position['description'];

                            $position_stmt->bind_param(

                                "iss",

                                $election_id,

                                $position_name,

                                $position_description

                            );

                            if (!$position_stmt->execute()) {

                                throw new Exception("Category creation failed.");

                            }

                        }

                        $position_stmt->close();

                        // Save everything together

                        $conn->commit();

                        $message = "Election and all categories created successfully!";

                        $message_type = "success";

                    } catch (Throwable $e) {

                        // Undo incomplete database changes

                        $conn->rollback();

                        $message = "Unable to create election. Please check your categories and try again.";

                        $message_type = "error";

                    }

                }

            }

        }

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Election - VoteHub</title>
<style>

* {

    box-sizing: border-box;

}

body {

    font-family: Arial, sans-serif;

    background: #f2f4f8;

    margin: 0;

}

header {

    background: #174ea6;

    color: white;

    padding: 20px;

}

.container {

    width: 600px;

    max-width: 90%;

    background: white;

    margin: 40px auto;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

}

h2 {

    text-align: center;

}

label {

    display: block;

    margin-top: 15px;

    font-weight: bold;

}

input, textarea {

    width: 100%;

    padding: 12px;

    margin-top: 8px;

    border: 1px solid #ccc;

    border-radius: 5px;

}

textarea {

    height: 100px;

    resize: vertical;

}

button {

    padding: 13px;

    margin-top: 15px;

    background: #174ea6;

    color: white;

    border: none;

    border-radius: 5px;

    cursor: pointer;

}

button:hover {

    background: #123d82;

}

.submit-btn {

    width: 100%;

    margin-top: 25px;

}

.add-btn {

    background: #198754;

}

.add-btn:hover {

    background: #146c43;

}

.remove-btn {

    background: #dc3545;

    width: 100%;

}

.remove-btn:hover {

    background: #bb2d3b;

}

.category-box {

    border: 1px solid #d5deeb;

    padding: 15px;

    margin-top: 15px;

    border-radius: 8px;

    background: #f8faff;

}

.category-heading {

    font-weight: bold;

    color: #174ea6;

}

.section-heading {

    margin-top: 30px;

    color: #174ea6;

    border-bottom: 1px solid #ddd;

    padding-bottom: 10px;

}

.message {

    text-align: center;

    padding: 12px;

    border-radius: 5px;

}

.success {

    background: #d4edda;

    color: #155724;

}

.error {

    background: #f8d7da;

    color: #721c24;

}

.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    text-decoration: none;

    color: #174ea6;

}
</style>
</head>
<body>
<header>
<h1>VoteHub Administration</h1>
</header>
<div class="container">
<h2>Create New Election</h2>
<?php if (!empty($message)): ?>
<p class="message <?php echo $message_type; ?>">
<?php echo htmlspecialchars($message); ?>
</p>
<?php endif; ?>
<form method="POST" action="">
<input

    type="hidden"

    name="csrf_token"

    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
>
<h3 class="section-heading">Election Details</h3>
<label>Election Title</label>
<input

    type="text"

    name="title"

    placeholder="Enter election title"

    maxlength="150"

    required
>
<label>Election Description</label>
<textarea

    name="description"

    placeholder="Describe the election"
></textarea>
<label>Starting Date and Time</label>
<input

    type="datetime-local"

    name="start_date"

    required
>
<label>Ending Date and Time</label>
<input

    type="datetime-local"

    name="end_date"

    required
>
<h3 class="section-heading">Election Categories</h3>
<p>Add the positions students can apply for in this election.</p>
<div id="categories-container">
<div class="category-box">
<div class="category-heading">Category 1</div>
<label>Category Name</label>
<input

            type="text"

            name="position_name[]"

            placeholder="e.g. Most Elegant"

            maxlength="100"

            required
>
<label>Category Description</label>
<textarea

            name="position_description[]"

            placeholder="Describe this category"
></textarea>
</div>
</div>
<button

    type="button"

    class="add-btn"

    onclick="addCategory()"
>

    + Add Another Category
</button>
<button type="submit" class="submit-btn">

    Create Election
</button>
</form>
<a href="dashboard.php" class="back">

    Back to Admin Dashboard
</a>
</div>
<script>

let categoryCount = 1;

function addCategory() {

    categoryCount++;

    const container = document.getElementById("categories-container");

    const category = document.createElement("div");

    category.className = "category-box";

    category.innerHTML = `
<div class="category-heading">

            Category ${categoryCount}
</div>
<label>Category Name</label>
<input

            type="text"

            name="position_name[]"

            placeholder="Enter category name"

            maxlength="100"

            required
>
<label>Category Description</label>
<textarea

            name="position_description[]"

            placeholder="Describe this category"
></textarea>
<button

            type="button"

            class="remove-btn"

            onclick="removeCategory(this)"
>

            Remove Category
</button>

    `;

    container.appendChild(category);

}

function removeCategory(button) {

    button.closest(".category-box").remove();

    updateCategoryNumbers();

}

function updateCategoryNumbers() {

    const categories = document.querySelectorAll(".category-box");

    categoryCount = categories.length;

    categories.forEach((category, index) => {

        category.querySelector(".category-heading").textContent =

            "Category " + (index + 1);

    });

}
</script>
</body>
</html>
 