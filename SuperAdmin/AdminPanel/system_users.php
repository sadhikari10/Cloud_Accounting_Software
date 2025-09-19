<?php
session_start();
if (!isset($_SESSION['SuperAdminID'])) {
    header("Location: login.php");
    exit;
}

require '../../Common/connection.php';

// Fetch all users except Admin role
$stmt = $conn->prepare("SELECT SuperAdminID, FirstName, LastName, Email, Status 
                        FROM SuperAdmin 
                        WHERE Role != 'Admin'
                        ORDER BY FirstName ASC");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Details</title>
    <link rel="stylesheet" href="../style.css">
</head>
    <body class="dashboard-page">
        <main>
            <div class="dashboard-container">
                <h2>Accounts Details</h2>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>View Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['FirstName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['LastName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Status']); ?></td>
                                    <td>
                                        <a href="individual_user.php?id=<?php echo $row['SuperAdminID']; ?>" class="dashboard-btn">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

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
