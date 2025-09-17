<?php
session_start();
if (!isset($_SESSION['SuperAdminID'])) {
    header("Location: login.php");
    exit;
}

require '../Common/connection.php';
$adminID = $_SESSION['SuperAdminID'];

// Fetch login history
$stmt = $conn->prepare("SELECT LoginAt, IPAddress, UserAgent 
                        FROM AdminLoginHistory 
                        WHERE SuperAdminID = ? 
                        ORDER BY LoginAt DESC");
$stmt->bind_param("i", $adminID);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login History</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="history-page">
    <div class="history-container">
        <h2>Previous Logins</h2>
        <table>
            <thead>
                <tr>
                    <th>Login Time (Nepali)</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['LoginAt']); ?></td>
                    <td><?php echo htmlspecialchars($row['IPAddress']); ?></td>
                    <td><?php echo htmlspecialchars($row['UserAgent']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <br>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</body>
<?php 
    include '../Common/footer.php';
?>
</html>

<?php
$stmt->close();
$conn->close();
?>
