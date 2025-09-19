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
$stmt->close();

// Fetch login history for this user
$historyStmt = $conn->prepare("SELECT LoginAt, IPAddress, UserAgent 
                               FROM AdminLoginHistory 
                               WHERE SuperAdminID = ? 
                               ORDER BY LoginAt DESC");
$historyStmt->bind_param("i", $userID);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$historyStmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Individual User Details</title>
<link rel="stylesheet" href="../style.css">
<style>
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.success-message { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
.history-table th, .history-table td { padding: 8px; border: 1px solid #ccc; }
</style>
</head>
<body class="dashboard-page">
<main>
    <div class="dashboard-container">
        <h2>Individual User Details</h2>

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

                        <!-- Status row -->
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

            <!-- Login history -->
            <h3>Login History</h3>
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Login Time (Nepali)</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $historyResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['LoginAt']); ?></td>
                            <td><?php echo htmlspecialchars($row['IPAddress']); ?></td>
                            <td><?php echo htmlspecialchars($row['UserAgent']); ?></td>
                        </tr>
                        <?php endwhile; ?>
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
