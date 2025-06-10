<?php
$newPassword = "admin@deeken.com";
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
echo $hashedPassword;
?>