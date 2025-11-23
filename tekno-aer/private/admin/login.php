<?php
session_start();
// 🛠️ PERBAIKAN 1: Mengubah path ke db.php
include '../../config/db.php';

$error_username = '';
$error_password = '';
$username_value = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $username_value = $username; // Simpan nilai username yang diinput

    $stmt = $conn->prepare("SELECT admin_id, nama_lengkap, password FROM admin WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Pengecekan password
        if ($password === $row['password']) {
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['nama'] = $row['nama_lengkap'];
            $_SESSION['role'] = 'admin';
            header("Location: dashboard.php");
            exit;
        } else {
            $error_password = "Password salah! Coba lagi.";
        }
    } else {
        $error_username = "Username tidak ditemukan!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin - TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* =========================================================
    ROOT & GLOBAL STYLE
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
    /* 🛠️ PERBAIKAN 2: Mengubah path ke bg.png */
    background: url('../assets/bg/bg.png') no-repeat center/cover;
    overflow: hidden;
}

/* =========================================================
    MAIN WRAPPER & PANELS
========================================================= */
.wrapper {
    width: 90%;
    max-width: 1200px;
    height: 85vh;
    margin: auto;
    display: flex;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    border-radius: 20px;
    overflow: hidden;
}

/* LEFT PANEL (BRANDING) */
.left-panel {
    width: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px;
    background: linear-gradient(
        90deg,
        rgba(0,0,0,0.6), 
        rgba(0,0,0,0.35),
        rgba(0,0,0,0.15)
    );
    color: var(--color-light);
    animation: fadeSlide 1s ease-out;
}

.left-panel h1 {
    font-size: 58px; 
    margin: 0;
    font-weight: 700;
    line-height: 1.1;
}

.left-panel h2 {
    font-size: 34px; 
    margin: 5px 0 20px;
    font-weight: 600;
    opacity: 0.9;
}

.left-panel p {
    font-size: 17px;
    color: rgba(255,255,255,0.7);
    max-width: 450px;
    line-height: 1.6; 
}

/* RIGHT PANEL (FORM LOGIN) */
.right-panel {
    width: 50%;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    padding: 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    animation: fadeSlide 1s ease-out;
}

.right-panel h3 {
    color: var(--color-light);
    text-align: center;
    margin-top: 10px;
    font-size: 30px;
    font-weight: 700;
    margin-bottom: 30px;
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
    border-bottom: 2px solid rgba(255, 255, 255, 0.5); 
    color: var(--color-light);
    outline: none;
    transition: border-bottom-color 0.3s ease;
}

/* Efek fokus */
.login-input:focus {
    border-bottom-color: var(--color-light);
}

/* Placeholder */
.login-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

/* Tombol Login */
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
    ERROR STATE (Ditingkatkan)
========================================================= */
.error-message {
    font-size: 14px;
    color: var(--color-error); 
    margin-top: 5px;
    text-align: left;
    font-weight: 600;
}

/* Visualisasi Error pada Input Field */
.login-input.error-border {
    border-bottom-color: var(--color-error);
}

/* Ikon Password */
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
    ANIMATION (Tetap)
========================================================= */
@keyframes fadeSlide {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

</style>
</head>

<body>
<div class="wrapper">

    <div class="left-panel">
        <h1>TEKNOAER</h1>
        <h2>Admin Dashboard</h2>
        <p>Sistem manajemen data barang, pesanan, kurir dan laporan keuangan — semua dalam satu panel admin modern.</p>
        <p style="margin-top: 30px; font-size: 15px; font-style: italic;">"Halo admin tercinta, silahkan kamu login dulu."</p>
    </div>

    <div class="right-panel">
        <h3>LOGIN ADMIN</h3>

        <form method="post">
            
            <div class="input-group">
                <input 
                    type="text" 
                    name="username" 
                    placeholder="Username Admin" 
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
                <?php endif; ?>
            </div>
            
            <button type="submit" name="login" class="btn-login">LOGIN</button>
        </form>

        <div class="login-options">
            <a href="../beranda_admin.php">Kembali ke Beranda</a>
        </div>
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