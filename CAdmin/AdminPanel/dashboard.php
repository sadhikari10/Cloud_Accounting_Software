<?php
session_start();
require '../../Common/connection.php';

// ------------------ Check if user is logged in ------------------
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

// ------------------ Determine User Info ------------------
if (isset($_SESSION['CAdminID'])) {
    $userName = $_SESSION['CAdminName'];
    $role = $_SESSION['Role'] ?? 'Admin';
    $companyId = $_SESSION['CompanyID'];
} else {
    $userName = $_SESSION['UserName'];
    $role = $_SESSION['Role'] ?? 'Staff';
    $companyId = $_SESSION['CompanyID'];
}

// ------------------ Initialize Stats ------------------
$totalStaff = 0;
$activeStaff = 0;
$inactiveStaff = 0;
$pendingPassword = 0;
$lastLogin = 'No logins yet';

// ------------------ Admin Dashboard Stats ------------------
if ($role === 'Admin') {

    // Total staff
    $stmt = $conn->prepare("SELECT COUNT(*) FROM company_staff WHERE company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($totalStaff);
    $stmt->fetch();
    $stmt->close();

    // Active staff
    $stmt = $conn->prepare("SELECT COUNT(*) FROM company_staff WHERE company_id = ? AND status = 'active'");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($activeStaff);
    $stmt->fetch();
    $stmt->close();

    // Inactive staff
    $inactiveStaff = $totalStaff - $activeStaff;

    // Pending password updates
    $stmt = $conn->prepare("SELECT COUNT(*) FROM company_staff WHERE company_id = ? AND must_change_password = 1");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($pendingPassword);
    $stmt->fetch();
    $stmt->close();

    // ------------------ Last Login (Staff/Admin) ------------------
    $stmt = $conn->prepare("
        SELECT 
            s.first_name, 
            s.last_name, 
            l.login_at,
            CASE 
                WHEN l.staff_id IS NOT NULL THEN 'Staff' 
                ELSE 'Admin' 
            END AS role
        FROM company_user_login_history l
        LEFT JOIN company_staff s ON l.staff_id = s.staff_id
        WHERE l.company_id = ?
        ORDER BY l.login_at DESC
        LIMIT 1
    ");

    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($lastFirstName, $lastLastName, $lastLoginAt, $lastLoginRole);
    if ($stmt->fetch()) {
        $displayName = $lastFirstName ? htmlspecialchars($lastFirstName . ' ' . $lastLastName) : 'Admin';
        $lastLogin = sprintf("%s (%s) at %s", $displayName, htmlspecialchars($lastLoginRole), $lastLoginAt);
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="customer_admin_style.css">
<style>
.dashboard-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.dashboard-stats { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
.stat-card { flex: 1 1 150px; border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #f9f9f9; text-align: center; }
.btn { display: inline-block; margin: 5px; padding: 10px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
.logout-btn { background: #dc3545; }
.logout-btn:hover { background: #a71d2a; }
</style>
</head>
<body>
<div class="dashboard-container">
    <h1>Dashboard</h1>
    <p>Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong>! (<?php echo htmlspecialchars($role); ?>)</p>

    <?php if ($role === 'Admin'): ?>
        <!-- Quick Stats / Overview Panel -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Total Staff</h3>
                <p><?php echo $totalStaff; ?></p>
            </div>
            <div class="stat-card">
                <h3>Active Staff</h3>
                <p><?php echo $activeStaff; ?></p>
            </div>
            <div class="stat-card">
                <h3>Inactive Staff</h3>
                <p><?php echo $inactiveStaff; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pending Password Updates</h3>
                <p><?php echo $pendingPassword; ?></p>
            </div>
            <div class="stat-card">
                <h3>Last Login</h3>
                <p><?php echo htmlspecialchars($lastLogin); ?></p>
            </div>
        </div>

        <!-- Staff Management Button -->
        <a href="staff_management.php" class="btn">Staff Management</a>
        
        <!-- Company Management Button -->
        <a href="company_management.php" class="btn">Company Management</a>

        <!-- Chart Of Accounts -->
        <a href="../Common/chart_of_accounts.php" class="btn">Chart Of Accounts</a>

        <!-- Add customers -->
        <a href="../Common/add_customers.php" class="btn">Add Customers</a>

    <?php else: ?>
        <p>You are logged in as Staff.</p>
    <?php endif; ?>

    <!-- View Admin Login History Button -->
    <a href="admin_login_history.php" class="btn">View Admin Login History</a>

    <!-- Logout -->
    <a href="../logout.php" class="btn logout-btn">Logout</a>
</div>
</body>
</html>
