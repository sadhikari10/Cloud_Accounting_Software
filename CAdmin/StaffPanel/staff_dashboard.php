<?php
session_start();

// Check if staff is logged in
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'staff') {
    header("Location: ../login.php");
    exit;
}

$staffName = $_SESSION['UserName'];
$companyId = $_SESSION['CompanyID'];
$permissions = $_SESSION['Permissions'] ?? []; // Load permissions from session
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard</title>
<link rel="stylesheet" href="staff_style.css">
<style>
.dashboard-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.dashboard-actions { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
.btn { padding: 10px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
.logout-btn { background-color: #f44336; }
.logout-btn:hover { background-color: #c62828; }
</style>
</head>
<body>

<div class="dashboard-container">
    <h1>Staff Dashboard</h1>
    <p>Welcome, <strong><?php echo htmlspecialchars($staffName); ?></strong>!</p>

    <div class="dashboard-actions">
        <?php if (!empty($permissions['customers']['create'])): ?>
            <a href="../Common/add_customer.php" class="btn">Add New Customer</a>
        <?php endif; ?>

        <?php if (!empty($permissions['customers']['view'])): ?>
            <a href="../Common/view_customers.php" class="btn">View Customers</a>
        <?php endif; ?>

        <?php if (!empty($permissions['customers']['edit'])): ?>
            <a href="../Common/edit_customer.php" class="btn">Edit Customer</a>
        <?php endif; ?>

        <?php if (!empty($permissions['customers']['delete'])): ?>
            <a href="../Common/delete_customer.php" class="btn">Delete Customer</a>
        <?php endif; ?>

        <?php if (!empty($permissions['inventory']['view'])): ?>
            <a href="../Common/inventory.php" class="btn">View Inventory</a>
        <?php endif; ?>

        <!-- Add more modules here following the same logic -->
    </div>

    <a href="../logout.php" class="btn logout-btn">Logout</a>
</div>

</body>
</html>
