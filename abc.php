<?php
// DB connection
$host = 'localhost';
$db   = 'star_accounting';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Fetch all COA records ordered by ID
$sql = "SELECT 
            id, 
            company_id, 
            account_code, 
            account_name, 
            parent_id, 
            normal_side, 
            is_system, 
            is_active, 
            created_by, 
            updated_by, 
            created_at, 
            updated_at
        FROM chart_of_accounts
        ORDER BY id ASC";

$stmt = $pdo->query($sql);
$accounts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chart of Accounts - Detailed List</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 95%; margin: 30px auto; font-size: 14px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>

<h2 style="text-align:center;">Chart of Accounts (Detailed List)</h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Company ID</th>
            <th>Account Code</th>
            <th>Account Name</th>
            <th>Parent ID</th>
            <th>Normal Side</th>
            <th>Is System</th>
            <th>Is Active</th>
            <th>Created By</th>
            <th>Updated By</th>
            <th>Created At</th>
            <th>Updated At</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['company_id']) ?></td>
                <td><?= htmlspecialchars($row['account_code']) ?></td>
                <td><?= htmlspecialchars($row['account_name']) ?></td>
                <td><?= htmlspecialchars($row['parent_id']) ?></td>
                <td><?= htmlspecialchars($row['normal_side']) ?></td>
                <td><?= $row['is_system'] ? 'Yes' : 'No' ?></td>
                <td><?= $row['is_active'] ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars($row['created_by']) ?></td>
                <td><?= htmlspecialchars($row['updated_by']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
