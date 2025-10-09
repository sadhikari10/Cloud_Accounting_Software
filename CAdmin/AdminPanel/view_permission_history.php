<?php
session_start();
require '../../Common/connection.php';

// ------------------ Check if admin is logged in ------------------
if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$staffId = intval($_GET['staff_id'] ?? 0); // Corrected GET parameter
if ($staffId <= 0) die("Invalid staff ID.");

$companyId = $_SESSION['CompanyID'];
$permissionHistory = [];
$errorMessage = '';

try {
    $stmt = $conn->prepare("SELECT old_permissions, new_permissions, changed_at 
                            FROM user_permission_history 
                            WHERE staff_id = ? AND company_id = ? 
                            ORDER BY changed_at DESC");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $staffId, $companyId);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $permissionHistory[] = [
            'old_permissions' => json_decode($row['old_permissions'], true),
            'new_permissions' => json_decode($row['new_permissions'], true),
            'changed_at' => $row['changed_at']
        ];
    }

    $stmt->close();
} catch (Exception $e) {
    $errorMessage = "Error fetching permission history: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permission History</title>
<link rel="stylesheet" href="customer_admin_style.css">
<style>
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
th { background: #f2f2f2; text-align: left; }
pre { margin: 0; font-family: monospace; }
.btn { display: inline-block; margin: 5px 0; padding: 10px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
</style>
</head>
<body>
<h2>Permission History</h2>
<a href="view_staff_details.php?id=<?php echo $staffId; ?>" class="btn">Back to Staff Details</a>

<?php if ($errorMessage): ?>
    <p style="color:red;"><?php echo htmlspecialchars($errorMessage); ?></p>
<?php elseif (count($permissionHistory) === 0): ?>
    <p>No permission history found for this staff member.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Changed At</th>
                <th>Old Permissions</th>
                <th>New Permissions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissionHistory as $history): ?>
                <tr>
                    <td><?php echo htmlspecialchars($history['changed_at']); ?></td>
                    <td>
                        <pre>
<?php foreach ($history['old_permissions'] as $module => $actions): ?>
<?php echo $module . ': ' . implode(', ', array_keys(array_filter($actions))) . "\n"; ?>
<?php endforeach; ?>
                        </pre>
                    </td>
                    <td>
                        <pre>
<?php foreach ($history['new_permissions'] as $module => $actions): ?>
<?php echo $module . ': ' . implode(', ', array_keys(array_filter($actions))) . "\n"; ?>
<?php endforeach; ?>
                        </pre>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
