<?php
session_start();
// 🛠️ PERBAIKAN 1: Mengubah path ke db.php
include '../config/db.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Login - TeknoAER Petugas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* =========================================================
    ROOT & GLOBAL STYLE (Warna Konsisten)
========================================================= */
:root {
    --color-primary: #1e847f; /* Warna hijau Teknoaer */
    --color-secondary: #004d40;
    --color-light: #ffffff;
    --font-poppins: 'Poppins', sans-serif;
}

body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    font-family: var(--font-poppins);
    /* 🛠️ PERBAIKAN 2: Mengubah path background */
    background: url('assets/bg/bg.png') no-repeat center/cover; 
    overflow: hidden;
}

/* =========================================================
    MAIN WRAPPER (Satu Kolom Tengah)
========================================================= */
.wrapper {
    width: 90%;
    max-width: 600px; /* Ukuran yang pas untuk tampilan ini */
    min-height: 50vh;
    margin: auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center; /* Memastikan konten di tengah */
    text-align: center;
    
    /* Efek Glassmorphism: Background transparan + blur */
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    border-radius: 20px;
    padding: 50px 40px;
    
    animation: fadeSlide 1s ease-out;
}

/* =========================================================
    HEADER TEXT
========================================================= */
h1 {
    font-size: 54px;
    color: var(--color-light);
    margin: 0;
    font-weight: 700;
    line-height: 1.2;
}

h2 {
    font-size: 36px;
    color: var(--color-light);
    margin: 10px 0 30px;
    font-weight: 700;
}

p {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 30px;
}

/* =========================================================
    NAVIGATION BUTTONS/LINKS
========================================================= */
.link-option {
    display: block;
    text-decoration: none;
    font-size: 22px;
    font-weight: 600;
    color: var(--color-light);
    padding: 15px 40px;
    margin: 10px 0;
    border-radius: 10px;
    transition: 0.3s;
    /* Efek tombol semi-transparan */
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
}

.link-option:hover {
    background: rgba(255, 255, 255, 0.3);
    color: var(--color-secondary); /* Ubah warna teks saat hover agar menonjol */
    transform: translateY(-2px);
}

/* Link tambahan */
.back-link {
    margin-top: 30px;
}
.back-link a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 14px;
    transition: 0.3s;
}
.back-link a:hover {
    color: var(--color-light);
}

/* =========================================================
    ANIMATION
========================================================= */
@keyframes fadeSlide {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>

<body>
<div class="wrapper">

    <h1>SELAMAT DATANG</h1>
    <h2>PETUGAS TEKNOAER</h2>
    
    <p>Silahkan pilih peran login Anda untuk melanjutkan:</p>

    <a href="admin/login.php" class="link-option">
        <i class="fas fa-user-shield"></i> LOGIN ADMIN
    </a>
    
    <a href="pengantar/login.php" class="link-option">
        <i class="fas fa-shipping-fast"></i> LOGIN KURIR
    </a>

</div>
</body>
</html>