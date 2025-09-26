<?php
session_start();
require '../../Common/connection.php';

// Check if admin is logged in
if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$staffId = intval($_GET['id'] ?? 0);
if ($staffId <= 0) die("Invalid staff ID.");

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $newStatus = $_POST['status'] === 'active' ? 'active' : 'inactive';
    $stmt = $conn->prepare("UPDATE company_staff SET status = ? WHERE staff_id = ?");
    if (!$stmt) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("si", $newStatus, $staffId);
    $stmt->execute();
    $stmt->close();
}

// Fetch staff details
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, status, role, created_at 
                        FROM company_staff WHERE staff_id = ?");
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$staffResult = $stmt->get_result();
$staff = $staffResult->fetch_assoc();
$stmt->close();

if (!$staff) die("Staff not found.");

// Fetch login history
$stmt = $conn->prepare("SELECT LoginID, login_at, ip_address, user_agent 
                        FROM company_user_login_history 
                        WHERE staff_id = ? 
                        ORDER BY login_at DESC");
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$loginHistory = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Details</title>
<link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>
<h2>Staff Details</h2>
<a href="view_users.php" class="btn">Back to Users List</a>

<p><strong>Full Name:</strong> <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
<p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone_number']); ?></p>
<p><strong>Role:</strong> <?php echo htmlspecialchars($staff['role']); ?></p>
<p><strong>Created At:</strong> <?php echo $staff['created_at']; ?></p>

<p><strong>Current Status:</strong> <?php echo htmlspecialchars($staff['status']); ?></p>

<form method="POST" style="margin-top:10px;">
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
            <!-- <th>Login ID</th> -->
            <th>Date & Time</th>
            <th>IP Address</th>
            <th>User Agent</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($loginHistory->num_rows > 0): ?>
            <?php while ($login = $loginHistory->fetch_assoc()): ?>
            <tr>
                <!-- <td><?php echo $login['LoginID']; ?></td> -->
                <td><?php echo $login['login_at']; ?></td>
                <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                <td><?php echo htmlspecialchars($login['user_agent']); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">No login history available.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
