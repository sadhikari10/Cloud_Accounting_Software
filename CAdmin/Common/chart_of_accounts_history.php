<?php
session_start();
require '../../Common/connection.php';

// Check if user is logged in
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'];
$userId = $_SESSION['UserID'] ?? $_SESSION['CAdminID'];

// Fetch history with joins for performer name (admin or staff) and parent account
$history = [];
$stmt = $conn->prepare("
    SELECT h.id, h.account_id, h.action_type, h.old_data, h.new_data, h.performed_by, h.performed_at,
           a.account_name,
           parent_a.account_name as parent_name,
           COALESCE(
               CONCAT(s.first_name, ' ', s.last_name),
               CONCAT(ca.first_name, ' ', ca.last_name)
           ) as performer_name
    FROM chart_of_accounts_history h
    LEFT JOIN chart_of_accounts a ON h.account_id = a.id
    LEFT JOIN chart_of_accounts parent_a ON a.parent_id = parent_a.id
    LEFT JOIN company_staff s ON h.performed_by = s.staff_id AND s.company_id = ?
    LEFT JOIN company_admins ca ON h.performed_by = ca.admin_id AND ca.company_id = ?
    WHERE h.company_id = ? AND h.action_type = 'CREATE'
    ORDER BY h.performed_at DESC
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iii", $companyId, $companyId, $companyId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chart of Accounts Creation History</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .details { background: #f4f4f4; padding: 4px; font-family: monospace; font-size: 12px; max-width: 300px; overflow: auto; }
    </style>
</head>
<body>
    <h2>Chart of Accounts Creation History</h2>
    <p>This shows who created which accounts for your company.</p>

    <a href="../AdminPanel/dashboard.php">Back to Dashboard</a>

    <?php if (empty($history)): ?>
        <p>No creation history found.</p>
    <?php else: ?>
        <h3>Created Accounts</h3>
        <table>
            <tr>
                <th>Serial No.</th>
                <th>Created By</th>
                <th>Date Created</th>
                <th>Parent Account</th>
                <th>Account Details</th>
            </tr>
            <?php $serial = 1; foreach ($history as $h): ?>
            <tr>
                <td><?= $serial++ ?></td>
                <td><?= htmlspecialchars($h['performer_name'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($h['performed_at']) ?></td>
                <td><?= htmlspecialchars($h['parent_name'] ?? 'N/A') ?></td>
                <td>
                    <div class="details">
                        <?php
                        $newData = $h['new_data'] ?? null;
                        if ($newData) {
                            $data = json_decode($newData, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                echo "account name : " . htmlspecialchars($data['account_name'] ?? 'N/A') . ", account_type : " . htmlspecialchars($data['account_type'] ?? 'N/A');
                            } else {
                                echo htmlspecialchars($newData);
                            }
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <script>
        $(document).ready(function(){
            // Optional: Additional enhancements if needed
        });
    </script>
</body>
</html>