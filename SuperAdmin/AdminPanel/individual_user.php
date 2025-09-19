<?php
session_start();
if (!isset($_SESSION['SuperAdminID'])) {
    header("Location: login.php");
    exit;
}

require '../../Common/connection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid user ID.");
}

$userID = intval($_GET['id']);
$statusMessage = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $newStatus = ($_POST['status'] === 'Active') ? 'Active' : 'Inactive';

    $updateStmt = $conn->prepare("UPDATE SuperAdmin SET Status = ?, UpdatedAt = NOW() WHERE SuperAdminID = ?");
    $updateStmt->bind_param("si", $newStatus, $userID);
    if ($updateStmt->execute()) {
        $statusMessage = "User status updated to $newStatus.";
    } else {
        $statusMessage = "Failed to update status.";
    }
    $updateStmt->close();
}

// Fetch user details after potential update
$stmt = $conn->prepare("SELECT FirstName, LastName, Email, LastLoginAt, UpdatedAt, Role, Status, PhoneNumber
                        FROM SuperAdmin
                        WHERE SuperAdminID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Individual User Details</title>
<link rel="stylesheet" href="../style.css">

</head>
<body class="dashboard-page">
<main>
    <div class="dashboard-container">
        <h2>Individual Details</h2>

        <?php if($statusMessage): ?>
            <div class="success-message"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if($user): ?>
            <div class="table-responsive">
                <table>
                    <tbody>
                        <tr><th>First Name</th><td><?php echo htmlspecialchars($user['FirstName']); ?></td></tr>
                        <tr><th>Last Name</th><td><?php echo htmlspecialchars($user['LastName']); ?></td></tr>
                        <tr><th>Email</th><td><?php echo htmlspecialchars($user['Email']); ?></td></tr>
                        <tr><th>Phone Number</th><td><?php echo htmlspecialchars($user['PhoneNumber']); ?></td></tr>

                        <!-- Status row with current and dropdown -->
                        <tr>
                            <th>Status</th>
                            <td>
                                <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                                    <span>Current: <strong><?php echo htmlspecialchars($user['Status']); ?></strong></span>
                                    <form action="" method="POST" style="display:inline-flex; align-items:center; gap:5px;">
                                        <select name="status">
                                            <option value="Active" <?php if($user['Status'] === 'Active') echo 'selected'; ?>>Active</option>
                                            <option value="Inactive" <?php if($user['Status'] === 'Inactive') echo 'selected'; ?>>Inactive</option>
                                        </select>
                                        <button type="submit" class="dashboard-btn">Update</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <tr><th>Role</th><td><?php echo htmlspecialchars($user['Role']); ?></td></tr>
                        <tr><th>Last Login At</th><td><?php echo htmlspecialchars($user['LastLoginAt']); ?></td></tr>
                        <tr><th>Updated At</th><td><?php echo htmlspecialchars($user['UpdatedAt']); ?></td></tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>User not found.</p>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;">
            <a href="system_users.php" class="dashboard-btn">Back to Accounts</a>
        </div>

    </div>
</main>

<?php include '../../Common/footer.php'; ?>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
