<?php
$hashedPassword = password_hash("admin@123", PASSWORD_BCRYPT);
echo $hashedPassword;
?>
