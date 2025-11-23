<?php
// File: tekno-aer/private/index.php

// Mencegah caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Mengarahkan ke beranda_admin.php
header("Location: ../beranda_admin.php");
exit;
?>