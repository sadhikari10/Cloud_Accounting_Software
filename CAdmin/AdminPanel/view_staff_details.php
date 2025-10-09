<?php
session_start();
require '../../Common/connection.php';

// ------------------ Check if admin is logged in ------------------
if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$staffId = intval($_GET['id'] ?? 0);
if ($staffId <= 0) die("Invalid staff ID.");

// ----------------- Handle Status Update ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_form'])) {
    $newStatus = $_POST['status'] === 'active' ? 'active' : 'inactive';
    $stmt = $conn->prepare("UPDATE company_staff SET status = ? WHERE staff_id = ?");
    $stmt->bind_param("si", $newStatus, $staffId);
    $stmt->execute();
    $stmt->close();
}

// ----------------- Fetch Staff Details ------------------
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, status, role, created_at, must_change_password 
                        FROM company_staff WHERE staff_id = ?");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$staffResult = $stmt->get_result();
$staff = $staffResult->fetch_assoc();
$stmt->close();

if (!$staff) die("Staff not found.");

// Determine password status
$passwordStatus = $staff['must_change_password'] == 1 ? 'Pending Change' : 'Updated';

// ----------------- Fetch Login History ------------------
$stmt = $conn->prepare("SELECT LoginID, login_at, ip_address, user_agent 
                        FROM company_user_login_history 
                        WHERE staff_id = ? 
                        ORDER BY login_at DESC");
$stmt->bind_param("i", $staffId);
$stmt->execute();
$loginHistory = $stmt->get_result();
$stmt->close();

// ----------------- Fetch Permissions from Database ------------------
$stmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id = ? AND company_id = ?");
$stmt->bind_param("ii", $staffId, $_SESSION['CompanyID']);
$stmt->execute();
$result = $stmt->get_result();
$currentPermissions = [];

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentPermissions = json_decode($row['permissions'], true);
}
$stmt->close();

// ----------------- Handle Permissions Update ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permissions_form'])) {
    $submittedPermissions = $_POST['permissions'] ?? [];

    // Prepare final permissions array based on database keys
    $finalPermissions = [];
    foreach ($currentPermissions as $module => $actions) {
        $finalPermissions[$module] = [];
        foreach ($actions as $action => $value) {
            $finalPermissions[$module][$action] = isset($submittedPermissions[$module]) && in_array($action, $submittedPermissions[$module]);
        }
    }

    // Update database
    $permissionsJson = json_encode($finalPermissions);

    $stmt = $conn->prepare("UPDATE user_permissions SET permissions = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND company_id = ?");
    $stmt->bind_param("sii", $permissionsJson, $staffId, $_SESSION['CompanyID']);
    $stmt->execute();
    $stmt->close();

    $currentPermissions = $finalPermissions;
    $permissionMessage = "Permissions updated successfully.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Details</title>
<link rel="stylesheet" href="customer_admin_style.css">
<style>
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; }
    th { background: #f2f2f2; }
    fieldset { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
    legend { font-weight: bold; }
    label { margin-right: 10px; }
    .btn {
        background: #007bff;
        color: #fff;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
    }
    .btn:hover { background: #0056b3; }
</style>
</head>
<body>
<h2>Staff Details</h2>
<a href="staff_management.php" class="btn">Back to Users List</a>

<p><strong>Full Name:</strong> <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
<p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone_number']); ?></p>
<p><strong>Role:</strong> <?php echo htmlspecialchars($staff['role']); ?></p>
<p><strong>Password Status:</strong> <?php echo $passwordStatus; ?></p>
<p><strong>Created At:</strong> <?php echo $staff['created_at']; ?></p>
<p><strong>Current Status:</strong> <?php echo htmlspecialchars($staff['status']); ?></p>

<!-- Status Update Form -->
<form method="POST" style="margin-top:10px;">
    <input type="hidden" name="status_form" value="1">
    <label>Change Status:</label>
    <select name="status">
        <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
        <option value="inactive" <?php echo $staff['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
    </select>
    <button type="submit" class="btn">Update Status</button>
</form>

<h3>Login History</h3>
<table>
    <thead>
        <tr>
            <th>Date & Time</th>
            <th>IP Address</th>
            <th>User Agent</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($loginHistory->num_rows > 0): ?>
            <?php while ($login = $loginHistory->fetch_assoc()): ?>
            <tr>
                <td><?php echo $login['login_at']; ?></td>
                <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                <td><?php echo htmlspecialchars($login['user_agent']); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3">No login history available.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<h3>Assign Permissions</h3>
<?php if (isset($permissionMessage)) echo "<p style='color:green;'>$permissionMessage</p>"; ?>
<form method="POST">
    <input type="hidden" name="permissions_form" value="1">
    <?php foreach ($currentPermissions as $module => $actions): ?>
        <fieldset>
            <legend><?php echo ucfirst($module); ?></legend>
            <?php foreach ($actions as $action => $value): ?>
                <label>
                    <input type="checkbox" name="permissions[<?php echo $module; ?>][]" value="<?php echo $action; ?>"
                        <?php echo ($value === true) ? 'checked' : ''; ?>>
                    <?php echo ucfirst($action); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>
    <button type="submit" class="btn">Save Permissions</button>
</form>

</body>
</html>
