<?php
session_start();
require '../Common/connection.php';

$error = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $login_at = $_POST['login_at']; // Nepali time from frontend

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Verify admin credentials
        $stmt = $conn->prepare("SELECT SuperAdminID, PasswordHash, FirstName, LastName, Status 
                                FROM SuperAdmin 
                                WHERE Email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hash, $firstName, $lastName, $status);
                $stmt->fetch();

                if ($status !== 'Active') {
                    $error = "Account is inactive or suspended.";
                } elseif (password_verify($password, $hash)) {
                    // Successful login

                    // Combine first and last name safely
                    $firstName = trim($firstName);
                    $lastName = trim($lastName);
                    if (empty($lastName) || strtolower($firstName) === strtolower($lastName)) {
                        $displayName = $firstName;
                    } else {
                        $displayName = $firstName . ' ' . $lastName;
                    }

                    $_SESSION['SuperAdminID'] = $id;
                    $_SESSION['SuperAdminName'] = $displayName;
                    session_regenerate_id(true);

                    // Update LastLoginAt in SuperAdmin
                    $updateStmt = $conn->prepare("UPDATE SuperAdmin SET LastLoginAt = NOW() WHERE SuperAdminID = ?");
                    $updateStmt->bind_param("i", $id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    // Insert login history
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];

                    $historyStmt = $conn->prepare("INSERT INTO AdminLoginHistory (SuperAdminID, LoginAt, IPAddress, UserAgent) VALUES (?, ?, ?, ?)");
                    $historyStmt->bind_param("isss", $id, $login_at, $ip, $userAgent);
                    $historyStmt->execute();
                    $historyStmt->close();

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }

            $stmt->close();
        } else {
            $error = "An internal error occurred. Please try again later.";
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
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>

        <?php if($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST" onsubmit="getNepaliTime()">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter email" required value="<?php echo isset($email)?htmlspecialchars($email):''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>
            <input type="hidden" name="login_at" id="login_at">
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>

    <script>
        function getNepaliTime() {
            const now = new Date();
            // UTC+5:45 for Nepal
            const nepaliTime = new Date(now.getTime() + (5*60 + 45)*60000);
            document.getElementById('login_at').value = nepaliTime.toISOString().slice(0,19).replace('T', ' ');
        }
    </script>
</body>
</html>
