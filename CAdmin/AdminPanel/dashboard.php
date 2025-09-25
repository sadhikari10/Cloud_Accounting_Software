<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>
<h1>Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['UserName']); ?>!</p>

<!-- Add User Button -->
<a href="add_user.php" class="btn">Add User</a>

<!-- View Users Button -->
<a href="view_users.php" class="btn">View Users</a>

<!-- Logout -->
<a href="../logout.php" class="btn logout-btn">Logout</a>

</body>
</html>
