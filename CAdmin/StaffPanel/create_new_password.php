<?php
session_start();
require '../../Common/connection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New password and confirmation do not match.";
    } else {
        // Check if email exists and must_change_password = 1
        $stmt = $conn->prepare("SELECT staff_id, password_hash, must_change_password FROM company_staff WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $error = "Email not found.";
            } else {
                $stmt->bind_result($staffId, $hash, $mustChange);
                $stmt->fetch();

                if ($mustChange != 1) {
                    $error = "Password change is not required for this account.";
                } elseif (!password_verify($oldPassword, $hash)) {
                    $error = "Old password is incorrect.";
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $newPassword)) {
                    $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE company_staff SET password_hash = ?, must_change_password = 0 WHERE staff_id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("si", $newHash, $staffId);
                        if ($updateStmt->execute()) {
                            $success = "Password updated successfully. Redirecting to login...";
                            header("refresh:3;url=../login.php");
                        } else {
                            $error = "Failed to update password.";
                        }
                        $updateStmt->close();
                    }
                }
            }
            $stmt->close();
        } else {
            $error = "Internal server error.";
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
<title>Create New Password</title>
<link rel="stylesheet" href="staff_style.css">
</head>
<body>
<main>
    <div class="form-container">
        <h1>Create New Password</h1>

        <?php if ($error): ?>
            <p class="message error"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif ($success): ?>
            <p class="message success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">

            <label>Old Password</label>
            <input type="password" name="old_password" required>

            <label>New Password</label>
            <input type="password" name="new_password" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" class="btn">Update Password</button>
        </form>

        <a href="../login.php" class="btn logout-btn">Back to Login</a>
    </div>
</main>
</body>
</html>
