<?php
ob_start();
session_start();
require '../../Common/connection.php';

// Check if user is logged in
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'];
$userId = $_SESSION['UserID'] ?? $_SESSION['CAdminID'];
$role = $_SESSION['Role'];

$error = '';
$success = '';
$parentType = '';
$subParentId = '';
$newSubParentName = '';
$childName = '';
$normalSide = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentType = $_POST['parent_type'] ?? '';
    $subParentId = $_POST['sub_parent'] ?? '';
    $newSubParentName = trim($_POST['new_sub_parent'] ?? '');
    $childName = trim($_POST['child_name'] ?? '');
    $normalSide = $_POST['normal_side_hidden'] ?? $_POST['normal_side'] ?? '';

    $hasSubParent = !empty($subParentId);
    $hasNewSubParent = !empty($newSubParentName);
    $hasChild = !empty($childName);

    if (empty($parentType) || empty($normalSide)) {
        $error = "Please fill in all required fields.";
    } elseif ($hasChild) {
        if (!($hasSubParent XOR $hasNewSubParent)) {
            $error = "For adding a child, select exactly one sub-parent option (dropdown or new name).";
        }
    } else {
        if (!$hasNewSubParent) {
            $error = "For adding a sub-parent, provide a new sub-parent name.";
        }
    }

    if (empty($error)) {
        $conn->begin_transaction();

        try {
            $subParentIdForChild = $subParentId; // Use existing if provided

            // -------------------------
            // Insert new sub-parent if provided
            // -------------------------
            if ($newSubParentName) {
                $prefix = ($parentType === 'Asset') ? '10' :
                          (($parentType === 'Liability') ? '20' :
                          (($parentType === 'Equity') ? '30' :
                          (($parentType === 'Income') ? '40' : '50')));

                $topCode = sprintf("%02d000000", intval($prefix));

                $stmt_top = $conn->prepare("SELECT id FROM chart_of_accounts WHERE account_type = ? AND parent_id IS NULL AND account_code = ? AND (company_id IS NULL OR company_id = ?)");
                if (!$stmt_top) {
                    throw new Exception("Unable to prepare query for top-level account. Please try again.");
                }
                $stmt_top->bind_param("ssi", $parentType, $topCode, $companyId);
                $stmt_top->execute();
                $result_top = $stmt_top->get_result();
                $row_top = $result_top->fetch_assoc();

                if (!$row_top) {
                    throw new Exception("Top-level account for $parentType not found. Please contact support.");
                }

                $topId = $row_top['id'];

                // Get next sub-parent code
                $stmt = $conn->prepare("
                    SELECT MAX(CAST(SUBSTRING(account_code, 3, 2) AS UNSIGNED)) as max_code 
                    FROM chart_of_accounts 
                    WHERE account_type = ? AND parent_id = ? AND (company_id IS NULL OR company_id = ?)
                ");
                if (!$stmt) {
                    throw new Exception("Unable to prepare query for next code. Please try again.");
                }
                $stmt->bind_param("sii", $parentType, $topId, $companyId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $currentMax = $row['max_code'] ?? 0;
                $nextSubCode = $currentMax + 1;
                if ($nextSubCode < 50) $nextSubCode = 50;

                $subAccountCode = sprintf("%02d%02d0000", intval($prefix), $nextSubCode);

                $stmtInsert = $conn->prepare("
                    INSERT INTO chart_of_accounts 
                    (account_code, account_name, normal_side, account_type, parent_id, company_id, is_system, created_by, updated_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
                ");
                if (!$stmtInsert) {
                    throw new Exception("Unable to prepare insert query. Please try again.");
                }

                $stmtInsert->bind_param("ssssiiii", $subAccountCode, $newSubParentName, $normalSide, $parentType, $topId, $companyId, $userId, $userId);

                if (!$stmtInsert->execute()) {
                    $dbErrorCode = $stmtInsert->errno;
                    $dbErrorMsg = $stmtInsert->error;
                    if ($dbErrorCode == 1062) { // Duplicate entry
                        throw new Exception("A sub-parent account with the name '$newSubParentName' or code '$subAccountCode' already exists. Please choose a different name.");
                    } elseif ($dbErrorCode == 1452) { // Foreign key constraint
                        throw new Exception("Invalid parent reference. Please contact support.");
                    } else {
                        throw new Exception("Failed to add sub-parent account: " . $dbErrorMsg . ". Please try again or contact support if the issue persists.");
                    }
                }

                $subParentIdForChild = $stmtInsert->insert_id;

                // -------------------------
                // Insert History
                // -------------------------
                $newData = json_encode([
                    'account_code' => $subAccountCode,
                    'account_name' => $newSubParentName,
                    'normal_side' => $normalSide,
                    'account_type' => $parentType
                ]);

                $stmtHist = $conn->prepare("
                    INSERT INTO chart_of_accounts_history 
                    (account_id, company_id, action_type, old_data, new_data, performed_by) 
                    VALUES (?, ?, 'CREATE', NULL, ?, ?)
                ");
                if (!$stmtHist) {
                    throw new Exception("Unable to prepare history insert. Please try again.");
                }
                $stmtHist->bind_param("iisi", $subParentIdForChild, $companyId, $newData, $userId);
                if (!$stmtHist->execute()) {
                    $dbErrorCode = $stmtHist->errno;
                    $dbErrorMsg = $stmtHist->error;
                    if ($dbErrorCode == 1062) {
                        throw new Exception("History entry could not be created due to duplicate data. Please contact support.");
                    } else {
                        throw new Exception("Failed to log history: " . $dbErrorMsg . ". Please contact support.");
                    }
                }
            }

            // -------------------------
            // Insert child entry only if childName is provided
            // -------------------------
            if ($childName) {
                // Determine next child code
                $stmt = $conn->prepare("SELECT MAX(account_code) as max_code FROM chart_of_accounts WHERE parent_id=? AND (company_id IS NULL OR company_id = ?)");
                if (!$stmt) {
                    throw new Exception("Unable to prepare query for next child code. Please try again.");
                }
                $stmt->bind_param("ii", $subParentIdForChild, $companyId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $maxCode = $row['max_code'] ?? null;

                $subParentCode = null;
                if ($maxCode) {
                    $childCodeNum = intval(substr($maxCode,4)) + 1;
                    $subParentCode = substr($maxCode, 0, 4);
                } else {
                    $stmtSub = $conn->prepare("SELECT account_code FROM chart_of_accounts WHERE id=?");
                    if (!$stmtSub) {
                        throw new Exception("Unable to prepare query for sub-parent code. Please try again.");
                    }
                    $stmtSub->bind_param("i", $subParentIdForChild);
                    $stmtSub->execute();
                    $resultSub = $stmtSub->get_result();
                    $subParentRow = $resultSub->fetch_assoc();
                    $subParentCode = substr($subParentRow['account_code'], 0, 4);
                    $childCodeNum = intval(substr($subParentRow['account_code'],4,4)) + 1;
                }

                $childCode = $subParentCode . str_pad($childCodeNum, 4, '0', STR_PAD_LEFT);

                $stmtInsertChild = $conn->prepare("
                    INSERT INTO chart_of_accounts 
                    (account_code, account_name, normal_side, account_type, parent_id, company_id, is_system, created_by, updated_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
                ");
                if (!$stmtInsertChild) {
                    throw new Exception("Unable to prepare child insert query. Please try again.");
                }

                $stmtInsertChild->bind_param("ssssiiii", $childCode, $childName, $normalSide, $parentType, $subParentIdForChild, $companyId, $userId, $userId);

                if (!$stmtInsertChild->execute()) {
                    $dbErrorCode = $stmtInsertChild->errno;
                    $dbErrorMsg = $stmtInsertChild->error;
                    if ($dbErrorCode == 1062) { // Duplicate entry
                        throw new Exception("A child account with the name '$childName' or code '$childCode' already exists under this sub-parent. Please choose a different name.");
                    } elseif ($dbErrorCode == 1452) { // Foreign key constraint
                        throw new Exception("Invalid parent reference for child account. Please contact support.");
                    } else {
                        throw new Exception("Failed to add child account: " . $dbErrorMsg . ". Please try again or contact support if the issue persists.");
                    }
                }

                $childId = $stmtInsertChild->insert_id;

                // -------------------------
                // Insert child history
                // -------------------------
                $newDataChild = json_encode([
                    'account_code' => $childCode,
                    'account_name' => $childName,
                    'normal_side' => $normalSide,
                    'account_type' => $parentType
                ]);

                $stmtHistChild = $conn->prepare("
                    INSERT INTO chart_of_accounts_history 
                    (account_id, company_id, action_type, old_data, new_data, performed_by) 
                    VALUES (?, ?, 'CREATE', NULL, ?, ?)
                ");
                if (!$stmtHistChild) {
                    throw new Exception("Unable to prepare child history insert. Please try again.");
                }
                $stmtHistChild->bind_param("iisi", $childId, $companyId, $newDataChild, $userId);
                if (!$stmtHistChild->execute()) {
                    $dbErrorCode = $stmtHistChild->errno;
                    $dbErrorMsg = $stmtHistChild->error;
                    if ($dbErrorCode == 1062) {
                        throw new Exception("Child history entry could not be created due to duplicate data. Please contact support.");
                    } else {
                        throw new Exception("Failed to log child history: " . $dbErrorMsg . ". Please contact support.");
                    }
                }
            }

            $conn->commit();

            if ($newSubParentName && !$hasChild) {
                $success = "Sub-parent added successfully!";
            } elseif ($childName) {
                $success = "Child account added successfully!";
            }

            // Clear form on success
            $parentType = '';
            $subParentId = '';
            $newSubParentName = '';
            $childName = '';
            $normalSide = '';

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// -------------------------
// Handle AJAX request for sub-parents
// -------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_sub_parents') {
    ob_clean(); // Clean any prior output (e.g., from connection.php)
    $parentType = $_GET['parent_type'] ?? '';
    if (!$parentType) {
        header('Content-Type: application/json');
        echo json_encode(['subParents' => [], 'normalSide' => '']);
        exit;
    }

    $prefix = ($parentType === 'Asset') ? '10' :
              (($parentType === 'Liability') ? '20' :
              (($parentType === 'Equity') ? '30' :
              (($parentType === 'Income') ? '40' : '50')));

    $topCode = sprintf("%02d000000", intval($prefix));

    $stmt_top = $conn->prepare("SELECT id, normal_side FROM chart_of_accounts WHERE account_type = ? AND parent_id IS NULL AND account_code = ? AND (company_id IS NULL OR company_id = ?)");
    $stmt_top->bind_param("ssi", $parentType, $topCode, $companyId);
    $stmt_top->execute();
    $result_top = $stmt_top->get_result();
    $row_top = $result_top->fetch_assoc();

    if (!$row_top) {
        header('Content-Type: application/json');
        echo json_encode(['subParents' => [], 'normalSide' => '']);
        exit;
    }

    $topId = $row_top['id'];
    $normalSide = $row_top['normal_side'];

    $stmt = $conn->prepare("
        SELECT id, account_name 
        FROM chart_of_accounts 
        WHERE account_type = ? AND parent_id = ? 
          AND (company_id IS NULL OR company_id = ?)
          AND is_active = 1
        ORDER BY account_code ASC
    ");
    $stmt->bind_param("sii", $parentType, $topId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $subParents = [];
    while ($row = $result->fetch_assoc()) {
        $subParents[] = ['id' => $row['id'], 'name' => $row['account_name']];
    }

    header('Content-Type: application/json');
    echo json_encode(['subParents' => $subParents, 'normalSide' => $normalSide]);
    exit;
}

// -------------------------
// Fetch all accounts for display
// -------------------------
$accounts = [];
$stmt = $conn->prepare("SELECT * FROM chart_of_accounts WHERE (company_id IS NULL OR company_id = ?) AND is_active = 1 ORDER BY account_code ASC");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}
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
    <a href="../AdminPanel/dashboard.php" style="text-decoration:none;">
  <button>Back to Dashboard</button>
</a>

<h2>Chart of Accounts</h2>

<?php if($error): ?><p class="error"><?=htmlspecialchars($error)?></p><?php endif; ?>
<?php if($success): ?><p class="success"><?=htmlspecialchars($success)?></p><?php endif; ?>

<form method="POST" id="accountForm">
    <label>Parent Type:</label>
    <select name="parent_type" id="parent_type">
        <option value="">-- Select Parent --</option>
        <option value="Asset" <?= $parentType === 'Asset' ? 'selected' : '' ?>>Asset</option>
        <option value="Liability" <?= $parentType === 'Liability' ? 'selected' : '' ?>>Liability</option>
        <option value="Equity" <?= $parentType === 'Equity' ? 'selected' : '' ?>>Equity</option>
        <option value="Income" <?= $parentType === 'Income' ? 'selected' : '' ?>>Income</option>
        <option value="Expense" <?= $parentType === 'Expense' ? 'selected' : '' ?>>Expense</option>
    </select><br><br>

    <label>Sub Parent:</label>
    <select name="sub_parent" id="sub_parent">
        <option value="">-- Select Sub Parent --</option>
    </select>
    <input type="text" name="new_sub_parent" placeholder="Or add new sub-parent" value="<?= htmlspecialchars($newSubParentName) ?>"><br><br>

    <label>Child Name:</label>
    <input type="text" name="child_name" value="<?= htmlspecialchars($childName) ?>"><br><br>

    <label>Normal Side:</label>
    <select name="normal_side" id="normal_side">
        <option value="Debit" <?= $normalSide === 'Debit' ? 'selected' : '' ?>>Debit</option>
        <option value="Credit" <?= $normalSide === 'Credit' ? 'selected' : '' ?>>Credit</option>
    </select>
    <input type="hidden" name="normal_side_hidden" id="normal_side_hidden" value="<?= htmlspecialchars($normalSide) ?>"><br><br>

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
    // Hide success message after 10 seconds
    if ($('.success').length) {
        setTimeout(function() {
            $('.success').fadeOut('slow');
        }, 10000);
    }

    // Sync hidden normal_side on form submit
    $('#accountForm').submit(function() {
        var ns = $('#normal_side').val();
        $('#normal_side_hidden').val(ns);
    });

    // Sticky form: If error and parent_type selected, reload sub-parents and set values
    <?php if (!empty($error) && !empty($parentType)): ?>
    $('#parent_type').val('<?= addslashes($parentType) ?>').trigger('change');
    setTimeout(function() {
        $('#sub_parent').val('<?= addslashes($subParentId) ?>');
    }, 500);
    $('#normal_side_hidden').val('<?= addslashes($normalSide) ?>');
    $('#normal_side').val('<?= addslashes($normalSide) ?>');
    <?php endif; ?>

    $('#parent_type').change(function(){
        var parentType = $(this).val();
        if(parentType){
            $.ajax({
                url: window.location.pathname,
                type: 'GET',
                data: {action: 'get_sub_parents', parent_type: parentType},
                dataType: 'json',
                success: function(data){
                    var subParents = data.subParents;
                    var normalSide = data.normalSide;
                    var options = '<option value="">-- Select Sub Parent --</option>';
                    $.each(subParents, function(i, sp){
                        options += '<option value="' + sp.id + '">' + sp.name + '</option>';
                    });
                    $('#sub_parent').html(options);
                    $('#normal_side').val(normalSide);
                    $('#normal_side_hidden').val(normalSide);
                    $('#normal_side').prop('disabled', true);
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error: ' + status + ' - ' + error);
                    console.log(xhr.responseText);
                }
            });
        } else {
            $('#sub_parent').html('<option value="">-- Select Sub Parent --</option>');
            $('#normal_side').prop('disabled', false).val('');
            $('#normal_side_hidden').val('');
        }
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>