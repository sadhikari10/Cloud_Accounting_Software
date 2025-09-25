<?php
session_start();

// Check if user is logged in (Admin or Staff)
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: login.php");
    exit;
}

// Determine user name and role
if (isset($_SESSION['CAdminID'])) {
    $userName = $_SESSION['CAdminName'];
    $role = $_SESSION['Role'] ?? 'Admin';
} else {
    $userName = $_SESSION['UserName'];
    $role = $_SESSION['Role'] ?? 'Staff';
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
    <div class="dashboard-container">
        <h1>Dashboard</h1>
        <p>Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong>! (<?php echo htmlspecialchars($role); ?>)</p>

        <?php if ($role === 'Admin'): ?>
            <!-- Add User Button -->
            <a href="add_user.php" class="btn">Add User</a>

            <!-- View Users Button -->
            <a href="view_users.php" class="btn">View Users</a>
        <?php else: ?>
            <p>You are logged in as Staff.</p>
        <?php endif; ?>

        <!-- Logout -->
        <a href="../logout.php" class="btn logout-btn">Logout</a>
    </div>
</body>
</html>
