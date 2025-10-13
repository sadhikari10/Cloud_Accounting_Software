<?php
session_start();
require '../../Common/connection.php';

// ------------------ Check Admin ------------------
if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$staffId = intval($_GET['staff_id'] ?? 0);
if ($staffId <= 0) die("Invalid staff ID.");

// ------------------ Fetch Permission History ------------------
$stmt = $conn->prepare("
    SELECT h.history_id, h.admin_id, h.old_permissions, h.new_permissions, h.changed_at, 
           a.first_name AS admin_first, a.last_name AS admin_last
    FROM user_permission_history h
    LEFT JOIN company_admins a ON h.admin_id = a.admin_id
    WHERE h.staff_id = ? AND h.company_id = ?
    ORDER BY h.changed_at DESC
");
$stmt->bind_param("ii", $staffId, $_SESSION['CompanyID']);
$stmt->execute();
$result = $stmt->get_result();
$historyData = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
    th { background: #f2f2f2; }
    .btn { background: #007bff; color: #fff; padding: 6px 12px; border-radius: 5px; text-decoration: none; display: inline-block; margin-bottom: 10px; }
    .btn:hover { background: #0056b3; }
</style>
</head>
<body>

<h2>Permission History for Staff ID: <?php echo $staffId; ?></h2>
<a href="view_staff_details.php?id=<?php echo $staffId; ?>" class="btn">Go Back to User Details</a>

<table>
    <thead>
        <tr>
            <th>Changed By</th>
            <th>Date & Time</th>
            <th>Old Permissions</th>
            <th>New Permissions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($historyData)): ?>
            <?php foreach ($historyData as $row): 
                $adminName = htmlspecialchars($row['admin_first'] . ' ' . $row['admin_last']);
                $oldPerms = json_decode($row['old_permissions'], true);
                $newPerms = json_decode($row['new_permissions'], true);
            ?>
            <tr>
                <td><?php echo $adminName; ?></td>
                <td><?php echo $row['changed_at']; ?></td>
                <td>
                    <table>
                        <?php foreach ($oldPerms as $module => $actions): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($module); ?></strong></td>
                            <td>
                                <?php 
                                $acts = [];
                                foreach ($actions as $act => $val) {
                                    if ($val) $acts[] = $act;
                                }
                                echo htmlspecialchars(implode(", ", $acts));
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
                <td>
                    <table>
                        <?php foreach ($newPerms as $module => $actions): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($module); ?></strong></td>
                            <td>
                                <?php 
                                $acts = [];
                                foreach ($actions as $act => $val) {
                                    if ($val) $acts[] = $act;
                                }
                                echo htmlspecialchars(implode(", ", $acts));
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="4">No permission history found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
