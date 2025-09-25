<?php
session_start();
require '../../Common/connection.php';

// Check if admin is logged in
if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch all staff for the company admin
$companyId = $_SESSION['CompanyID'] ?? 0;

$stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, phone_number, role, status 
                        FROM company_staff 
                        WHERE company_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $companyId);
$stmt->execute();
$usersResult = $stmt->get_result();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Staff</title>
<link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>
<h2>Staff List</h2>
<a href="dashboard.php" class="btn">Back to Dashboard</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($usersResult && $usersResult->num_rows > 0): ?>
            <?php while ($user = $usersResult->fetch_assoc()): ?>
            <tr>
                <td><?php echo $user['staff_id']; ?></td>
                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td><?php echo htmlspecialchars($user['status']); ?></td>
                <td>
                    <a href="view_staff_details.php?id=<?php echo $user['staff_id']; ?>" class="details-btn">View Details</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7">No staff found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
