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
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $accountId = $_POST['account_id'] ?? 0;
            if ($accountId) {
                $conn->begin_transaction();
                try {
                    // Check if it's a company entry
                    $stmtCheck = $conn->prepare("SELECT is_system, company_id, parent_id FROM chart_of_accounts WHERE id = ?");
                    if (!$stmtCheck) {
                        throw new Exception("Unable to prepare check query. Please try again.");
                    }
                    $stmtCheck->bind_param("i", $accountId);
                    $stmtCheck->execute();
                    $resultCheck = $stmtCheck->get_result();
                    $rowCheck = $resultCheck->fetch_assoc();

                    if ($rowCheck && $rowCheck['is_system'] == 0 && $rowCheck['company_id'] == $companyId) {
                        // Get old data for history
                        $stmtOld = $conn->prepare("SELECT account_code, account_name, normal_side, account_type FROM chart_of_accounts WHERE id = ?");
                        if (!$stmtOld) {
                            throw new Exception("Unable to prepare old data query. Please try again.");
                        }
                        $stmtOld->bind_param("i", $accountId);
                        $stmtOld->execute();
                        $resultOld = $stmtOld->get_result();
                        $oldRow = $resultOld->fetch_assoc();
                        $oldData = json_encode([
                            'account_code' => $oldRow['account_code'],
                            'account_name' => $oldRow['account_name'],
                            'normal_side' => $oldRow['normal_side'],
                            'account_type' => $oldRow['account_type']
                        ]);

                        // Delete children first
                        $stmtDelChildren = $conn->prepare("DELETE FROM chart_of_accounts WHERE parent_id = ? AND company_id = ?");
                        if (!$stmtDelChildren) {
                            throw new Exception("Unable to prepare children delete query. Please try again.");
                        }
                        $stmtDelChildren->bind_param("ii", $accountId, $companyId);
                        $stmtDelChildren->execute();

                        // Delete the account
                        $stmtDel = $conn->prepare("DELETE FROM chart_of_accounts WHERE id = ? AND company_id = ?");
                        if (!$stmtDel) {
                            throw new Exception("Unable to prepare delete query. Please try again.");
                        }
                        $stmtDel->bind_param("ii", $accountId, $companyId);
                        $stmtDel->execute();

                        // Log history
                        $stmtHist = $conn->prepare("INSERT INTO chart_of_accounts_history (account_id, company_id, action_type, old_data, new_data, performed_by) VALUES (?, ?, 'DELETE', ?, NULL, ?)");
                        if (!$stmtHist) {
                            throw new Exception("Unable to prepare history insert. Please try again.");
                        }
                        $stmtHist->bind_param("iisi", $accountId, $companyId, $oldData, $userId);
                        $stmtHist->execute();

                        $conn->commit();
                        $success = "Account deleted successfully!";
                    } else {
                        throw new Exception("Cannot delete system default or unauthorized account.");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            } else {
                $error = "Invalid account ID.";
            }
        } elseif ($_POST['action'] === 'edit') {
            $accountId = $_POST['account_id'] ?? 0;
            $newName = trim($_POST['account_name'] ?? '');
            if ($accountId && $newName) {
                $conn->begin_transaction();
                try {
                    // Check if it's a company entry
                    $stmtCheck = $conn->prepare("SELECT is_system, company_id FROM chart_of_accounts WHERE id = ?");
                    if (!$stmtCheck) {
                        throw new Exception("Unable to prepare check query. Please try again.");
                    }
                    $stmtCheck->bind_param("i", $accountId);
                    $stmtCheck->execute();
                    $resultCheck = $stmtCheck->get_result();
                    $rowCheck = $resultCheck->fetch_assoc();

                    if ($rowCheck && $rowCheck['is_system'] == 0 && $rowCheck['company_id'] == $companyId) {
                        // Get old data
                        $stmtOld = $conn->prepare("SELECT account_code, account_name, normal_side, account_type FROM chart_of_accounts WHERE id = ?");
                        if (!$stmtOld) {
                            throw new Exception("Unable to prepare old data query. Please try again.");
                        }
                        $stmtOld->bind_param("i", $accountId);
                        $stmtOld->execute();
                        $resultOld = $stmtOld->get_result();
                        $oldRow = $resultOld->fetch_assoc();
                        $oldData = json_encode([
                            'account_code' => $oldRow['account_code'],
                            'account_name' => $oldRow['account_name'],
                            'normal_side' => $oldRow['normal_side'],
                            'account_type' => $oldRow['account_type']
                        ]);

                        // Update account name only
                        $stmtUpdate = $conn->prepare("UPDATE chart_of_accounts SET account_name = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND company_id = ?");
                        if (!$stmtUpdate) {
                            throw new Exception("Unable to prepare update query. Please try again.");
                        }
                        $stmtUpdate->bind_param("siii", $newName, $userId, $accountId, $companyId);
                        if (!$stmtUpdate->execute()) {
                            $dbErrorCode = $stmtUpdate->errno;
                            $dbErrorMsg = $stmtUpdate->error;
                            if ($dbErrorCode == 1062) { // Duplicate entry
                                throw new Exception("An account with the name '$newName' already exists for this company. Please choose a different name.");
                            } else {
                                throw new Exception("Failed to update account: " . $dbErrorMsg);
                            }
                        }

                        // New data
                        $newData = json_encode([
                            'account_code' => $oldRow['account_code'],
                            'account_name' => $newName,
                            'normal_side' => $oldRow['normal_side'],
                            'account_type' => $oldRow['account_type']
                        ]);

                        // Log history
                        $stmtHist = $conn->prepare("INSERT INTO chart_of_accounts_history (account_id, company_id, action_type, old_data, new_data, performed_by) VALUES (?, ?, 'UPDATE', ?, ?, ?)");
                        if (!$stmtHist) {
                            throw new Exception("Unable to prepare history insert. Please try again.");
                        }
                        $stmtHist->bind_param("iissi", $accountId, $companyId, $oldData, $newData, $userId);
                        $stmtHist->execute();

                        $conn->commit();
                        $success = "Account updated successfully!";
                    } else {
                        throw new Exception("Cannot edit system default or unauthorized account.");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            } else {
                $error = "Please provide a valid account name for editing.";
            }
        }
    } else {
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
form button { margin: 2px; }
#editModal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
#editModal .modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 400px;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}
.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
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

<!-- Edit Modal -->
<div id="editModal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h3>Edit Account Name</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="account_id" id="edit_account_id" value="">
            <label for="edit_account_name">Account Name:</label><br>
            <input type="text" id="edit_account_name" name="account_name" required><br><br>
            <button type="submit">Update</button>
            <button type="button" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>
</div>

<h3>Existing Accounts</h3>
<table>
<tr>
<th>Code</th><th>Name</th><th>Type</th><th>Normal Side</th><th>Source</th><th>Actions</th>
</tr>
<?php foreach($accounts as $a): ?>
<tr>
<td><?=htmlspecialchars($a['account_code'])?></td>
<td><?=htmlspecialchars($a['account_name'])?></td>
<td><?=htmlspecialchars($a['account_type'])?></td>
<td><?=htmlspecialchars($a['normal_side'])?></td>
<td><?= $a['is_system'] ? 'System Default' : 'Company Entry' ?></td>
<td>
<?php if (!$a['is_system']): ?>
    <button onclick="editAccount(<?= $a['id'] ?>, '<?= addslashes($a['account_name']) ?>')" style="color:blue;">Edit</button>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this account and its children?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="account_id" value="<?= $a['id'] ?>">
        <button type="submit" style="color:red;">Delete</button>
    </form>
<?php else: ?>
    -
<?php endif; ?>
</td>
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

    window.editAccount = function(id, name) {
        $('#edit_account_id').val(id);
        $('#edit_account_name').val(name);
        $('#editModal').show();
    };

    window.closeEditModal = function() {
        $('#editModal').hide();
    };

    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeEditModal();
        }
    }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>