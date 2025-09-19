<?php
session_start();

// Check if staff is logged in
if (!isset($_SESSION['SuperAdminID']) || !isset($_SESSION['Role']) || strtolower($_SESSION['Role']) !== 'staff') {
    header("Location: ../login.php");
    exit;
}

// Get the staff name from session
$staffName = $_SESSION['SuperAdminName'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-page">
<main>
    <div class="dashboard-container">
        <h2>Welcome, <?php echo htmlspecialchars($staffName); ?>!</h2>
        <p>Good day to you.</p>

        <div class="dashboard-buttons" style="margin-top:20px;">
            <!-- Logout Form -->
            <form action="../logout.php" method="POST" style="display:inline;">
                <button type="submit" class="dashboard-btn">Logout</button>
            </form>
        </div>
    </div>
</main>

<?php include '../../Common/footer.php'; ?>
</body>
</html>
