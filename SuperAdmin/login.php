<?php
    // session at the start of the php tag
    session_start();

    //database connection.
    require '../Common/connection.php';

    // string to hold error messafes
    $error = '';

    // Handle form submission after te login button is pressed.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        //check if the email contains characters like '<','>' etc and remove them.
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        $password = $_POST['password'] ?? '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';

        // Check if the user has left any field empty before submitting the form.
        if (empty($email) || empty($password) || empty($role)) {
            $error = "Please fill in all fields.";
        } 

        //process the data when all fields are submitted
        else {

            //statement to select the admin based on the role, email.
            $stmt = $conn->prepare("SELECT SuperAdminID, PasswordHash, FirstName, LastName, Status, Role 
                                    FROM SuperAdmin 
                                    WHERE Email = ? AND Role = ?");

            //executed if the email and roll are found in the database                        
            if ($stmt) {
                $stmt->bind_param("ss", $email, $role);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($id, $hash, $firstName, $lastName, $status, $dbRole);
                    $stmt->fetch();

                    if ($status !== 'Active') {
                        // Inactive staff triggers modal
                        $error = "inactive"; 
                    } elseif (password_verify($password, $hash)) {
                        // Successful login
                        $firstName = trim($firstName);
                        $lastName = trim($lastName);
                        $displayName = (empty($lastName) || strtolower($firstName) === strtolower($lastName)) 
                                    ? $firstName : $firstName . ' ' . $lastName;

                        // Store session variables
                        $_SESSION['SuperAdminID'] = $id;
                        $_SESSION['SuperAdminName'] = $displayName;
                        $_SESSION['Role'] = $dbRole;
                        $_SESSION['SuperAdminEmail'] = $email; // <-- NEW
                        session_regenerate_id(true);

                        // Log time in Nepali timezone
                        $utc = new DateTime("now", new DateTimeZone("UTC"));
                        $utc->setTimezone(new DateTimeZone("Asia/Kathmandu"));
                        $login_at = $utc->format('Y-m-d H:i:s');

                        // Update last login
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

                        // Redirect based on role
                        $roleNormalized = strtolower(trim($dbRole));
                        if ($roleNormalized === 'admin') {
                            header("Location: AdminPanel/dashboard.php");
                            exit;
                        } else { // Staff
                            header("Location: StaffPanel/staff_dashboard.php");
                            exit;
                        }
                    } 
                    else {
                        $error = "Invalid email or password.";
                    }
                } else {
                    $error = "Invalid email or password.";
                }

                $stmt->close();
            } else {
                $error = "Internal error. Please try again later.";
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
        <!-- Name of th epage -->
        <title>Admin Login</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <main>
            <div class="login-container">
                <h2>Admin Login</h2>

                <?php if($error && $error !== "inactive"): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <!-- Field to enter the email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter email" required value="<?php echo isset($email)?htmlspecialchars($email):''; ?>">
                    </div>

                    <!-- Field to enter the password -->
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>

                    <!-- Dropdown to select the role -->
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>

                    <!-- Submit button to process the data further and login  -->
                    <button type="submit" class="login-btn">Login</button>
                </form>

                <!-- Option to register the account for new user -->
                <p class="register-link">
                    Don’t have an account yet? <a href="register.php">Register here</a>
                </p>
            </div>
        </main>

        <!-- Modal for inactive staff -->
        <div id="inactiveModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h3>Your account is inactive. Please wait for admin activation to login.</h3>
            </div>
        </div>

        <!-- Footer file to display at the end of page -->
        <?php include '../Common/footer.php'; ?>

        <!-- Javascript to handle the inacitve status message -->
        <script>
            const modal = document.getElementById('inactiveModal');
            const closeBtn = document.querySelector('.modal-close');

            <?php if($error === "inactive"): ?>
            modal.style.display = "block";
            <?php endif; ?>

            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            }
        </script>
    </body>
</html>
