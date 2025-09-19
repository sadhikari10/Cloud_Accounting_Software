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

// Fetch user details
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
        <title>View User Details</title>
        <link rel="stylesheet" href="../style.css">
    </head>
    <body class="dashboard-page">
        <main>
            <div class="dashboard-container">
                <h2>User Details</h2>

                <?php if($user): ?>
                    <div class="table-responsive">
                        <table>
                            <tbody>
                                <tr><th>First Name</th><td><?php echo htmlspecialchars($user['FirstName']); ?></td></tr>
                                <tr><th>Last Name</th><td><?php echo htmlspecialchars($user['LastName']); ?></td></tr>
                                <tr><th>Email</th><td><?php echo htmlspecialchars($user['Email']); ?></td></tr>
                                <tr><th>Phone Number</th><td><?php echo htmlspecialchars($user['PhoneNumber']); ?></td></tr>
                                <tr><th>Status</th><td><?php echo htmlspecialchars($user['Status']); ?></td></tr>
                                <tr><th>Role</th><td><?php echo htmlspecialchars($user['Role']); ?></td></tr>
                                <tr><th>Last Login At</th><td><?php echo htmlspecialchars($user['LastLoginAt']); ?></td></tr>
                                <tr><th>Updated At</th><td><?php echo htmlspecialchars($user['UpdatedAt']); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>User not found.</p>
                <?php endif; ?>

                <br>
                <a href="system_users.php" class="back-btn">Back to Accounts</a>
            </div>
        </main>
        <?php include '../../Common/footer.php'; ?>
    </body>
</html>

<?php
    $stmt->close();
    $conn->close();
?>
