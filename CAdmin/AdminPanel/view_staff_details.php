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

    // ------------------ AJAX Handler ------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax']==='1') {
        ob_clean();
        header('Content-Type: application/json');

        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data || !isset($data['type'])) {
                throw new Exception("Invalid request data.");
            }

            $companyId = $_SESSION['CompanyID'];
            $adminId = $_SESSION['CAdminID'];

            // --------- STATUS UPDATE ---------
            if ($data['type'] === 'status') {
                if (!isset($data['status'])) throw new Exception("Status value missing.");

                $newStatus = $data['status'] === 'active' ? 'active' : 'inactive';
                $stmt = $conn->prepare("UPDATE company_staff SET status=? WHERE staff_id=?");
                if (!$stmt) throw new Exception("Failed to prepare status update statement.");
                $stmt->bind_param("si", $newStatus, $staffId);
                $stmt->execute();
                $stmt->close();

                echo json_encode(['success'=>true,'message'=>'Status updated successfully.']);
                exit;
            }

            // --------- PERMISSION UPDATE ---------
            if ($data['type'] === 'permission') {
                if (!isset($data['module'], $data['action'], $data['checked'])) {
                    throw new Exception("Incomplete permission data.");
                }

                $module = $data['module'];
                $action = $data['action'];
                $checked = (bool)$data['checked'];

                // Fetch current permissions
                $stmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id=? AND company_id=?");
                if (!$stmt) throw new Exception("Failed to prepare permission fetch statement.");
                $stmt->bind_param("ii", $staffId, $companyId);
                $stmt->execute();
                $result = $stmt->get_result();
                $permissions = [];
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $permissions = json_decode($row['permissions'], true) ?? [];
                }
                $stmt->close();

                $oldPermissionsJson = json_encode($permissions);
                if (!isset($permissions[$module])) $permissions[$module] = [];
                $permissions[$module][$action] = $checked;
                $newPermissionsJson = json_encode($permissions);

                // Update main table
                $stmt = $conn->prepare("UPDATE user_permissions SET permissions=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND company_id=?");
                if (!$stmt) throw new Exception("Failed to prepare permission update statement.");
                $stmt->bind_param("sii", $newPermissionsJson, $staffId, $companyId);
                $stmt->execute();
                $stmt->close();

                // Insert history
                $stmt = $conn->prepare("INSERT INTO user_permission_history (admin_id, staff_id, company_id, old_permissions, new_permissions, changed_at)
                    VALUES (?,?,?,?,?,NOW())");
                if (!$stmt) throw new Exception("Failed to insert permission history.");
                $stmt->bind_param("iiiss", $adminId, $staffId, $companyId, $oldPermissionsJson, $newPermissionsJson);
                $stmt->execute();
                $stmt->close();

                echo json_encode(['success'=>true,'message'=>'Permission updated successfully.']);
                exit;
            }

            throw new Exception("Unknown action type.");

        } catch (Exception $e) {
            // Log server-side
            error_log("AJAX Error (staff_id: $staffId): " . $e->getMessage());

            // Return friendly message to user
            echo json_encode(['success'=>false,'message'=>'An error occurred. Please try again later.']);
            exit;
        }
    }

    // ----------------- Fetch Staff Info -----------------
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, status, role, created_at, must_change_password 
                                FROM company_staff WHERE staff_id=?");
        if (!$stmt) throw new Exception("Failed to fetch staff info.");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$staff) throw new Exception("Staff not found.");

        $passwordStatus = $staff['must_change_password'] == 1 ? 'Pending Change' : 'Updated';

        // ----------------- Fetch Login History -----------------
        $stmt = $conn->prepare("SELECT LoginID, login_at, ip_address, user_agent 
                                FROM company_user_login_history 
                                WHERE staff_id=? 
                                ORDER BY login_at DESC");
        if (!$stmt) throw new Exception("Failed to fetch login history.");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $loginHistory = $stmt->get_result();
        $stmt->close();

        // ----------------- Fetch Permissions -----------------
        $stmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id=? AND company_id=?");
        if (!$stmt) throw new Exception("Failed to fetch permissions.");
        $stmt->bind_param("ii", $staffId, $_SESSION['CompanyID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentPermissions = [];
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentPermissions = json_decode($row['permissions'], true) ?? [];
        }
        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        error_log("Staff Page Error (staff_id: $staffId): " . $e->getMessage());
        die("An error occurred while loading staff details. Please try again later.");
    }
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
            
            /* Make checkbox and text inline and closer */
            .permCheckbox {
                margin-right: 5px;   /* Space between checkbox and text */
                vertical-align: middle;
            }

            label {
                display: inline-flex; /* Keep checkbox and text on same line */
                align-items: center;  /* Vertically center checkbox and text */
                margin-right: 15px;   /* Space between multiple checkboxes */
                margin-bottom: 5px;   /* Space between rows */
                cursor: pointer;      /* Makes label clickable */
            }
            #statusDropdown {
                width: auto;           /* Fit content */
                min-width: 120px;      /* Optional: prevent it from being too small */
                max-width: 250px;      /* Optional: limit max width */
                padding: 5px 8px;      /* Some padding for nicer look */
                font-size: 14px;
                display: inline-block; /* Prevent stretching */
            }

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

        <!-- View Permission History Button -->
        <p>
            <a href="view_permission_history.php?staff_id=<?php echo $staffId; ?>" class="btn">
                View Permission History
            </a>
        </p>


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
            statusDropdown.addEventListener('change', async e => {
                const status = e.target.value;
                statusMsg.textContent = "Saving...";
                try {
                    const res = await fetch(`?id=<?php echo $staffId;?>&ajax=1`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type: 'status', status })
                    });
                    const data = await res.json();
                    statusMsg.style.color = data.success ? "green" : "red";
                    statusMsg.textContent = (data.success ? "✓ " : "✗ ") + data.message;

                    // Hide message after 5 seconds
                    setTimeout(() => { statusMsg.textContent = ""; }, 5000);

                } catch (err) {
                    statusMsg.style.color = "red";
                    statusMsg.textContent = "✗ Error: " + err.message;
                    setTimeout(() => { statusMsg.textContent = ""; }, 5000);
                }
            });

            // --- AJAX: Update Permissions ---
            document.querySelectorAll('.permCheckbox').forEach(box => {
                box.addEventListener('change', async e => {
                    const module = e.target.dataset.module;
                    const action = e.target.dataset.action;
                    const checked = e.target.checked;
                    permMsg.textContent = "Saving...";
                    try {
                        const res = await fetch(`?id=<?php echo $staffId;?>&ajax=1`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ type: 'permission', module, action, checked })
                        });
                        const data = await res.json();
                        permMsg.style.color = data.success ? "green" : "red";
                        permMsg.textContent = (data.success ? "✓ " : "✗ ") + data.message;

                        if (!data.success) e.target.checked = !checked; // rollback

                        // Hide message after 5 seconds
                        setTimeout(() => { permMsg.textContent = ""; }, 5000);

                    } catch (err) {
                        permMsg.style.color = "red";
                        permMsg.textContent = "✗ Error: " + err.message;
                        e.target.checked = !checked;
                        setTimeout(() => { permMsg.textContent = ""; }, 5000);
                    }
                });
            });
        </script>
    </body>
</html>
