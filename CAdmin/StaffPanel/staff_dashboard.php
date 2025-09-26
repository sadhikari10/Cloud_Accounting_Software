<?php
session_start();

// Check if staff is logged in
if (!isset($_SESSION['UserID']) || strtolower($_SESSION['Role']) !== 'staff') {
    header("Location: ../login.php");
    exit;
}

$staffName = $_SESSION['UserName'];
$companyId = $_SESSION['CompanyID']; // in case you need it for pre-filling forms
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

<div class="dashboard-container">
    <h1>Staff Dashboard</h1>
    <p>Welcome, <strong><?php echo htmlspecialchars($staffName); ?></strong>!</p>

    <div class="dashboard-actions">
        <!-- Add New Customer -->
        <a href="../Common/add_customer.php" class="btn">Add New Customer</a>

        <!-- Add New Organization -->
        <a href="../Common/add_customer_company.php" class="btn">Add New Organization</a>

        <!-- You can add more buttons here for sales, purchase, inventory, etc. -->
    </div>

    <!-- Optional: Quick Stats Panel for Staff -->
    <div class="quick-stats">
        <!-- Placeholder, can later show pending sales, tasks, etc. -->
    </div>

    <a href="../logout.php" class="btn logout-btn" style="background-color:#f44336;">Logout</a>
</div>

</body>
</html>
