<?php

require_once 'includes/db.php';

$message = "";

$message_type = "";

$redirect_to_login = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);

    $student_id = trim($_POST['student_id']);

    $email = trim($_POST['email']);

    $password = $_POST['password'];

    $confirm_password = $_POST['confirm_password'];

    // Check required fields

    if (empty($full_name) || empty($email) || empty($password)) {

        $message = "Please fill in all required fields.";

        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

        $message_type = "error";

    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters long.";

        $message_type = "error";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

        $message_type = "error";

    } else {

        // Check if email already exists

        $check_email = $conn->prepare(

            "SELECT user_id FROM users WHERE email = ?"

        );

        $check_email->bind_param("s", $email);

        $check_email->execute();

        $check_email->store_result();

        if ($check_email->num_rows > 0) {

            $message = "This email is already registered.";

            $message_type = "error";

        } else {

            if ($student_id === "") {

                $student_id = null;

            }

            // Check student ID if provided

            if ($student_id !== null) {

                $check_student = $conn->prepare(

                    "SELECT user_id FROM users WHERE student_id = ?"

                );

                $check_student->bind_param("s", $student_id);

                $check_student->execute();

                $check_student->store_result();

                if ($check_student->num_rows > 0) {

                    $message = "This student ID is already registered.";

                    $message_type = "error";

                }

                $check_student->close();

            }

            // Register user if there are no errors

            if ($message_type !== "error") {

                $hashed_password = password_hash(

                    $password,

                    PASSWORD_DEFAULT

                );

                $stmt = $conn->prepare(

                    "INSERT INTO users

                    (student_id, full_name, email, password)

                    VALUES (?, ?, ?, ?)"

                );

                $stmt->bind_param(

                    "ssss",

                    $student_id,

                    $full_name,

                    $email,

                    $hashed_password

                );

                if ($stmt->execute()) {

                    $message = "Registration successful! Redirecting you to the login page...";

                    $message_type = "success";

                    $redirect_to_login = true;

                } else {

                    $message = "Registration failed. Please try again.";

                    $message_type = "error";

                }

                $stmt->close();

            }

        }

        $check_email->close();

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | VoteHub</title>
<style>

body {

    font-family: Arial, sans-serif;

    background: #f1f4f9;

    display: flex;

    justify-content: center;

    align-items: center;

    min-height: 100vh;

    margin: 0;

}

.container {

    background: white;

    width: 350px;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 4px 15px rgba(0,0,0,0.1);

}

h2 {

    text-align: center;

    color: #243b64;

}

p {

    text-align: center;

    color: #666;

}

input {

    width: 100%;

    padding: 12px;

    margin: 8px 0 15px;

    border: 1px solid #ccc;

    border-radius: 5px;

    box-sizing: border-box;

}

label {

    font-weight: bold;

}

button {

    width: 100%;

    padding: 12px;

    background: #243b64;

    color: white;

    border: none;

    border-radius: 5px;

    cursor: pointer;

    font-size: 16px;

}

button:hover {

    background: #172a49;

}

.message {

    text-align: center;

    padding: 10px;

    margin-bottom: 15px;

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
</style>
</head>
<body>
<div class="container">
<h2>Create Account</h2>
<p>Join VoteHub today!</p>
<?php if (!empty($message)): ?>
<div class="message <?php echo $message_type; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<form method="POST" action="">

Full Name
<input type="text" name="full_name" required>

Student ID (Optional)
<input type="text" name="student_id">

Email Address
<input type="email" name="email" required>

Password
<input type="password" name="password" required>

Confirm Password
<input type="password" name="confirm_password" required>

Register
</form>
</div>
<?php if ($redirect_to_login): ?>
<script>
// Automatically redirect to login page after 2 seconds

setTimeout(function() {

    window.location.replace("login.php?registered=1");

}, 2000);
</script>
<?php endif; ?>
</body>
</html>
 