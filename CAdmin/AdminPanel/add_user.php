<?php
session_start();
require '../../Common/connection.php';

$error = '';
$success = '';

// ------------------ Check Admin Session ------------------
if (!isset($_SESSION['CAdminID']) || !isset($_SESSION['CompanyID'])) {
    header("Location: ../login.php");
    exit;
}

// ------------------ Handle Form Submission ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone_number'] ?? '');
    $status = $_POST['status'] ?? 'active';

    $companyId = $_SESSION['CompanyID'];
    $createdBy = $_SESSION['CAdminID'];

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $tempPasswordExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

        // ------------------ Insert Staff ------------------
        $stmt = $conn->prepare("INSERT INTO company_staff 
            (company_id, email, password_hash, first_name, last_name, phone_number, status, role, must_change_password, temp_password_expires_at, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Staff', 1, ?, ?, NOW(), NOW())");

        if ($stmt) {
            $stmt->bind_param(
                "isssssssi",
                $companyId,
                $email,
                $passwordHash,
                $firstName,
                $lastName,
                $phone,
                $status,
                $tempPasswordExpires,
                $createdBy
            );

            if ($stmt->execute()) {
                // ✅ Staff added successfully
                $success = "Staff member added successfully!";

                // ------------------ Insert Default Permissions ------------------
                $staffId = $conn->insert_id;

                // Default customers permission (all false)
                $defaultPermissions = [
                    "customers" => [
                        "view" => false,
                        "create" => false,
                        "edit" => false,
                        "delete" => false
                    ]
                ];
                $permissionsJson = json_encode($defaultPermissions);

                $permStmt = $conn->prepare("INSERT INTO user_permissions (user_id, company_id, permissions, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                if ($permStmt) {
                    $permStmt->bind_param("iis", $staffId, $companyId, $permissionsJson);
                    $permStmt->execute();
                    $permStmt->close();
                }
                // ------------------ End Default Permissions ------------------

            } else {
                $error = "Database error: Could not add staff. Email might already exist.";
            }
            $stmt->close();
        } else {
            $error = "Internal error: Could not prepare statement.";
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
    <title>Add User</title>
    <link rel="stylesheet" href="customer_admin_style.css">
</head>
<body>

<h2>Add Staff User</h2>

<?php if ($error): ?>
    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
<?php elseif ($success): ?>
    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<form method="POST" action="">
    <label>First Name*</label>
    <input type="text" name="first_name" required>

    <label>Last Name*</label>
    <input type="text" name="last_name" required>

    <label>Email*</label>
    <input type="email" name="email" required>

    <label>Password*</label>
    <input type="password" name="password" required>

    <label>Phone Number</label>
    <input type="text" name="phone_number">

    <label>Status</label>
    <select name="status">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <button type="submit" class="btn">Add User</button>
</form>

<a href="dashboard.php">Back to Dashboard</a>

</body>
</html>
