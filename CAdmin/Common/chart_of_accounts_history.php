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

// Fetch history with joins for performer name (admin or staff)
$history = [];
$stmt = $conn->prepare("
    SELECT h.id, h.account_id, h.action_type, h.old_data, h.new_data, h.performed_by, h.performed_at,
           a.account_name,
           COALESCE(
               CONCAT(s.first_name, ' ', s.last_name),
               CONCAT(ca.first_name, ' ', ca.last_name)
           ) as performer_name
    FROM chart_of_accounts_history h
    LEFT JOIN chart_of_accounts a ON h.account_id = a.id
    LEFT JOIN company_staff s ON h.performed_by = s.staff_id AND s.company_id = ?
    LEFT JOIN company_admins ca ON h.performed_by = ca.admin_id AND ca.company_id = ?
    WHERE h.company_id = ?
    ORDER BY h.performed_at DESC
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iii", $companyId, $companyId, $companyId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Extract old_name, new_name, account_code, parent_name from json data
    $row['old_name'] = null;
    $row['new_name'] = null;
    $row['account_code'] = null;
    $row['parent_name'] = null;

    if ($row['old_data']) {
        $old = json_decode($row['old_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $row['old_name'] = $old['account_name'] ?? null;
            $row['account_code'] = $old['account_code'] ?? null;
            $row['parent_name'] = $old['parent_name'] ?? null;
        }
    }

    if ($row['new_data']) {
        $new = json_decode($row['new_data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $row['new_name'] = $new['account_name'] ?? null;
            if (!$row['account_code']) $row['account_code'] = $new['account_code'] ?? null;
            if (!$row['parent_name']) $row['parent_name'] = $new['parent_name'] ?? null;
        }
    }

    // Fallback to account_name if available (for non-deleted)
    if (!$row['old_name'] && !$row['new_name'] && $row['account_name']) {
        $row['old_name'] = $row['account_name'];
        $row['new_name'] = $row['account_name'];
    }

    // If parent_name still null, try to fetch from current db if account exists
    if (empty($row['parent_name']) && $row['account_id']) {
        $stmtParent = $conn->prepare("
            SELECT parent_a.account_name as parent_name 
            FROM chart_of_accounts a 
            LEFT JOIN chart_of_accounts parent_a ON a.parent_id = parent_a.id 
            WHERE a.id = ? AND (a.company_id IS NULL OR a.company_id = ?)
        ");
        if ($stmtParent) {
            $stmtParent->bind_param("ii", $row['account_id'], $companyId);
            $stmtParent->execute();
            $parentRes = $stmtParent->get_result();
            $parentRow = $parentRes->fetch_assoc();
            if ($parentRow && $parentRow['parent_name']) {
                $row['parent_name'] = $parentRow['parent_name'];
            }
            $stmtParent->close();
        }
    }

    $history[] = $row;
}

// Separate into sections
$creates = array_filter($history, function($h) { return $h['action_type'] === 'CREATE'; });
$updates = array_filter($history, function($h) { return $h['action_type'] === 'UPDATE'; });
$deletes = array_filter($history, function($h) { return $h['action_type'] === 'DELETE'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chart of Accounts History</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .details { background: #f4f4f4; padding: 4px; font-family: monospace; font-size: 12px; max-width: 300px; overflow: auto; }
    </style>
</head>
<body>
    <h2>Chart of Accounts History</h2>
    <p>This shows the history of account creations, updates, and deletions for your company.</p>

    <a href="../AdminPanel/dashboard.php">Back to Dashboard</a>

    <?php if (empty($history)): ?>
        <p>No history found.</p>
    <?php else: ?>
        <?php if (!empty($creates)): ?>
            <h3>Created Accounts</h3>
            <table>
                <tr>
                    <th>Serial No.</th>
                    <th>Performed By</th>
                    <th>Date Performed</th>
                    <th>Parent Account</th>
                    <th>Account Details</th>
                </tr>
                <?php $serial = 1; foreach ($creates as $h): ?>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= htmlspecialchars($h['performer_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($h['performed_at']) ?></td>
                    <td><?= htmlspecialchars($h['parent_name'] ?? 'N/A') ?></td>
                    <td>
                        <div class="details">
                            <?php
                            $data = null;
                            if ($h['new_data']) {
                                $data = json_decode($h['new_data'], true);
                            } elseif ($h['old_data']) {
                                $data = json_decode($h['old_data'], true);
                            }
                            $name = $h['new_name'] ?? $h['old_name'] ?? 'N/A';
                            $code = $h['account_code'] ?? 'N/A';
                            $type = $data['account_type'] ?? 'N/A';
                            if (json_last_error() === JSON_ERROR_NONE || $data) {
                                echo "Code: " . htmlspecialchars($code) . ", Name: " . htmlspecialchars($name) . ", Type: " . htmlspecialchars($type);
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

        <?php if (!empty($updates)): ?>
            <h3>Updated Accounts</h3>
            <table>
                <tr>
                    <th>Serial No.</th>
                    <th>Performed By</th>
                    <th>Date Performed</th>
                    <th>Parent Account</th>
                    <th>Old Name</th>
                    <th>New Name</th>
                    <th>Account Details</th>
                </tr>
                <?php $serial = 1; foreach ($updates as $h): ?>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= htmlspecialchars($h['performer_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($h['performed_at']) ?></td>
                    <td><?= htmlspecialchars($h['parent_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($h['old_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($h['new_name'] ?? 'N/A') ?></td>
                    <td>
                        <div class="details">
                            <?php
                            $data = null;
                            if ($h['new_data']) {
                                $data = json_decode($h['new_data'], true);
                            } elseif ($h['old_data']) {
                                $data = json_decode($h['old_data'], true);
                            }
                            $name = $h['new_name'] ?? $h['old_name'] ?? 'N/A';
                            $code = $h['account_code'] ?? 'N/A';
                            $type = $data['account_type'] ?? 'N/A';
                            if (json_last_error() === JSON_ERROR_NONE || $data) {
                                echo "Code: " . htmlspecialchars($code) . ", Name: " . htmlspecialchars($name) . ", Type: " . htmlspecialchars($type);
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

        <?php if (!empty($deletes)): ?>
            <h3>Deleted Accounts</h3>
            <table>
                <tr>
                    <th>Serial No.</th>
                    <th>Performed By</th>
                    <th>Date Performed</th>
                    <th>Parent Account</th>
                    <th>Account Details</th>
                </tr>
                <?php $serial = 1; foreach ($deletes as $h): ?>
                <tr>
                    <td><?= $serial++ ?></td>
                    <td><?= htmlspecialchars($h['performer_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($h['performed_at']) ?></td>
                    <td><?= htmlspecialchars($h['parent_name'] ?? 'N/A') ?></td>
                    <td>
                        <div class="details">
                            <?php
                            $data = null;
                            if ($h['old_data']) {
                                $data = json_decode($h['old_data'], true);
                            } elseif ($h['new_data']) {
                                $data = json_decode($h['new_data'], true);
                            }
                            $name = $h['old_name'] ?? $h['new_name'] ?? 'N/A';
                            $code = $h['account_code'] ?? 'N/A';
                            $type = $data['account_type'] ?? 'N/A';
                            if (json_last_error() === JSON_ERROR_NONE || $data) {
                                echo "Code: " . htmlspecialchars($code) . ", Name: " . htmlspecialchars($name) . ", Type: " . htmlspecialchars($type);
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
    <?php endif; ?>

    <script>
        $(document).ready(function(){
            // Optional: Additional enhancements if needed
        });
    </script>
</body>
</html>