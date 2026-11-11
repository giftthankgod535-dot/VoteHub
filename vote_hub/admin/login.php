<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

$error = "";

// Redirect an already logged-in administrator

if (isset($_SESSION['admin_id'])) {

    header("Location: dashboard.php");

    exit();

}

// Handle login form submission

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);

    $password = $_POST['password'];

    // Find administrator using email

    $stmt = $conn->prepare(

        "SELECT admin_id, full_name, email, password

         FROM admins

         WHERE email = ?"

    );

    $stmt->bind_param("s", $email);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $admin = $result->fetch_assoc();

        // Verify password

        if (password_verify($password, $admin['password'])) {

            // Store administrator details in session

            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['admin_id'];

            $_SESSION['admin_name'] = $admin['full_name'];

            $_SESSION['admin_email'] = $admin['email'];

            // Redirect to ADMIN dashboard

            header("Location: dashboard.php");

            exit();

        } else {

            $error = "Invalid email or password.";

        }

    } else {

        $error = "Invalid email or password.";

    }

    $stmt->close();

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - VoteHub</title>
<style>

        body {

            font-family: Arial, sans-serif;

            background: #f2f4f8;

            display: flex;

            justify-content: center;

            align-items: center;

            min-height: 100vh;

            margin: 0;

        }

        .login-container {

            background: white;

            width: 350px;

            padding: 30px;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.1);

        }

        h2 {

            text-align: center;

            color: #222;

        }

        p {

            text-align: center;

            color: #666;

        }

        input {

            width: 100%;

            padding: 12px;

            margin: 10px 0;

            box-sizing: border-box;

            border: 1px solid #ccc;

            border-radius: 5px;

        }

        button {

            width: 100%;

            padding: 12px;

            background: #174ea6;

            color: white;

            border: none;

            border-radius: 5px;

            cursor: pointer;

        }

        button:hover {

            background: #123d82;

        }

        .error {

            color: red;

            text-align: center;

        }
</style>
</head>
<body>
<div class="login-container">
<h2>VoteHub Admin</h2>
<p>Sign in to manage elections</p>
<?php if (!empty($error)): ?>
<p class="error">
<?php echo htmlspecialchars($error); ?>
</p>
<?php endif; ?>
<form method="POST" action="">
<input

                type="email"

                name="email"

                placeholder="Admin email"

                required
>
<input

                type="password"

                name="password"

                placeholder="Password"

                required
>
<button type="submit">Login</button>
</form>
</div>
</body>
</html>
 