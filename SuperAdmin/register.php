<?php
    //start of session at the start of php file
    session_start();
    //indicates that this file requires connection of database
    require '../Common/connection.php'; 

    //strings to cathc error or success message while a new account is registered
    $error = '';
    $success='';

    //logic to check the entered details of staff of the accounting.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Sanitize and trim the entered the data by staffs
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        try {
            // Validation to make sure only alphabets are in first name.
            if (!preg_match("/^[a-zA-Z]+$/", $firstName)) {
                throw new Exception("First name must contain only alphabets.");
            }

            //Validation to make sure only alphabets are in last name.
            if (!preg_match("/^[a-zA-Z]+$/", $lastName)) {
                throw new Exception("Last name must contain only alphabets.");
            }

            //Validation to make sure email contains '@' symbol.
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            //Validation to make sure that phone numbers are numerical digits only.
            if (!preg_match("/^\+?[0-9\s\-]{7,15}$/", $phone)) {
                throw new Exception("Phone number is invalid.");
            }

            //Validation to make sure password and confirm password sections match.
            if ($password !== $confirmPassword) {
                throw new Exception("Passwords do not match.");
            }

            //Validation to make sure the password contains alphabets upper and lower, numeric values, special characters and is at leat eight characters long.
            if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
                throw new Exception("Password must be at least 8 characters and include uppercase, lowercase, number, and special character.");
            }

            // Check email and phone uniqueness to mak esure the same email and phone number can not used to register a new account.
            $stmt = $conn->prepare("SELECT SuperAdminID FROM SuperAdmin WHERE Email = ? OR PhoneNumber = ?");
            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                throw new Exception("Email or phone number is already registered.");
            }
            $stmt->close();

            // Password encryption for security. 
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert staff with inactive status wen the account is first created.
            $stmt = $conn->prepare("INSERT INTO SuperAdmin (Email, PasswordHash, FirstName, LastName, PhoneNumber, Status, Role, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, 'Inactive', 'Staff', NOW(), NOW())");
            $stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $phone);
            $stmt->execute();
            $stmt->close();

            // Pop-up and redirect after the registration process is complete.
            echo "<script>
                    alert('Registration successful! Your account is inactive and will be activated by admin.');
                    window.location.href='login.php';
                </script>";
            exit;

        // Catch and display any unforeseen error that may occure during validation.
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
        <!-- name of the page -->
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
                    <!-- Input field for first name -->
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($firstName ?? ''); ?>">
                    </div>

                    <!--  Input field for last name-->
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($lastName ?? ''); ?>">
                    </div>

                    <!-- Input field for email-->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>

                    <!-- Input field for phone number -->
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                    </div>

                    <!-- Input field for password -->
                    <div class="form-group password-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <i class="fa-solid fa-eye toggle-password" toggle="#password"></i>
                    </div>

                    <!-- Input field for confirm password -->
                    <div class="form-group password-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <i class="fa-solid fa-eye toggle-password" toggle="#confirm_password"></i>
                    </div>
                    
                    <!-- Register button to submit the form data and process it further -->
                    <button type="submit" class="login-btn">Register</button>
                </form>

                <!-- Option to login if the user has already and account and wants to login rather than registering a new account-->
                <p class="register-link">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            </div>
        </main>

        <!-- Footer to be displayed at the bottom of the page-->
        <?php include '../Common/footer.php'; ?>

        <!-- Javascript logic to hide and show the password when clicked on the eye icon -->
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
