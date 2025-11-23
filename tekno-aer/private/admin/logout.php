<?php
session_start();

// Hanya hapus session milik admin, bukan user
unset($_SESSION['admin_id']);
unset($_SESSION['nama']);
unset($_SESSION['role']);

// redirect ke halaman login admin
header("Location: login.php");
exit;
?>
