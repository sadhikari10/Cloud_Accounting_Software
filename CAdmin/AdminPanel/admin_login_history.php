<?php
session_start();
require '../../Common/connection.php';

// Only allow CAdmin (company admin) to access this page
if (!isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'];
$adminId = $_SESSION['CAdminID']; // The logged-in admin

// Fetch login history for this admin
$stmt = $conn->prepare("
    SELECT login_at, ip_address, user_agent
    FROM company_user_login_history
    WHERE company_id = ? AND admin_id = ?
    ORDER BY login_at DESC
");
$stmt->bind_param("ii", $companyId, $adminId);
$stmt->execute();
$result = $stmt->get_result();
$loginHistory = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login History</title>
<link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>
<div class="dashboard-container">
    <h1>Admin Login History</h1>
    <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['CAdminName']); ?></strong>!</p>

    <?php if (count($loginHistory) > 0): ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Login Date & Time</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loginHistory as $index => $login): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($login['login_at']); ?></td>
                        <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($login['user_agent']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No login history found.</p>
    <?php endif; ?>

    <a href="dashboard.php" class="btn">Back to Dashboard</a>
</div>
</body>
</html>
