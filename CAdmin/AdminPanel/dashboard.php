<?php
session_start();
require '../../Common/connection.php';

// Check if user is logged in (Admin or Staff)
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

// Determine user name, role, and company
if (isset($_SESSION['CAdminID'])) {
    $userName = $_SESSION['CAdminName'];
    $role = $_SESSION['Role'] ?? 'Admin';
    $companyId = $_SESSION['CompanyID'];
} else {
    $userName = $_SESSION['UserName'];
    $role = $_SESSION['Role'] ?? 'Staff';
    $companyId = $_SESSION['CompanyID'];
}

// Initialize Quick Stats variables
$totalStaff = 0;
$activeStaff = 0;
$inactiveStaff = 0;
$pendingPassword = 0;
$lastLogin = 'N/A';

if ($role === 'Admin') {
    // Total staff in company
    $stmt = $conn->prepare("SELECT COUNT(*) FROM company_staff WHERE company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($totalStaff);
    $stmt->fetch();
    $stmt->close();

    // Active staff
    $stmt = $conn->prepare("SELECT COUNT(*) FROM company_staff WHERE company_id = ? AND status = 'Active'");
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

    // Last login details (latest login)

    $lastLogin = 'No logins yet';
    $stmt = $conn->prepare("
        SELECT first_name, last_name, last_login_at
        FROM company_staff
        WHERE company_id = ?
        AND last_login_at IS NOT NULL
        ORDER BY last_login_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stmt->bind_result($lastFirstName, $lastLastName, $lastLoginAt);
    if ($stmt->fetch()) {
        $lastLogin = sprintf(
            "%s %s at %s",
            htmlspecialchars($lastFirstName),
            htmlspecialchars($lastLastName),
            $lastLoginAt
        );
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
