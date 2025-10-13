    <?php
    session_start();
    require '../../Common/connection.php';

    // ------------------ Check Admin ------------------
    if (!isset($_SESSION['CAdminID']) || strtolower($_SESSION['Role']) !== 'admin') {
        http_response_code(403);
        die("Unauthorized");
    }

    $staffId = intval($_GET['id'] ?? 0);
    if ($staffId <= 0) die("Invalid staff ID.");

    // ------------------ Handle AJAX Updates ------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax']==='1') {
        // Ensure no stray output
        ob_clean();
        header('Content-Type: application/json');
        
        // Suppress warnings
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['type'])) {
            echo json_encode(['success'=>false,'message'=>'Invalid request.']);
            exit;
        }

        $companyId = $_SESSION['CompanyID'];
        $adminId = $_SESSION['CAdminID'];

        // ---------- STATUS UPDATE ----------
        if ($data['type']==='status') {
            $newStatus = $data['status']==='active'?'active':'inactive';
            $stmt = $conn->prepare("UPDATE company_staff SET status=? WHERE staff_id=?");
            $stmt->bind_param("si",$newStatus,$staffId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success'=>true,'message'=>'Status updated successfully.']);
            exit;
        }

        // ---------- PERMISSION UPDATE ----------
        if ($data['type']==='permission' && isset($data['module'],$data['action'],$data['checked'])) {
            $module = $data['module'];
            $action = $data['action'];
            $checked = (bool)$data['checked'];

            // Fetch current permissions
            $stmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id=? AND company_id=?");
            $stmt->bind_param("ii",$staffId,$companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $permissions = [];
            if($result->num_rows>0){
                $row=$result->fetch_assoc();
                $permissions=json_decode($row['permissions'],true);
            }
            $stmt->close();

            $oldPermissionsJson = json_encode($permissions);
            if(!isset($permissions[$module])) $permissions[$module]=[];
            $permissions[$module][$action]=$checked;
            $newPermissionsJson = json_encode($permissions);

            // Update main table
            $stmt = $conn->prepare("UPDATE user_permissions SET permissions=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND company_id=?");
            $stmt->bind_param("sii",$newPermissionsJson,$staffId,$companyId);
            $stmt->execute();
            $stmt->close();

            // Insert history
            $stmt = $conn->prepare("INSERT INTO user_permission_history (admin_id, staff_id, company_id, old_permissions, new_permissions, changed_at)
                VALUES (?,?,?,?,?,NOW())");
            $stmt->bind_param("iiiss",$adminId,$staffId,$companyId,$oldPermissionsJson,$newPermissionsJson);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success'=>true,'message'=>'Permission updated successfully.']);
            exit;
        }

        echo json_encode(['success'=>false,'message'=>'Unknown action.']);
        exit;
    }

    // ----------------- Fetch Staff Info ------------------
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, status, role, created_at, must_change_password 
                            FROM company_staff WHERE staff_id=?");
    $stmt->bind_param("i",$staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$staff) die("Staff not found.");

    $passwordStatus = $staff['must_change_password']==1?'Pending Change':'Updated';

    // ----------------- Fetch Login History ------------------
    $stmt = $conn->prepare("SELECT LoginID, login_at, ip_address, user_agent FROM company_user_login_history WHERE staff_id=? ORDER BY login_at DESC");
    $stmt->bind_param("i",$staffId);
    $stmt->execute();
    $loginHistory = $stmt->get_result();
    $stmt->close();

    // ----------------- Fetch Permissions ------------------
    $stmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id=? AND company_id=?");
    $stmt->bind_param("ii",$staffId,$_SESSION['CompanyID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentPermissions = [];
    if($result->num_rows>0){
        $row=$result->fetch_assoc();
        $currentPermissions=json_decode($row['permissions'],true);
    }
    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="customer_admin_style.css">
        <title>Staff Details & Permissions</title>
        <style>
            .msg{font-size:14px;margin-top:5px;}
        </style>
    </head>

    <body>
        <h2>Staff Details</h2>
        <a href="staff_management.php" class="btn">Back to Users List</a>

        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($staff['first_name'].' '.$staff['last_name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone_number']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($staff['role']); ?></p>
        <p><strong>Password Status:</strong> <?php echo $passwordStatus; ?></p>
        <p><strong>Created At:</strong> <?php echo $staff['created_at']; ?></p>

        <!-- Dynamic Status -->
        <p><strong>Current Status:</strong></p>
        <select id="statusDropdown">
            <option value="active" <?php echo $staff['status']==='active'?'selected':'';?>>Active</option>
            <option value="inactive" <?php echo $staff['status']==='inactive'?'selected':'';?>>Inactive</option>
        </select>
        <div id="statusMsg" class="msg"></div>

        <h3>Login History</h3>
        <table>
            <thead>
            <tr><th>Date & Time</th><th>IP Address</th><th>User Agent</th></tr>
            </thead>

            <tbody>
                <?php if($loginHistory->num_rows>0): ?>
                    <?php while($login=$loginHistory->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $login['login_at']; ?></td>
                        <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($login['user_agent']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="3">No login history available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Assign Permissions</h3>
        <div id="permMsg" class="msg"></div>
        <?php foreach($currentPermissions as $module=>$actions): ?>
        <fieldset>
            <legend><?php echo ucfirst($module);?></legend>
            <?php foreach($actions as $action=>$value): ?>
            <label>
                <input type="checkbox" class="permCheckbox" 
                    data-module="<?php echo $module;?>" 
                    data-action="<?php echo $action;?>" 
                    <?php echo $value?'checked':'';?>>
                <?php echo ucfirst($action);?>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <?php endforeach; ?>

        <script>
            // --- AJAX: Update Status ---
            const statusDropdown=document.getElementById('statusDropdown');
            const statusMsg=document.getElementById('statusMsg');
            statusDropdown.addEventListener('change',async e=>{
                const status=e.target.value;
                statusMsg.textContent="Saving...";
                try{
                    const res=await fetch(`?id=<?php echo $staffId;?>&ajax=1`,{
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({type:'status',status})
                    });
                    const data=await res.json();
                    if(data.success){
                        statusMsg.style.color="green";
                        statusMsg.textContent="✓ "+data.message;
                    } else {
                        statusMsg.style.color="red";
                        statusMsg.textContent="✗ "+data.message;
                    }
                }catch(err){
                    statusMsg.style.color="red";
                    statusMsg.textContent="✗ Error: "+err.message;
                }
            });

            // --- AJAX: Update Permissions ---
            const permMsg=document.getElementById('permMsg');
            document.querySelectorAll('.permCheckbox').forEach(box=>{
                box.addEventListener('change',async e=>{
                    const module=e.target.dataset.module;
                    const action=e.target.dataset.action;
                    const checked=e.target.checked;
                    permMsg.textContent="Saving...";
                    try{
                        const res=await fetch(`?id=<?php echo $staffId;?>&ajax=1`,{
                            method:'POST',
                            headers:{'Content-Type':'application/json'},
                            body:JSON.stringify({type:'permission',module,action,checked})
                        });
                        const data=await res.json();
                        if(data.success){
                            permMsg.style.color="green";
                            permMsg.textContent="✓ "+data.message;
                        } else {
                            permMsg.style.color="red";
                            permMsg.textContent="✗ "+data.message;
                            e.target.checked=!checked;
                        }
                    }catch(err){
                        permMsg.style.color="red";
                        permMsg.textContent="✗ Error: "+err.message;
                        e.target.checked=!checked;
                    }
                });
            });
        </script>
    </body>
</html>
