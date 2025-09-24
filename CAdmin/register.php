<?php
session_start();
require '../Common/connection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $companyAddress = trim($_POST['company_address'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    try {
        // Basic validations
        if (empty($companyName) || empty($companyAddress) || empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
            throw new Exception("Please fill in all required fields.");
        }

        if (!preg_match("/^[a-zA-Z\s]+$/", $companyName)) throw new Exception("Company name must contain only letters and spaces.");
        if (!preg_match("/^[a-zA-Z]+$/", $firstName)) throw new Exception("First name must contain only letters.");
        if (!preg_match("/^[a-zA-Z]+$/", $lastName)) throw new Exception("Last name must contain only letters.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format.");
        if (!preg_match("/^\+?[0-9]{7,15}$/", $phone)) throw new Exception("Phone number is invalid.");
        if ($password !== $confirmPassword) throw new Exception("Passwords do not match.");
        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) throw new Exception("Password must be at least 8 characters and include uppercase, lowercase, number, and special character.");

        // Check if company already exists
        $stmt = $conn->prepare("SELECT company_id FROM companies WHERE company_name = ?");
        $stmt->bind_param("s", $companyName);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) throw new Exception("Company name already exists.");
        $stmt->close();

        // Check if email or phone already exists
        $stmt = $conn->prepare("SELECT admin_id FROM company_admins WHERE email = ? OR phone_number = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) throw new Exception("Email or phone number already registered.");
        $stmt->close();

        // Start transaction safely
        if (!$conn->begin_transaction()) {
            throw new Exception("Failed to start database transaction.");
        }

        // Insert company
        $stmt = $conn->prepare("INSERT INTO companies (company_name, address, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
        $stmt->bind_param("ss", $companyName, $companyAddress);
        if (!$stmt->execute()) throw new Exception("Failed to insert company: " . $stmt->error);
        $companyId = $stmt->insert_id;
        $stmt->close();

        // Insert admin
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO company_admins (company_id, email, password_hash, first_name, last_name, phone_number, status, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', 'Admin', NOW(), NOW())");
        $stmt->bind_param("isssss", $companyId, $email, $passwordHash, $firstName, $lastName, $phone);
        if (!$stmt->execute()) throw new Exception("Failed to insert admin: " . $stmt->error);
        $stmt->close();

        $conn->commit();
        header("Location: login.php");
    } catch (Exception $e) {
        // Rollback safely
        if (method_exists($conn, 'rollback')) {
            $conn->rollback();
        }
        $error = $e->getMessage();
    }
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Company & Admin</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<main>
    <div class="form-container">
        <h2>Register Company & Admin</h2>

        <?php if($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <h4>Company Details</h4>
            <label>Company Name *</label>
            <input type="text" name="company_name" required value="<?php echo htmlspecialchars($companyName ?? ''); ?>">

            <label>Address *</label>
            <input type="text" name="company_address" required value="<?php echo htmlspecialchars($companyAddress ?? ''); ?>">

            <h4>Admin Details</h4>
            <label>First Name *</label>
            <input type="text" name="first_name" required value="<?php echo htmlspecialchars($firstName ?? ''); ?>">

            <label>Last Name *</label>
            <input type="text" name="last_name" required value="<?php echo htmlspecialchars($lastName ?? ''); ?>">

            <label>Email *</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">

            <label>Phone *</label>
            <input type="text" name="phone" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">

            <div class="password-group">
                <label>Password *</label>
                <input type="password" name="password" id="password" required>
                <i class="fa-solid fa-eye toggle-password"></i>
            </div>

            <div class="password-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <i class="fa-solid fa-eye toggle-password"></i>
            </div>

            <button type="submit" class="btn">Register</button>
        </form>

        <p class="form-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</main>

<script>
const toggles = document.querySelectorAll('.toggle-password');
toggles.forEach(toggle => {
    toggle.addEventListener('click', function() {
        const input = this.previousElementSibling;
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
