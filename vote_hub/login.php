<?php

session_start();

require_once 'includes/db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);

    $password = $_POST['password'];

    if (empty($email) || empty($password)) {

        $message = "Please fill in all fields.";

    } else {

        $stmt = $conn->prepare(

            "SELECT user_id, full_name, email, password

            FROM users WHERE email = ?"

        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['user_id'];

                $_SESSION['full_name'] = $user['full_name'];

                $_SESSION['email'] = $user['email'];

                header("Location: dashboard.php");

                exit();

            } else {

                $message = "Incorrect email or password.";

            }

        } else {

            $message = "Incorrect email or password.";

        }

        $stmt->close();

    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | VoteHub</title>
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

        label {

            font-weight: bold;

        }

        input {

            width: 100%;

            padding: 12px;

            margin: 8px 0 15px;

            border: 1px solid #ccc;

            border-radius: 5px;

            box-sizing: border-box;

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

            background: #f8d7da;

            color: #721c24;

            padding: 10px;

            text-align: center;

            border-radius: 5px;

            margin-bottom: 15px;

        }
</style>
</head>
<body>
<div class="container">
<h2>Welcome Back!</h2>
<p>Login to your VoteHub account</p>
<?php if (!empty($message)): ?>
<div class="message">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<form method="POST" action="">
<label>Email Address</label>
<input type="email" name="email" required>
<label>Password</label>
<input type="password" name="password" required>
<button type="submit">Login</button>
</form>
<p>Don't have an account? 
<a href="register.php">Register here</a>
</p>
</div>
</body>
</html>
 