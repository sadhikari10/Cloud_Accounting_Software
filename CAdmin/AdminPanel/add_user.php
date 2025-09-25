<?php
session_start();
require '../Common/connection.php';

$error = '';
$email = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        // Determine which table to check
        if ($role === 'Admin') {
            $table = "company_admins";
            $idColumn = "admin_id";
        } else { // Staff
            $table = "company_staff";
            $idColumn = "staff_id";
        }

        // Prepare statement
        $stmt = $conn->prepare("SELECT $idColumn, password_hash, first_name, last_name, status, must_change_password
                                FROM $table WHERE email = ? LIMIT 1");

        if (!$stmt) {
            $error = "Internal error: Unable to process login. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $mustChange);
                $stmt->fetch();

                if ($status !== 'active') {
                    $error = "Your account is inactive. Please contact your admin.";
                } elseif (password_verify($password, $hash)) {

                    // First-time login check
                    if ($mustChange == 1 && $role === 'Staff') {
                        $_SESSION['StaffID'] = $id;
                        $_SESSION['StaffName'] = trim($firstName . ' ' . $lastName);
                        header("Location: change_password.php");
                        exit;
                    }

                    // Successful login
                    session_regenerate_id(true);
                    if ($role === 'Admin') {
                        $_SESSION['CAdminID'] = $id;
                        $_SESSION['CAdminName'] = trim($firstName . ' ' . $lastName);
                        $_SESSION['Role'] = $role;

                        // Record login history
                        $stmt2 = $conn->prepare("INSERT INTO company_user_login_history (user_id, user_type, login_at, ip_address, user_agent)
                                                 VALUES (?, 'Admin', NOW(), ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $ua = $_SERVER['HTTP_USER_AGENT'];
                        $stmt2->bind_param("iss", $id, $ip, $ua);
                        $stmt2->execute();
                        $stmt2->close();

                        header("Location: AdminPanel/dashboard.php");
                    } else {
                        $_SESSION['UserID'] = $id;
                        $_SESSION['UserName'] = trim($firstName . ' ' . $lastName);
                        $_SESSION['Role'] = $role;

                        // Record login history
                        $stmt2 = $conn->prepare("INSERT INTO company_user_login_history (user_id, user_type, login_at, ip_address, user_agent)
                                                 VALUES (?, 'Staff', NOW(), ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $ua = $_SERVER['HTTP_USER_AGENT'];
                        $stmt2->bind_param("iss", $id, $ip, $ua);
                        $stmt2->execute();
                        $stmt2->close();

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
<link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>
<main>
    <div class="form-container">
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
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
