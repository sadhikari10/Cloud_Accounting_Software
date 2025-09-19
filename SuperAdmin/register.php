<?php
session_start();
require '../Common/connection.php'; // mysqli connection

$error = '';
$success='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    try {
        // Validations
        if (!preg_match("/^[a-zA-Z]+$/", $firstName)) {
            throw new Exception("First name must contain only alphabets.");
        }
        if (!preg_match("/^[a-zA-Z]+$/", $lastName)) {
            throw new Exception("Last name must contain only alphabets.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        if (!preg_match("/^\+?[0-9\s\-]{7,15}$/", $phone)) {
            throw new Exception("Phone number is invalid.");
        }
        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match.");
        }
        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
            throw new Exception("Password must be at least 8 characters and include uppercase, lowercase, number, and special character.");
        }

        // Check email and phone uniqueness
        $stmt = $conn->prepare("SELECT SuperAdminID FROM SuperAdmin WHERE Email = ? OR PhoneNumber = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("Email or phone number is already registered.");
        }
        $stmt->close();

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert staff with inactive status
        $stmt = $conn->prepare("INSERT INTO SuperAdmin (Email, PasswordHash, FirstName, LastName, PhoneNumber, Status, Role, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, 'Inactive', 'Staff', NOW(), NOW())");
        $stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $phone);
        $stmt->execute();
        $stmt->close();

        // Pop-up and redirect
        echo "<script>
                alert('Registration successful! Your account is inactive and will be activated by admin.');
                window.location.href='login.php';
              </script>";
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register New Account</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<main>
    <div class="register-container">
        <h2>Register New Account</h2>

        <?php if($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($firstName ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($lastName ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>

            <div class="form-group password-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <i class="fa-solid fa-eye toggle-password" toggle="#password"></i>
            </div>

            <div class="form-group password-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <i class="fa-solid fa-eye toggle-password" toggle="#confirm_password"></i>
            </div>

            <button type="submit" class="login-btn">Register</button>
        </form>

        <p class="register-link">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</main>

<?php include '../Common/footer.php'; ?>

<script>
const toggles = document.querySelectorAll('.toggle-password');
toggles.forEach(toggle => {
    toggle.addEventListener('click', function() {
        const input = document.querySelector(this.getAttribute('toggle'));
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});
</script>
</body>
</html>
