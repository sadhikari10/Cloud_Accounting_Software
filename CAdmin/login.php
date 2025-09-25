<?php
session_start();
require '../Common/connection.php';

$error = '';
$email = '';
$role = '';
$inactiveMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        // Determine table based on role
        if (strtolower($role) === 'admin') {
            $table = 'company_admins';
            $idField = 'admin_id';
        } else {
            $table = 'company_staff';
            $idField = 'staff_id';
        }

        // Prepare secure statement
        $stmt = $conn->prepare("SELECT $idField, password_hash, first_name, last_name, status, company_id, must_change_password 
                                FROM $table 
                                WHERE email = ? LIMIT 1");

        if (!$stmt) {
            $error = "Internal error: Unable to process login. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $companyId, $mustChange);
                $stmt->fetch();

                if ($status !== 'active') {
                    $inactiveMessage = "Your account is inactive. Please wait for activation.";
                } elseif (password_verify($password, $hash)) {
                    session_regenerate_id(true); // prevent session fixation

                    // Set session variables
                    $_SESSION['UserID'] = $id;
                    $_SESSION['UserName'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['Role'] = $role;
                    $_SESSION['Email'] = $email;
                    $_SESSION['CompanyID'] = $companyId;

                    // Staff first-time login check
                    if (strtolower($role) === 'staff' && $mustChange == 1) {
                        header("Location: StaffPanel/create_new_password.php");
                        exit;
                    }

                    // Record login in company_user_login_history
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                    $adminId = null;
                    $staffId = null;

                    if (strtolower($role) === 'admin') {
                        $adminId = $id;
                    } else {
                        $staffId = $id;
                    }

                    $historyStmt = $conn->prepare("
                        INSERT INTO company_user_login_history 
                        (admin_id, staff_id, company_id, login_at, ip_address, user_agent) 
                        VALUES (?, ?, ?, NOW(), ?, ?)
                    ");

                    if ($historyStmt) {
                        $historyStmt->bind_param("iiiss", $adminId, $staffId, $companyId, $ip, $userAgent);
                        $historyStmt->execute();
                        $historyStmt->close();
                    }

                    // Redirect based on role
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
