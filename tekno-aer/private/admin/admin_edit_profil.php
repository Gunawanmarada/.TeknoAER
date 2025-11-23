<?php
session_start();
// PERBAIKAN PATH: Sesuaikan path ke file db.php yang benar
// Asumsi path yang benar adalah: ../../config/db.php
include '../../config/db.php'; 

// Cek jika koneksi gagal (walaupun jarang, ini untuk keamanan)
if (!isset($conn)) {
    die("Koneksi database gagal. Periksa file db.php.");
}

// Cegah akses tanpa login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data admin yang sedang login
$admin_id = $_SESSION['admin_id'];

// Menggunakan Prepared Statement untuk keamanan dan mencegah SQL Injection
// Jika Anda tidak ingin menggunakan Prepared Statement, setidaknya gunakan fungsi real_escape_string
$admin_data = null;
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_data = $result->fetch_assoc();
    $stmt->close();
} else {
    // Fallback jika prepared statement gagal atau koneksi bermasalah
    $admin_data = $conn->query("SELECT * FROM admin WHERE admin_id = $admin_id")->fetch_assoc();
}


if (!$admin_data) {
    // Admin tidak ditemukan (Error serius)
    // Arahkan ke logout jika data sesi valid tapi data di DB hilang
    header("Location: logout.php");
    exit;
}

// Path Gambar Profil Admin saat ini
$foto_profil_saat_ini = !empty($admin_data['foto_profil']) ? 
    '../assets/uploads/' . htmlspecialchars($admin_data['foto_profil']) : 
    '../assets/images/default_admin.jpg'; // Path default

// Pesan status (Sukses atau Error)
$status_message = '';
if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']); // Hapus setelah ditampilkan
}

// Path Default Image (Disalin dari dashboard.php)
$default_path = '../assets/images/default_admin.jpg'; 
$primary_color = '#6C48C5'; // Warna Utama

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil Admin | TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
    <style>
        /* CSS DARI DASHBOARD.PHP */
        :root {
            --primary-color: <?= $primary_color; ?>; 
            --secondary-color: #C68FE6; 
            --text-dark: #333;
            --bg-light: #f4f6f9;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            display: flex; 
            color: var(--text-dark);
        }

        .sidebar {
            width: 250px;
            background-color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .profile-avatar-wrapper { 
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .profile-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .nav-link {
            padding: 12px 15px;
            margin-bottom: 5px;
            color: var(--text-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: background-color 0.2s, color 0.2s;
        }

        .nav-link.active, .nav-link:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        .content-container {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        /* AKHIR CSS DARI DASHBOARD.PHP */

        /* CSS KHUSUS FORM */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input[type="file"] {
            padding: 10px 0;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .current-photo {
            margin-top: 10px;
            text-align: center;
            padding: 15px;
            border: 1px dashed #ddd;
            border-radius: 8px;
        }

        .current-photo img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .btn-submit {
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        .btn-submit:hover {
            background-color: #5b46b6; /* Warna ungu sedikit lebih gelap */
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo-section" style="cursor: default;">
        <div class="profile-avatar-wrapper">
            <img src="<?= htmlspecialchars($foto_profil_saat_ini); ?>" alt="Admin Foto">
        </div>
        <div>
            <strong><?= htmlspecialchars($admin_data['nama_lengkap']); ?></strong>
            <div style="font-size: 0.8em; color: #888;">Role: <?= htmlspecialchars($admin_data['role']); ?></div>
        </div>
    </div>
    
    <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
    <a href="admin_edit_profil.php" class="nav-link active">👤 **Edit Profil**</a>
    <a href="logout.php" class="nav-link" style="margin-top: auto;">➡️ Keluar</a>
</div>

<div class="main-content">
    
    <div class="content-container">
        
        <h2>📝 Edit Profil Admin</h2>
        
        <?php if ($status_message): ?>
            <div class="<?= strpos($status_message, 'berhasil') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?= $status_message; ?>
            </div>
        <?php endif; ?>
        
        <form action="admin_edit_profil_process.php" method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap:</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($admin_data['nama_lengkap']); ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin_data['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_data['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="password_baru">Password Baru (Kosongkan jika tidak ingin diubah):</label>
                <input type="password" id="password_baru" name="password_baru" placeholder="Masukkan password baru">
                <small style="color: #888;">*Jika diisi, password lama akan diganti.</small>
            </div>
            
            <div class="form-group">
                <label for="foto_profil">Ubah Foto Profil:</label>
                <div class="current-photo">
                    <img src="<?= htmlspecialchars($foto_profil_saat_ini); ?>" alt="Foto Profil Saat Ini">
                    <p style="font-size: 0.9em; margin: 0; color: #555;">Foto Profil Saat Ini</p>
                </div>
                <input type="file" id="foto_profil" name="foto_profil" accept="image/*">
                <small style="color: #888;">*Hanya file JPG, JPEG, PNG, GIF yang diizinkan.</small>
            </div>

            <button type="submit" class="btn-submit">Simpan Perubahan</button>
        </form>
    </div>

</div>

</body>
</html>