<?php
session_start();
include '../../config/db.php'; // Perbaikan Jalur File

// Cek jika sudah login, alihkan ke dashboard
if (isset($_SESSION['pengantar_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error_username = '';
$error_password = '';
$username_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // Perbaikan: Gunakan prepared statement untuk keamanan, jika tidak menggunakan prepared statement,
    // pastikan Anda sudah terhubung ke $conn dan menggunakan real_escape_string.
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; 
    $username_value = $username;

    // 1. Ambil data pengantar berdasarkan username
    $q = $conn->query("SELECT * FROM pengantar WHERE username = '$username'");
    $pengantar = $q->fetch_assoc();

    if ($pengantar) {
        // 2. Verifikasi Password (Menggunakan perbandingan Plain Text sesuai implementasi Anda)
        if ($password === $pengantar['password']) { 
            
            // Login Berhasil
            $_SESSION['pengantar_id'] = $pengantar['pengantar_id'];
            $_SESSION['pengantar_nama'] = $pengantar['nama']; 
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error_password = "Password salah. Pastikan Anda memasukkan kode yang benar.";
        }
    } else {
        $error_username = "Username tidak terdaftar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kurir - TeknoAER</title>
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
    --color-error: #ef4444; /* Merah untuk error */
    --font-poppins: 'Poppins', sans-serif;
}

body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    font-family: var(--font-poppins);
    /* Gunakan background yang sama untuk konsistensi */
    background: url('../assets/bg/bg.png') no-repeat center/cover; 
    overflow: hidden;
}

/* =========================================================
    MAIN WRAPPER (Fokus Satu Kolom)
========================================================= */
.wrapper {
    width: 90%;
    max-width: 450px; /* Ukuran lebih ringkas, cocok untuk mobile/kurir */
    min-height: 50vh;
    margin: auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    
    /* Efek Glassmorphism: Background transparan + blur */
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    border-radius: 20px;
    padding: 50px 40px;
    
    animation: fadeSlide 1s ease-out;
}

/* Header */
.wrapper h3 {
    color: var(--color-light);
    text-align: center;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 25px;
}

/* =========================================================
    FORM INPUTS & BUTTON
========================================================= */
.input-group {
    position: relative;
    margin-bottom: 25px; 
}

.login-input {
    width: 100%;
    padding: 12px 10px 12px 0;
    font-size: 16px;
    border: none;
    background: transparent;
    /* Garis bawah elegan */
    border-bottom: 2px solid rgba(255, 255, 255, 0.5); 
    color: var(--color-light);
    outline: none;
    transition: border-bottom-color 0.3s ease;
}

.login-input:focus {
    border-bottom-color: var(--color-light);
}

.login-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

/* Tombol Login (Konsisten dengan Admin) */
.btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(145deg, #ffffff, #f0f0f0);
    border: none;
    border-radius: 10px;
    color: var(--color-secondary); 
    font-size: 18px;
    cursor: pointer;
    margin-top: 25px;
    font-weight: 700;
    transition: 0.3s;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.btn-login:hover {
    background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
}

/* TAUTAN DI BAWAH FORM */
.login-options {
    text-align: center;
    margin-top: 30px;
}

.login-options a {
    color: var(--color-light);
    text-decoration: none;
    display: block;
    margin-top: 10px;
    font-size: 15px;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.login-options a:hover {
    opacity: 1;
    text-decoration: underline;
}

/* =========================================================
    ERROR STATE (Sama dengan Admin)
========================================================= */
.error-message {
    font-size: 14px;
    color: var(--color-error); /* Merah */
    margin-top: 5px;
    text-align: left;
    font-weight: 600;
}

.login-input.error-border {
    border-bottom-color: var(--color-error);
}

.toggle-password {
    position: absolute;
    right: 0;
    top: 12px;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s;
}

.toggle-password:hover {
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

    <h3><i class="fas fa-shipping-fast"></i> LOGIN KURIR</h3>

    <form method="POST">
        
        <div class="input-group">
            <input 
                type="text" 
                name="username" 
                placeholder="Username Kurir" 
                required
                class="login-input <?= $error_username ? 'error-border' : ''; ?>"
                value="<?= htmlspecialchars($username_value); ?>"
                id="username-input"
                autocomplete="username"
            >
            <?php if ($error_username): ?>
                <p class="error-message"><?= $error_username; ?></p>
            <?php endif; ?>
        </div>

        <div class="input-group">
            <input 
                type="password" 
                name="password" 
                placeholder="Kata Sandi" 
                required
                class="login-input <?= $error_password ? 'error-border' : ''; ?>"
                id="password-input"
                autocomplete="current-password"
            >
            <i class="fas fa-eye toggle-password" id="toggle-password-icon"></i>

            <?php if ($error_password): ?>
                <p class="error-message"><?= $error_password; ?></p>
                <a href="https://wa.me/qr/GOKMOEICNJM2B1" class="error-message" style="display: block; text-align: right; text-decoration: underline; margin-top: 10px;">Hubungi Admin untuk Reset Password</a>
            <?php endif; ?>
        </div>
        
        <button type="submit" name="login" class="btn-login">LOGIN</button>
    </form>

    <div class="login-options">
        <a href="../beranda_admin.php">Kembali ke Beranda</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password-input');
        const toggleIcon = document.getElementById('toggle-password-icon');

        toggleIcon.addEventListener('click', function() {
            // Toggle type antara 'password' dan 'text'
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Ubah ikon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Fokus pada field input yang error
        if (document.querySelector('.login-input.error-border')) {
            document.querySelector('.login-input.error-border').focus();
        }
    });
</script>

</body>
</html>