<?php
session_start();
require '../../Common/connection.php';
header('Content-Type: text/html; charset=utf-8');

// ------------------ Session Check ------------------
if (!isset($_SESSION['UserID']) && !isset($_SESSION['CAdminID'])) {
    header("Location: ../login.php");
    exit;
}

$companyId = $_SESSION['CompanyID'] ?? null;
$changedBy = $_SESSION['CAdminID'] ?? $_SESSION['UserID'] ?? null;

// ------------------ Handle AJAX Requests ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (ob_get_length()) ob_clean(); // clear any previous output
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => '', 'data' => null];

    try {
        if (!$companyId || !$changedBy) throw new Exception('Invalid session. Please login again.');

        $action = $_POST['action'] ?? '';
        $customerId = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;

        // ------------------ Add / Update ------------------
        if ($action === 'add' || $action === 'edit') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $customerCompany = trim($_POST['customer_company'] ?? null);
            $email = trim($_POST['email'] ?? '');
            $phoneNumber = trim($_POST['phone_number'] ?? '');

            if (!$firstName || !$lastName || !$email) {
                throw new Exception('First Name, Last Name, and Email are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            if ($phoneNumber && !preg_match('/^[0-9+\-\s]{5,20}$/', $phoneNumber)) {
                throw new Exception('Invalid phone number.');
            }

            // ------------------ Check duplicates ------------------
            if ($action === 'edit') {
                $dupStmt = $conn->prepare("SELECT customer_id FROM customers WHERE company_id = ? AND (email = ? OR phone_number = ?) AND customer_id != ?");
                $dupStmt->bind_param("issi", $companyId, $email, $phoneNumber, $customerId);
            } else {
                $dupStmt = $conn->prepare("SELECT customer_id FROM customers WHERE company_id = ? AND (email = ? OR phone_number = ?)");
                $dupStmt->bind_param("iss", $companyId, $email, $phoneNumber);
            }
            $dupStmt->execute();
            if ($conn->error) throw new Exception('Database error: ' . $conn->error);
            $dupStmt->store_result();
            if ($dupStmt->num_rows > 0) {
                throw new Exception($phoneNumber && $email ? 'Email or Phone number already exists for another customer.' : ($email ? 'Email already exists.' : 'Phone number already exists.'));
            }
            $dupStmt->close();

            if ($action === 'add') {
                // Insert customer
                $stmt = $conn->prepare("INSERT INTO customers (company_id, first_name, last_name, customer_company, email, phone_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssi", $companyId, $firstName, $lastName, $customerCompany, $email, $phoneNumber, $changedBy);
                $stmt->execute();
                if ($conn->error) throw new Exception('Database error: ' . $conn->error);
                $customerId = $stmt->insert_id;
                $stmt->close();

                // History
                $newData = json_encode(['first_name'=>$firstName,'last_name'=>$lastName,'customer_company'=>$customerCompany,'email'=>$email,'phone_number'=>$phoneNumber]);
                $historyStmt = $conn->prepare("INSERT INTO customer_history (customer_id, company_id, old_data, new_data, changed_by, operation) VALUES (?, ?, ?, ?, ?, ?)");
                $operation='create';
                $oldData=json_encode(null);
                $historyStmt->bind_param("iissis", $customerId, $companyId, $oldData, $newData, $changedBy, $operation);
                $historyStmt->execute();
                if ($conn->error) throw new Exception('Database error: ' . $conn->error);
                $historyStmt->close();

                $response['data'] = [
                    'id' => $customerId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'customer_company' => $customerCompany,
                    'email' => $email,
                    'phone_number' => $phoneNumber
                ];
                $response['success'] = true;
                $response['message'] = 'Customer added successfully.';
            } else { // edit
                // Fetch old data
                $oldStmt = $conn->prepare("SELECT first_name,last_name,customer_company,email,phone_number FROM customers WHERE customer_id=?");
                $oldStmt->bind_param("i",$customerId);
                $oldStmt->execute();
                if ($conn->error) throw new Exception('Database error: ' . $conn->error);
                $oldStmt->bind_result($oldFirst,$oldLast,$oldCompany,$oldEmail,$oldPhone);
                if (!$oldStmt->fetch()) {
                    throw new Exception('Customer not found.');
                }
                $oldData = json_encode(['first_name'=>$oldFirst,'last_name'=>$oldLast,'customer_company'=>$oldCompany,'email'=>$oldEmail,'phone_number'=>$oldPhone]);
                $oldStmt->close();

                // Update customer
                $stmt = $conn->prepare("UPDATE customers SET first_name=?, last_name=?, customer_company=?, email=?, phone_number=? WHERE customer_id=?");
                $stmt->bind_param("sssssi",$firstName,$lastName,$customerCompany,$email,$phoneNumber,$customerId);
                $stmt->execute();
                if ($conn->error) throw new Exception('Database error: ' . $conn->error);
                $stmt->close();

                // Insert history
                $newData = json_encode(['first_name'=>$firstName,'last_name'=>$lastName,'customer_company'=>$customerCompany,'email'=>$email,'phone_number'=>$phoneNumber]);
                $historyStmt = $conn->prepare("INSERT INTO customer_history (customer_id, company_id, old_data, new_data, changed_by, operation) VALUES (?, ?, ?, ?, ?, ?)");
                $operation='update';
                $historyStmt->bind_param("iissis", $customerId, $companyId, $oldData, $newData, $changedBy, $operation);
                $historyStmt->execute();
                if ($conn->error) throw new Exception('Database error: ' . $conn->error);
                $historyStmt->close();

                $response['data'] = [
                    'id' => $customerId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'customer_company' => $customerCompany,
                    'email' => $email,
                    'phone_number' => $phoneNumber
                ];
                $response['success'] = true;
                $response['message'] = 'Customer updated successfully.';
            }
        }

        // ------------------ Delete ------------------
        elseif ($action==='delete' && $customerId) {
            // Fetch old data before deletion
            $oldStmt = $conn->prepare("SELECT first_name,last_name,customer_company,email,phone_number FROM customers WHERE customer_id=?");
            $oldStmt->bind_param("i",$customerId);
            $oldStmt->execute();
            if ($conn->error) throw new Exception('Database error: ' . $conn->error);
            $oldStmt->bind_result($oldFirst,$oldLast,$oldCompany,$oldEmail,$oldPhone);
            if (!$oldStmt->fetch()) {
                throw new Exception('Customer not found.');
            }
            $oldData = json_encode(['first_name'=>$oldFirst,'last_name'=>$oldLast,'customer_company'=>$oldCompany,'email'=>$oldEmail,'phone_number'=>$oldPhone]);
            $oldStmt->close();

            // Insert into history first
            $newData = json_encode(null);
            $operation = 'delete';
            $historyStmt = $conn->prepare("INSERT INTO customer_history (customer_id, company_id, old_data, new_data, changed_by, operation) VALUES (?, ?, ?, ?, ?, ?)");
            $historyStmt->bind_param("iissis", $customerId, $companyId, $oldData, $newData, $changedBy, $operation);
            $historyStmt->execute();
            if ($conn->error) throw new Exception('Database error: ' . $conn->error);
            $historyStmt->close();

            // Then delete from customers
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=?");
            $stmt->bind_param("i",$customerId);
            $stmt->execute();
            if ($conn->error) throw new Exception('Database error: ' . $conn->error);
            $stmt->close();

            $response['success'] = true;
            $response['message'] = 'Customer deleted successfully.';
        } else {
            throw new Exception('Invalid action.');
        }

    } catch(Exception $e){
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit; // IMPORTANT: stop further output
}

// ------------------ Fetch customers for list ------------------
$customerList=[];
$stmt = $conn->prepare("SELECT * FROM customers WHERE company_id = ? ORDER BY customer_id DESC");
$stmt->bind_param("i", $companyId);
$stmt->execute();
if ($conn->error) { /* handle error if needed */ }
$result = $stmt->get_result();
if($result){
    while($row=$result->fetch_assoc()){
        $customerList[]=$row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Management</title>
<style>
table { width: 90%; margin:auto; border-collapse: collapse; }
th,td { border:1px solid #ddd; padding:8px; text-align:left; }
th { background:#f4f4f4; }
button { padding:5px 10px; margin:2px; }
#modalForm { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:20px; border:1px solid #333; z-index:1000; width:350px; }
#overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; }
input { width:100%; padding:5px; margin:5px 0; }
#responseMessage { text-align:center; margin:10px; font-weight:bold; }
#responseMessage.success { color: green; }
#responseMessage.error { color: red; }
</style>
</head>
<body>

<h2 style="text-align:center;">Customers</h2>
<button onclick="openModal()">Add Customer</button>

<div id="responseMessage"></div>

<table>
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Actions</th>
        </tr>
    </thead>
    <tbody id="customerTable">
        <?php foreach($customerList as $cust): ?>
        <tr data-id="<?php echo $cust['customer_id']; ?>">
            <td><?php echo $cust['customer_id']; ?></td>
            <td><?php echo htmlspecialchars($cust['first_name'].' '.$cust['last_name']); ?></td>
            <td><?php echo htmlspecialchars($cust['customer_company']); ?></td>
            <td><?php echo htmlspecialchars($cust['email']); ?></td>
            <td><?php echo htmlspecialchars($cust['phone_number']); ?></td>
            <td>
                <button onclick="editCustomer(<?php echo $cust['customer_id']; ?>)">Edit</button>
                <button onclick="deleteCustomer(<?php echo $cust['customer_id']; ?>)">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="overlay"></div>
<div id="modalForm">
    <h3 id="modalTitle">Add Customer</h3>
    <form id="custForm">
        <input type="hidden" id="customer_id" name="customer_id">
        <label>First Name</label><input type="text" name="first_name" id="first_name" required>
        <label>Last Name</label><input type="text" name="last_name" id="last_name" required>
        <label>Company</label><input type="text" name="customer_company" id="customer_company">
        <label>Email</label><input type="email" name="email" id="email" required>
        <label>Phone</label><input type="text" name="phone_number" id="phone_number">
        <button type="submit">Save</button>
        <button type="button" onclick="closeModal()">Cancel</button>
    </form>
</div>

<script>
let currentAction='add';

function openModal(){
    currentAction='add';
    document.getElementById('modalTitle').innerText='Add Customer';
    document.getElementById('custForm').reset();
    document.getElementById('overlay').style.display='block';
    document.getElementById('modalForm').style.display='block';
}

function closeModal(){
    document.getElementById('overlay').style.display='none';
    document.getElementById('modalForm').style.display='none';
}

function editCustomer(id){
    currentAction='edit';
    let row=document.querySelector(`tr[data-id='${id}']`);
    document.getElementById('modalTitle').innerText='Edit Customer';
    document.getElementById('customer_id').value=id;
    let nameParts=row.children[1].innerText.split(' ');
    document.getElementById('first_name').value=nameParts[0];
    document.getElementById('last_name').value=nameParts[1]||'';
    document.getElementById('customer_company').value=row.children[2].innerText;
    document.getElementById('email').value=row.children[3].innerText;
    document.getElementById('phone_number').value=row.children[4].innerText;
    document.getElementById('overlay').style.display='block';
    document.getElementById('modalForm').style.display='block';
}

function deleteCustomer(id){
    if(confirm('Are you sure you want to delete this customer?')){
        let formData=new FormData();
        formData.append('ajax',1);
        formData.append('action','delete');
        formData.append('customer_id',id);

        fetch('add_customers.php',{method:'POST',body:formData})
        .then(res=>res.json())
        .then(res=>{
            let msgEl = document.getElementById('responseMessage');
            msgEl.innerText = res.message;
            msgEl.className = res.success ? 'success' : 'error';
            if(res.success) {
                let row = document.querySelector(`tr[data-id='${id}']`);
                if (row) row.remove();
            }
            setTimeout(() => { 
                msgEl.innerText = ''; 
                msgEl.className = ''; 
            }, 10000);
        }).catch(err=>{
            let msgEl = document.getElementById('responseMessage');
            msgEl.innerText = 'Unexpected error: '+err;
            msgEl.className = 'error';
            setTimeout(() => { 
                msgEl.innerText = ''; 
                msgEl.className = ''; 
            }, 10000);
        });
    }
}

document.getElementById('custForm').addEventListener('submit',function(e){
    e.preventDefault();
    let formData=new FormData(this);
    formData.append('ajax',1);
    formData.append('action',currentAction);

    fetch('add_customers.php',{method:'POST',body:formData})
    .then(res=>res.json())
    .then(res=>{
        let msgEl = document.getElementById('responseMessage');
        msgEl.innerText = res.message;
        msgEl.className = res.success ? 'success' : 'error';
        closeModal();
        if(res.success && res.data) {
            let tbody = document.querySelector('#customerTable');
            if (currentAction === 'add') {
                // Append new row
                let newRow = document.createElement('tr');
                newRow.setAttribute('data-id', res.data.id);
                newRow.innerHTML = `
                    <td>${res.data.id}</td>
                    <td>${escapeHtml(res.data.first_name + ' ' + res.data.last_name)}</td>
                    <td>${escapeHtml(res.data.customer_company || '')}</td>
                    <td>${escapeHtml(res.data.email)}</td>
                    <td>${escapeHtml(res.data.phone_number || '')}</td>
                    <td><button onclick="editCustomer(${res.data.id})">Edit</button> <button onclick="deleteCustomer(${res.data.id})">Delete</button></td>
                `;
                tbody.insertBefore(newRow, tbody.firstChild); // Add to top since ordered DESC
            } else if (currentAction === 'edit') {
                // Update existing row
                let row = document.querySelector(`tr[data-id='${res.data.id}']`);
                if (row) {
                    row.children[1].textContent = res.data.first_name + ' ' + res.data.last_name;
                    row.children[2].textContent = res.data.customer_company || '';
                    row.children[3].textContent = res.data.email;
                    row.children[4].textContent = res.data.phone_number || '';
                }
            }
        }
        setTimeout(() => { 
            msgEl.innerText = ''; 
            msgEl.className = ''; 
        }, 10000);
    }).catch(err=>{
        let msgEl = document.getElementById('responseMessage');
        msgEl.innerText = 'Unexpected error: '+err;
        msgEl.className = 'error';
        closeModal();
        setTimeout(() => { 
            msgEl.innerText = ''; 
            msgEl.className = ''; 
        }, 10000);
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>