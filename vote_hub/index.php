<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VoteHub - Online Voting Platform</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<!-- Navigation Bar -->
<header>
<nav>
<h1>VoteHub</h1>
<ul>
<li><a href="index.php">Home</a></li>
<li><a href="#about">About</a></li>
<li><a href="login.php">Student Login</a></li>
<li><a href="register.php">Register</a></li>
<li><a href="apply_candidate.php">Candidate Registration</a></li>
<li><a href="admin/login.php">Admin Login</a></li>
</ul>
</nav>
</header>
<!-- Hero Section -->
<section class="hero">
<h2>Welcome to VoteHub</h2>
<p>

            Your voice matters. Vote for your favourite candidates

            through a simple, secure and reliable online voting platform.
</p>
<a href="register.php" class="btn">Get Started</a>
<a href="login.php" class="btn secondary">Student Login</a>
<a href="apply_candidate.php" class="btn">Register as Candidate</a>
</section>
<!-- About Section -->
<section id="about" class="about">
<h2>About VoteHub</h2>
<p>

            VoteHub is an online voting platform designed to make

            elections easier, faster and more accessible. It allows

            registered users to participate in elections, select their

            preferred candidates and view election results.
</p>
</section>
<!-- Footer -->
<footer>
<p>
&copy; <?php echo date("Y"); ?> VoteHub. All Rights Reserved.
</p>
</footer>
</body>
</html>
 