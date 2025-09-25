<?php
session_start();

// Optional: check if user is logged in
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
<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .logout-btn {
        display: inline-block;
        padding: 10px 20px;
        background-color: #d9534f;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 20px;
    }
    .logout-btn:hover {
        background-color: #c9302c;
    }
</style>
</head>
<body>

<h1>Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['UserName']); ?>!</p>

<a href="../logout.php" class="logout-btn">Logout</a>

</body>
</html>
