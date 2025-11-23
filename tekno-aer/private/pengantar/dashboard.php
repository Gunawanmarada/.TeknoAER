<?php
session_start();
// Pastikan jalur ke file db.php sudah benar (keluar 2 tingkat, masuk config)
include '../../config/db.php'; 

// Cek jika belum login, alihkan ke login
if (!isset($_SESSION['pengantar_id'])) {
    header("Location: login.php");
    exit;
}

$pengantar_id = $_SESSION['pengantar_id'];

// --- Definisi Variabel Warna untuk CSS ---
// Diambil dari nilai default di CSS yang Anda sediakan
$primary_color = '#5b46b6'; // Ungu Utama
$secondary_color = '#6c54c3'; // Ungu Lebih Terang
$text_dark = '#333';

// ************************************************************
// KODE UNTUK MENGAMBIL FOTO PROFIL DAN NAMA LENGKAP
// ************************************************************

// 1. Ambil data kurir, termasuk foto_profil
$query_pengantar = $conn->query("SELECT pengantar_id, nama, foto_profil FROM pengantar WHERE pengantar_id = '$pengantar_id'");
$pengantar = $query_pengantar->fetch_assoc();

if (!$pengantar) {
    $nama_lengkap = isset($_SESSION['pengantar_nama']) ? $_SESSION['pengantar_nama'] : 'Kurir'; 
    $foto_profil_db = '';
} else {
    $nama_lengkap = $pengantar['nama']; 
    $foto_profil_db = $pengantar['foto_profil']; 
}
// Alias nama untuk konsistensi di sidebar template
$pengantar_nama = $nama_lengkap; 

// 2. Menentukan path foto
// Jalur default ke assets di root
$default_path = '../../assets/images/default.jpg'; 
// Jalur folder upload kurir yang baru (sama dengan yang di edit_profil.php)
$upload_dir = '../assets/profil/pengantar/'; 

$photo_path = (empty($foto_profil_db) || $foto_profil_db === 'default.jpg' || !file_exists($upload_dir . $foto_profil_db)) 
             ? $default_path 
             : $upload_dir . $foto_profil_db; 

// **********************************************
// LOGIKA PHP ASLI UNTUK MENGAMBIL DATA (STATISTIK DASHBOARD)
// Data Dummy (silakan ganti dengan logika fetch data Anda yang sebenarnya)
$total_saldo = 'Rp1.250.000'; 
$total_estimasi = 'Rp250.000'; 
$total_belum_diantar = 5; 
$klaim_berhasil = 1; 
$tagihan_cod = 'Rp8.400.000'; 
$pengeluaran_op_fiktif = 'Rp495.300'; 
// **********************************************
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengantar | KiriminAja-Clone</title>
    <style>
        /* CSS DARI DASHBOARD UNTUK KONSISTENSI */
        :root {
            --primary-color: <?= $primary_color; ?>; 
            --secondary-color: <?= $secondary_color; ?>; 
            --text-dark: <?= $text_dark; ?>;
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

        /* Sidebar Style */
        .sidebar { width: 250px; background-color: white; padding: 20px; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05); min-height: 100vh; display: flex; flex-direction: column; }
        .logo-section-link { text-decoration: none; color: inherit; display: block; padding: 10px 0; border-radius: 5px; margin-bottom: -10px; transition: background-color 0.2s; }
        .logo-section-link:hover { background-color: #f0f0f0; }
        .logo-section { display: flex; align-items: center; padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        .profile-avatar-wrapper { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; margin-right: 10px; flex-shrink: 0; }
        .profile-avatar-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .nav-link { padding: 12px 15px; margin-bottom: 5px; color: var(--text-dark); text-decoration: none; display: flex; align-items: center; border-radius: 8px; transition: background-color 0.2s, color 0.2s; }
        .nav-link.active, .nav-link:hover { background-color: var(--primary-color); color: white; }

        /* Icon style for sidebar */
        .nav-link svg {
            width: 20px; 
            height: 20px;
            margin-right: 10px; 
            stroke: var(--text-dark); 
            fill: none; 
            stroke-width: 3; 
            transition: stroke 0.2s;
        }
        .nav-link.active svg, .nav-link:hover svg {
            stroke: white; 
        }

        /* Main Content Style */
        .main-content { flex-grow: 1; padding: 20px 30px; }

        /* Top Bar */
        .top-bar { display: flex; justify-content: flex-end; align-items: center; background-color: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        
        /* Gaya Tombol Umum (Digunakan untuk Logout) */
        .btn-action {
            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9em;
            transition: opacity 0.2s;
            color: white !important; 
            flex-shrink: 0; 
            display: flex;
            align-items: center;
            background: var(--primary-color); /* Untuk Logout */
        }
        .btn-action:hover { opacity: 0.9; }

        /* Header Dashboard */
        .header-dashboard {
            background-color: var(--secondary-color);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .header-dashboard h2 {
            margin: 5px 0 10px 0; 
            font-size: 1.8em;
        }
        .header-dashboard p {
            margin: 0;
            font-size: 0.9em;
        }

        /* Metrics Card */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .metric-card h4 {
            margin-top: 0;
            color: #888;
            font-weight: 500;
            font-size: 0.9em;
        }

        .metric-card p {
            font-size: 2em;
            margin: 5px 0 10px 0;
            font-weight: bold;
        }

        .action-button {
            display: block;
            text-align: center;
            padding: 8px;
            background-color: #e9ecef;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }
        
        .action-button:hover {
            background-color: #d8dee3;
        }

        /* Lower Grid (3x2) */
        .lower-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .lower-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .lower-card h5 {
            color: var(--primary-color);
            font-weight: normal;
            margin-top: 0;
            font-size: 0.9em;
        }
        
        .security-alert {
            background-color: #ffc107; /* Kuning */
            color: var(--text-dark);
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 5px;
            display: inline-block;
            font-size: 0.8em;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="edit_profil.php" class="logo-section-link" title="Edit Profil">
        <div class="logo-section" style="padding-bottom: 0; border-bottom: none; margin-bottom: 0;">
            <div class="profile-avatar-wrapper">
                <img src="<?= htmlspecialchars($photo_path); ?>" alt="Foto Profil">
            </div>
            <div>
                <strong><?= htmlspecialchars($pengantar_nama); ?></strong>
                <div style="font-size: 0.8em; color: #888;">Kurir ID: <?= htmlspecialchars($pengantar_id); ?></div>
            </div>
        </div>
    </a>
    <div style="padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px;"></div>
    
    <a href="dashboard.php" class="nav-link active">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Beranda
    </a>
    <a href="cek_pengiriman.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package">
            <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
            <path d="M2.5 14.4V9.4l9-5 9 5v5l-9 5z"></path>
            <polyline points="2.5 7.6 12 13 21.5 7.6"></polyline>
            <polyline points="12 22.4 12 13"></polyline>
        </svg>
        Cek Pengiriman
    </a>
    <a href="riwayat_tugas.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-file-text">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        Riwayat Tugas
    </a>
</div>

<div class="main-content">
    
    <div class="top-bar">
        <a href="logout.php" class="btn-action">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out" style="width: 16px; height: 16px; margin-right: 5px;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
    
    <div class="header-dashboard">
        <p class="section-title">Beranda</p>
        <h2 class="main-greeting">
            Hello <?= htmlspecialchars($nama_lengkap); ?>, welcome back.
        </h2>
        <p class="smooth-work">
            May your work go smoothly.
        </p>
    </div>

</div>
</body>
</html>