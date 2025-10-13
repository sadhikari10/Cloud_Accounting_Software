<?php
session_start();
require '../../Common/connection.php';

// -------------------------
// Debugging: session info
// -------------------------
echo "<!-- DEBUG: Session Variables -->\n";
echo "<pre>" . print_r($_SESSION, true) . "</pre>\n";

// Check if user is logged in
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    echo "DEBUG: User not logged in. Redirecting...";
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'];
$userId = $_SESSION['UserID'] ?? $_SESSION['CAdminID'];
$role = $_SESSION['Role'];

$error = '';
$success = '';

// -------------------------
// Handle AJAX request for sub-parents
// -------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_sub_parents') {
    $parentType = $_GET['parent_type'] ?? '';
    if (!$parentType) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, account_name 
        FROM chart_of_accounts 
        WHERE account_type = ? AND parent_id IS NOT NULL 
          AND (is_system = 1 OR created_by = ?) 
        ORDER BY account_code ASC
    ");
    $stmt->bind_param("si", $parentType, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $subParents = [];
    while ($row = $result->fetch_assoc()) {
        $subParents[] = ['id' => $row['id'], 'name' => $row['account_name']];
    }

    // Debug
    error_log("DEBUG: Sub-parents fetched: " . print_r($subParents, true));

    header('Content-Type: application/json');
    echo json_encode($subParents);
    exit;
}

// -------------------------
// Handle Form Submission
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentType = $_POST['parent_type'] ?? '';
    $subParentId = $_POST['sub_parent'] ?? '';
    $newSubParentName = trim($_POST['new_sub_parent'] ?? '');
    $childName = trim($_POST['child_name'] ?? '');
    $normalSide = $_POST['normal_side'] ?? '';

    echo "<!-- DEBUG: Form submission received -->\n";
    echo "<pre>" . print_r($_POST, true) . "</pre>\n";

    if (!$parentType || (!$subParentId && !$newSubParentName) || !$childName || !$normalSide) {
        $error = "Please fill in all required fields.";
        echo "<!-- DEBUG: Validation failed -->\n";
    } else {
        $conn->begin_transaction();

        try {
            // -------------------------
            // Insert new sub-parent if provided
            // -------------------------
            if ($newSubParentName) {
                // Get next sub-parent code
                $stmt = $conn->prepare("
                    SELECT MAX(SUBSTRING(account_code,3,2)) as max_code 
                    FROM chart_of_accounts 
                    WHERE account_type=? AND parent_id IS NOT NULL
                ");
                $stmt->bind_param("s", $parentType);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $nextSubCode = ($row['max_code'] ?? 50) + 1; // start at 50 if none
                if ($nextSubCode < 50) $nextSubCode = 50;

                $prefix = ($parentType === 'Asset') ? '10' :
                          (($parentType === 'Liability') ? '20' :
                          (($parentType === 'Equity') ? '30' :
                          (($parentType === 'Income') ? '40' : '50')));
                
                $subAccountCode = sprintf("%02d%02d0000", intval($prefix), $nextSubCode);

                $stmtInsert = $conn->prepare("
                    INSERT INTO chart_of_accounts 
                    (account_code, account_name, normal_side, account_type, parent_id, is_system, created_by, updated_by) 
                    VALUES (?, ?, ?, ?, NULL, 0, ?, ?)
                ");
                $stmtInsert->bind_param("sssiii", $subAccountCode, $newSubParentName, $normalSide, $parentType, $userId, $userId);
                $stmtInsert->execute();
                $subParentId = $stmtInsert->insert_id;

                // -------------------------
                // Insert History
                // -------------------------
                $stmtHist = $conn->prepare("
                    INSERT INTO chart_of_accounts_history 
                    (account_id, company_id, action_type, old_data, new_data, performed_by) 
                    VALUES (?, ?, 'CREATE', NULL, ?, ?)
                ");
                $newData = json_encode([
                    'account_code' => $subAccountCode,
                    'account_name' => $newSubParentName,
                    'normal_side' => $normalSide,
                    'account_type' => $parentType
                ]);
                $stmtHist->bind_param("iisi", $subParentId, $companyId, $newData, $userId);
                $stmtHist->execute();

                echo "<!-- DEBUG: New sub-parent inserted, ID: $subParentId, Code: $subAccountCode -->\n";
            }

            // -------------------------
            // Insert child entry
            // -------------------------
            // Determine next child code
            $stmt = $conn->prepare("SELECT MAX(account_code) as max_code FROM chart_of_accounts WHERE parent_id=?");
            $stmt->bind_param("i", $subParentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $maxCode = $row['max_code'] ?? null;

            if ($maxCode) {
                $childCodeNum = intval(substr($maxCode,4)) + 1;
            } else {
                $subParentRow = $conn->query("SELECT account_code FROM chart_of_accounts WHERE id=$subParentId")->fetch_assoc();
                $childCodeNum = intval(substr($subParentRow['account_code'],4,4)) + 1;
            }

            $childCode = substr($maxCode ?? $subParentRow['account_code'],0,4) . str_pad($childCodeNum, 4, '0', STR_PAD_LEFT);

            $stmtInsertChild = $conn->prepare("
                INSERT INTO chart_of_accounts 
                (account_code, account_name, normal_side, account_type, parent_id, is_system, created_by, updated_by) 
                VALUES (?, ?, ?, ?, ?, 0, ?, ?)
            ");
            $stmtInsertChild->bind_param("sssiiii", $childCode, $childName, $normalSide, $parentType, $subParentId, $userId, $userId);
            $stmtInsertChild->execute();
            $childId = $stmtInsertChild->insert_id;

            // -------------------------
            // Insert child history
            // -------------------------
            $stmtHistChild = $conn->prepare("
                INSERT INTO chart_of_accounts_history 
                (account_id, company_id, action_type, old_data, new_data, performed_by) 
                VALUES (?, ?, 'CREATE', NULL, ?, ?)
            ");
            $newDataChild = json_encode([
                'account_code' => $childCode,
                'account_name' => $childName,
                'normal_side' => $normalSide,
                'account_type' => $parentType
            ]);
            $stmtHistChild->bind_param("iisi", $childId, $companyId, $newDataChild, $userId);
            $stmtHistChild->execute();

            $conn->commit();
            $success = "Account added successfully!";
            echo "<!-- DEBUG: Child account inserted, ID: $childId, Code: $childCode -->\n";

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
            echo "<!-- DEBUG: Exception caught - " . $e->getMessage() . " -->\n";
        }
    }
}

// -------------------------
// Fetch all accounts for display
// -------------------------
$accounts = [];
$stmt = $conn->prepare("SELECT * FROM chart_of_accounts WHERE is_system=1 OR created_by=? ORDER BY account_code ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

echo "<!-- DEBUG: Total accounts fetched: " . count($accounts) . " -->\n";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chart of Accounts</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
table { border-collapse: collapse; width: 100%; }
th, td { border:1px solid #ccc; padding: 8px; text-align: left; }
.error { color:red; }
.success { color:green; }
</style>
</head>
<body>
<h2>Chart of Accounts</h2>

<?php if($error): ?><p class="error"><?=htmlspecialchars($error)?></p><?php endif; ?>
<?php if($success): ?><p class="success"><?=htmlspecialchars($success)?></p><?php endif; ?>

<form method="POST">
    <label>Parent Type:</label>
    <select name="parent_type" id="parent_type">
        <option value="">-- Select Parent --</option>
        <option value="Asset">Asset</option>
        <option value="Liability">Liability</option>
        <option value="Equity">Equity</option>
        <option value="Income">Income</option>
        <option value="Expense">Expense</option>
    </select><br><br>

    <label>Sub Parent:</label>
    <select name="sub_parent" id="sub_parent">
        <option value="">-- Select Sub Parent --</option>
    </select>
    <input type="text" name="new_sub_parent" placeholder="Or add new sub-parent"><br><br>

    <label>Child Name:</label>
    <input type="text" name="child_name" required><br><br>

    <label>Normal Side:</label>
    <select name="normal_side">
        <option value="Debit">Debit</option>
        <option value="Credit">Credit</option>
    </select><br><br>

    <button type="submit">Add Account</button>
</form>

<h3>Existing Accounts</h3>
<table>
<tr>
<th>Code</th><th>Name</th><th>Type</th><th>Normal Side</th><th>Source</th>
</tr>
<?php foreach($accounts as $a): ?>
<tr>
<td><?=htmlspecialchars($a['account_code'])?></td>
<td><?=htmlspecialchars($a['account_name'])?></td>
<td><?=htmlspecialchars($a['account_type'])?></td>
<td><?=htmlspecialchars($a['normal_side'])?></td>
<td><?= $a['is_system'] ? 'System Default' : 'Company Entry' ?></td>
</tr>
<?php endforeach; ?>
</table>

<script>
$(document).ready(function(){
    $('#parent_type').change(function(){
        var parentType = $(this).val();
        if(parentType){
            $.ajax({
                url:'load_subparents.php',
                type:'POST',
                data:{parent_type: parentType},
                success:function(data){
                    $('#sub_parent').html(data);
                }
            });
        } else {
            $('#sub_parent').html('<option value="">-- Select Sub Parent --</option>');
        }
    });
});
</script>
</body>
</html>
