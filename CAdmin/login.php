<?php
session_start();
require '../Common/connection.php';

$error = '';
$email = '';
$role = '';
$inactiveMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        // Determine table based on role
        if (strtolower($role) === 'admin') {
            $stmt = $conn->prepare("SELECT admin_id, password_hash, first_name, last_name, status, company_id 
                                    FROM company_admins WHERE email = ? LIMIT 1");
            $idField = 'admin_id';
        } else {
            $stmt = $conn->prepare("SELECT staff_id, password_hash, first_name, last_name, status, company_id, must_change_password 
                                    FROM company_staff WHERE email = ? LIMIT 1");
            $idField = 'staff_id';
        }

        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            if (strtolower($role) === 'admin') {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $companyId);
            } else {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $companyId, $mustChange);
            }

            $stmt->fetch();

            if ($status !== 'active') {
                $inactiveMessage = "Your account is inactive. Please wait for activation.";
            } elseif (password_verify($password, $hash)) {
                session_regenerate_id(true); // prevent session fixation

                // -------------------- Set Session Variables --------------------
                if (strtolower($role) === 'admin') {
                    $_SESSION['CAdminID'] = $id;
                    $_SESSION['CAdminName'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['CompanyID'] = $companyId;
                } else {
                    $_SESSION['UserID'] = $id;
                    $_SESSION['UserName'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['CompanyID'] = $companyId;
                }

                $_SESSION['Role'] = $role;
                $_SESSION['Email'] = $email;

                // -------------------- Load Staff Permissions --------------------
                if (strtolower($role) === 'staff') {
                    $permissions = [];
                    $permStmt = $conn->prepare("SELECT permissions FROM user_permissions WHERE user_id = ? AND company_id = ?");
                    if ($permStmt) {
                        $permStmt->bind_param("ii", $id, $companyId);
                        $permStmt->execute();
                        $permResult = $permStmt->get_result();
                        if ($permResult && $permResult->num_rows > 0) {
                            $row = $permResult->fetch_assoc();
                            $permissions = json_decode($row['permissions'], true);
                        }
                        $_SESSION['Permissions'] = $permissions;
                        $permStmt->close();
                    }
                }

                // -------------------- First-time Staff Login --------------------
                if (strtolower($role) === 'staff' && $mustChange == 1) {
                    header("Location: StaffPanel/create_new_password.php");
                    exit;
                }

                // -------------------- Record Login History --------------------
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $adminId = (strtolower($role) === 'admin') ? $id : null;
                $staffId = (strtolower($role) === 'staff') ? $id : null;

                $historyStmt = $conn->prepare("INSERT INTO company_user_login_history 
                                               (admin_id, staff_id, company_id, login_at, ip_address, user_agent)
                                               VALUES (?, ?, ?, NOW(), ?, ?)");
                if ($historyStmt) {
                    $historyStmt->bind_param("iiiss", $adminId, $staffId, $companyId, $ip, $userAgent);
                    $historyStmt->execute();
                    $historyStmt->close();
                }

                // -------------------- Update Last Login --------------------
                if (strtolower($role) === 'admin') {
                    $updateStmt = $conn->prepare("UPDATE company_admins SET last_login_at = NOW() WHERE admin_id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("i", $id);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                } else {
                    $updateStmt = $conn->prepare("UPDATE company_staff SET 
                                                    last_login_at = NOW(), 
                                                    last_login_ip = ?, 
                                                    last_login_user_agent = ? 
                                                  WHERE staff_id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("ssi", $ip, $userAgent, $id);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }

                // -------------------- Redirect to Dashboard --------------------
                if (strtolower($role) === 'admin') {
                    header("Location: AdminPanel/dashboard.php");
                } else {
                    header("Location: StaffPanel/staff_dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CAdmin Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
    <div class="form-container">
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($inactiveMessage): ?>
            <div class="error-message"><?php echo htmlspecialchars($inactiveMessage); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Role</label>
            <select name="role" required>
                <option value="">-- Select Role --</option>
                <option value="Admin" <?php echo ($role === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="Staff" <?php echo ($role === 'Staff') ? 'selected' : ''; ?>>Staff</option>
            </select>

            <button type="submit" class="btn">Login</button>
        </form>

        <p class="form-link">
            Don’t have an account yet? <a href="register.php">Register here</a>
        </p>
    </div>
</main>
</body>
</html>
