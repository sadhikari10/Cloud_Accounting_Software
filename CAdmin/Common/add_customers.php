<?php
session_start();
require '../../Common/connection.php';

// ------------------ Session Check ------------------
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'] ?? null;
$createdBy = $_SESSION['CAdminID'] ?? $_SESSION['UserID'] ?? null;

// ------------------ Handle AJAX Submission ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (ob_get_length()) ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        if (!$companyId || !$createdBy) throw new Exception('Invalid session. Please login again.');

        // ------------------ Sanitize Inputs ------------------
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $customerCompany = trim($_POST['customer_company'] ?? null); // optional
        $email = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');

        // ------------------ Validation ------------------
        if (!$firstName || !$lastName || !$email) {
            throw new Exception('Please fill in all required fields (First Name, Last Name, Email).');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        if ($phoneNumber && !preg_match('/^[0-9+\-\s]{5,20}$/', $phoneNumber)) {
            throw new Exception('Invalid phone number format.');
        }

        // ------------------ Check for duplicate email ------------------
        $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE company_id = ? AND email = ?");
        $checkStmt->bind_param("is", $companyId, $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            throw new Exception('Email already belongs to another customer.');
        }
        $checkStmt->close();

        // ------------------ Check for duplicate phone number (if provided) ------------------
        if ($phoneNumber) {
            $checkPhoneStmt = $conn->prepare("SELECT customer_id FROM customers WHERE company_id = ? AND phone_number = ?");
            $checkPhoneStmt->bind_param("is", $companyId, $phoneNumber);
            $checkPhoneStmt->execute();
            $checkPhoneStmt->store_result();
            if ($checkPhoneStmt->num_rows > 0) {
                throw new Exception('Phone number already belongs to another customer.');
            }
            $checkPhoneStmt->close();
        }

        // ------------------ Insert into customers ------------------
        $stmt = $conn->prepare("INSERT INTO customers 
            (company_id, first_name, last_name, customer_company, email, phone_number, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Database error: ' . $conn->error);

        $stmt->bind_param("isssssi", $companyId, $firstName, $lastName, $customerCompany, $email, $phoneNumber, $createdBy);

        if ($stmt->execute()) {
            $customerId = $stmt->insert_id;

            // ------------------ Prepare JSON data for history ------------------
            $oldData = null; // new customer
            $newData = json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'customer_company' => $customerCompany,
                'email' => $email,
                'phone_number' => $phoneNumber
            ]);
            $operation = 'create';

            // ------------------ Insert into customer_history ------------------
            $historyStmt = $conn->prepare("INSERT INTO customer_history 
                (customer_id, company_id, old_data, new_data, changed_by, operation) 
                VALUES (?, ?, ?, ?, ?, ?)");
            if (!$historyStmt) throw new Exception('Database error (history): ' . $conn->error);

            $historyStmt->bind_param("iissis", $customerId, $companyId, $oldData, $newData, $createdBy, $operation);
            $historyStmt->execute();
            $historyStmt->close();

            $response['success'] = true;
            $response['message'] = 'Customer added successfully.';
        } else {
            throw new Exception('Database insert failed: ' . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    $conn->close();
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Customer</title>
<style>
    form { max-width: 400px; margin: 20px auto; }
    label { display: block; margin-top: 10px; }
    input { width: 100%; padding: 8px; margin-top: 5px; }
    button { margin-top: 15px; padding: 10px 15px; }
    #responseMessage { margin-top: 15px; font-weight: bold; text-align:center; }
</style>
</head>
<body>

<h2 style="text-align:center;">Add New Customer</h2>

<a href="../AdminPanel/dashboard.php">Back to dashboard</a>

<form id="customerForm">
    <label>First Name *</label>
    <input type="text" name="first_name" required>

    <label>Last Name *</label>
    <input type="text" name="last_name" required>

    <label>Customer Company</label>
    <input type="text" name="customer_company" placeholder="Optional">

    <label>Email *</label>
    <input type="email" name="email" required>

    <label>Phone Number</label>
    <input type="text" name="phone_number" placeholder="Optional">

    <button type="submit">Add Customer</button>
</form>

<div id="responseMessage" style="display:none;"></div>

<script>
document.getElementById('customerForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    data.append('ajax', 1); // mark as AJAX request

    fetch('add_customers.php', {
        method: 'POST',
        body: data
    })
    .then(res => res.text())
    .then(text => {
        try {
            const response = JSON.parse(text);
            const msgDiv = document.getElementById('responseMessage');
            msgDiv.style.display = 'block';
            msgDiv.innerText = response.message;
            msgDiv.style.color = response.success ? 'green' : 'red';

            setTimeout(() => { msgDiv.style.display = 'none'; }, 10000);
            if(response.success) form.reset();
        } catch (err) {
            console.error("JSON parse error:", err, text);
            const msgDiv = document.getElementById('responseMessage');
            msgDiv.style.display = 'block';
            msgDiv.innerText = "Invalid response received. Please check console.";
            msgDiv.style.color = 'red';
            setTimeout(() => { msgDiv.style.display = 'none'; }, 10000);
        }
    })
    .catch(err => {
        console.error("Fetch error:", err);
        const msgDiv = document.getElementById('responseMessage');
        msgDiv.style.display = 'block';
        msgDiv.innerText = 'An unexpected error occurred: ' + err.message;
        msgDiv.style.color = 'red';
        setTimeout(() => { msgDiv.style.display = 'none'; }, 10000);
    });
});
</script>

</body>
</html>
