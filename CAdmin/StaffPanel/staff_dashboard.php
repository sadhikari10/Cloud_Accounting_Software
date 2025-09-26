<?php
session_start();

// Check if staff is logged in
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'staff') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard</title>
<link rel="stylesheet" href="staff_style.css">
</head>
<body>

<h1>Staff Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['UserName']); ?>!</p>

<a href="../logout.php" class="btn logout-btn">Logout</a>

</body>
</html>
