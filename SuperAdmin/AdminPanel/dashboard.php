<?php
session_start();
if (!isset($_SESSION['SuperAdminID'])) {
    header("Location: login.php");
    exit;
}

$adminName = $_SESSION['SuperAdminName'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin Dashboard</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="dashboard-page">
    <main>
        <div class="dashboard-container">
            <h2>Welcome, <?php echo htmlspecialchars($adminName); ?>!</h2>

            <div class="dashboard-buttons">
                <form action="../logout.php" method="POST" style="display:inline;">
                    <button type="submit" class="dashboard-btn">Logout</button>
                </form>

                <form action="login_history.php" method="GET" style="display:inline;">
                    <button type="submit" class="dashboard-btn">See Previous Logins</button>
                </form>

                <form action="system_users.php" method="GET" style="display:inline;">
                    <button type="submit" class="dashboard-btn">Accounts Details</button>
                </form>
            </div>
        </div>
    </main>
    <?php 
        include '../../Common/footer.php';
    ?>
</body>
</html>
