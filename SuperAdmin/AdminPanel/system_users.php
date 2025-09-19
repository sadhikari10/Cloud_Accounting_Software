<?php
    session_start();
    if (!isset($_SESSION['SuperAdminID'])) {
        header("Location: login.php");
        exit;
    }

    require '../../Common/connection.php';

    // --- Handle Search & Clear ---
    $search = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['clear'])) {
            // Clear pressed → reset search
            $search = '';
        } elseif (!empty($_POST['search'])) {
            $search = trim($_POST['search']);
        }
    }

    // --- Prepare query based on search ---
    if (!empty($search)) {
        $likeSearch = "%$search%";
        $stmt = $conn->prepare("SELECT SuperAdminID, FirstName, LastName, Email, Status 
                                FROM SuperAdmin 
                                WHERE Role != 'Admin'
                                AND (FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ?)
                                ORDER BY FirstName ASC");
        $stmt->bind_param("sss", $likeSearch, $likeSearch, $likeSearch);
    } else {
        $stmt = $conn->prepare("SELECT SuperAdminID, FirstName, LastName, Email, Status 
                                FROM SuperAdmin 
                                WHERE Role != 'Admin'
                                ORDER BY FirstName ASC");
    }

    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Users</title>
    <link rel="stylesheet" href="../style.css">

    </head>
    <body class="dashboard-page">
    <main>
        <div class="dashboard-container">
            <h2>System Users</h2>

            <!-- Search Form -->
            <form method="POST" class="search-container">
                <input type="text" name="search" placeholder="Search by First Name, Last Name or Email"
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <button type="submit" name="clear" value="1">Clear</button>
            </form>

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
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['FirstName']); ?></td>
                                <td><?php echo htmlspecialchars($row['LastName']); ?></td>
                                <td><?php echo htmlspecialchars($row['Email']); ?></td>
                                <td><?php echo htmlspecialchars($row['Status']); ?></td>
                                <td>
                                    <a href="individual_user.php?id=<?php echo $row['SuperAdminID']; ?>" class="dashboard-btn">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">No users found.</td>
                            </tr>
                        <?php endif; ?>
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
