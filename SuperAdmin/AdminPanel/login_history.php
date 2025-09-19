<?php
session_start();
if (!isset($_SESSION['SuperAdminID']) || !isset($_SESSION['SuperAdminEmail'])) {
    header("Location: ../login.php");
    exit;
}

require '../../Common/connection.php';

$adminEmail = $_SESSION['SuperAdminEmail']; // Email stored during login

// Fetch login history only for the logged-in admin
$stmt = $conn->prepare(
    "SELECT ALH.LoginAt, ALH.IPAddress, ALH.UserAgent
     FROM AdminLoginHistory ALH
     INNER JOIN SuperAdmin SA ON ALH.SuperAdminID = SA.SuperAdminID
     WHERE SA.Email = ?
     ORDER BY ALH.LoginAt DESC"
);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Login History</title>
<link rel="stylesheet" href="../style.css">
<style>
/* Optional responsive table styling */
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}
@media (max-width: 768px) {
    table, thead, tbody, th, td, tr {
        display: block;
        width: 100%;
    }
    th {
        display: none;
    }
    td {
        position: relative;
        padding-left: 50%;
        text-align: right;
    }
    td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        width: 45%;
        font-weight: bold;
        text-align: left;
    }
}
</style>
</head>
<body class="history-page">
<main>
    <div class="history-container">
        <h2>My Previous Logins</h2>

        <?php if($result->num_rows === 0): ?>
            <p>No login history found.</p>
        <?php else: ?>
            <div class="table-responsive">
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
                            <td data-label="Login Time (Nepali)"><?php echo htmlspecialchars($row['LoginAt']); ?></td>
                            <td data-label="IP Address"><?php echo htmlspecialchars($row['IPAddress']); ?></td>
                            <td data-label="User Agent"><?php echo htmlspecialchars($row['UserAgent']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <br>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</main>

<?php include '../../Common/footer.php'; ?>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
