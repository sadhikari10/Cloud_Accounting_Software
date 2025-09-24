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
        // Prepare statement with correct column names
        $stmt = $conn->prepare("SELECT admin_id, password_hash, first_name, last_name, status, role 
                                FROM company_admins 
                                WHERE email = ? AND role = ?");

        if (!$stmt) {
            $error = "Internal error: Unable to process login. Please try again later.";
        } else {
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $dbRole);
                $stmt->fetch();

                if ($status !== 'active') {
                    $inactiveMessage = "Your account is inactive. Please wait for activation.";
                } elseif (password_verify($password, $hash)) {
                    // Successful login
                    $_SESSION['CAdminID'] = $id;
                    $_SESSION['CAdminName'] = trim($firstName . ' ' . $lastName);
                    $_SESSION['Role'] = $dbRole;
                    $_SESSION['Email'] = $email;
                    session_regenerate_id(true);

                    // Redirect based on role
                    if (strtolower($dbRole) === 'admin') {
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
