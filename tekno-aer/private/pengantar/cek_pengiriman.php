<?php
session_start();
include '../../config/db.php'; // FIX: Menggunakan jalur yang benar (Keluar 2x, masuk ke config)

// Cek jika belum login, alihkan ke login
if (!isset($_SESSION['pengantar_id'])) {
    header("Location: login.php");
    exit;
}

$pengantar_id = $_SESSION['pengantar_id'];
$pengantar_nama = isset($_SESSION['pengantar_nama']) ? $_SESSION['pengantar_nama'] : 'Kurir'; 

// --- LOGIKA PHP DENGAN PENCARIAN ---
$search_query_clause = "";
$search_term = $_GET['search'] ?? ''; // Ambil term pencarian dari URL, default kosong

if (!empty($search_term)) {
    // Sanitasi input untuk mencegah SQL Injection
    $safe_search_term = $conn->real_escape_string($search_term);
    
    // Logika pencarian: mencari di id_kirim, nama_barang, dan alamat_pengiriman
    $search_query_clause = " AND (
        id_kirim LIKE '%$safe_search_term%' OR 
        nama_barang LIKE '%$safe_search_term%' OR 
        alamat_pengiriman LIKE '%$safe_search_term%'
    )";
}

// Query utama yang disaring berdasarkan kurir, status 'dikirim', dan term pencarian
$data = $conn->query("
    SELECT * FROM pesanan_dikirim 
    WHERE pengantar_id='$pengantar_id' 
    AND status='dikirim' 
    $search_query_clause
");
// **********************************************
// LOGIKA UNTUK MENENTUKAN FOTO PROFIL (DIAMBIL DARI DASHBOARD SEBELUMNYA)
// **********************************************
$query_pengantar = $conn->query("SELECT foto_profil FROM pengantar WHERE pengantar_id = '$pengantar_id'");
$p = $query_pengantar->fetch_assoc();
$foto_profil_db = $p ? $p['foto_profil'] : '';

// FIX: Menentukan path foto
$default_path = '../../assets/images/default.jpg'; // FIX: Jalur default di folder assets global
$upload_dir = '../assets/profil/pengantar/'; // FIX: Jalur BARU di folder private/assets/profil/pengantar/

$photo_path = (empty($foto_profil_db) || $foto_profil_db === 'default.jpg' || !file_exists($upload_dir . $foto_profil_db)) 
             ? $default_path 
             : $upload_dir . $foto_profil_db; 

// **********************************************
// DATA DUMMY RINGKASAN
$total_tugas_dikirim = $data->num_rows;
$tugas_berhasil_hari_ini = 0; 
// **********************************************

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Pengiriman | Tugas Kurir</title>
    <style>
        /* CSS UMUM UNTUK KONSISTENSI */
        :root {
            --primary-color: #5b46b6; 
            --secondary-color: #6c54c3; 
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

        /* Sidebar Style */
        .sidebar {
            width: 250px;
            background-color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
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
        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* Gaya Tombol Umum (Digunakan untuk Logout dan Clear/Reset) */
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
        }
        .btn-action:hover { opacity: 0.9; }
        
        /* ************************************************* */
        /* START: STYLE FORM PENCARIAN BARU */
        .search-form { 
            display: flex; 
            align-items: center;
            gap: 5px; /* Jarak antara input dan tombol */
        }
        .search-form input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9em;
            width: 300px; /* Dibuat sedikit lebih besar */
            transition: border-color 0.2s;
        }
        .search-form input[type="text"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .search-form button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.9em;
            display: flex;
            align-items: center;
        }
        .search-form button:hover {
            background-color: var(--secondary-color);
        }
        .search-form button svg {
            width: 16px;
            height: 16px;
            stroke: white;
            fill: none;
            stroke-width: 3;
        }
        /* END: STYLE FORM PENCARIAN BARU */
        /* ************************************************* */

        /* STYLE KHUSUS DAFTAR PENGIRIMAN */
        .header-pengiriman {
            background-color: var(--secondary-color);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .header-pengiriman h2 {
            margin: 5px 0 10px 0;
            font-size: 1.8em;
        }
        
        /* Daftar Pengiriman */
        .shipping-list-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .shipping-list-container h3 {
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-top: 0;
            color: var(--primary-color);
        }

        /* Mengganti .box lama dengan style modern */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .order-item:hover {
            background-color: #f7f7f7;
        }
        
        .order-info {
            flex-grow: 1;
        }

        .order-info strong {
            display: block;
            font-size: 1.1em;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .order-info span {
            font-size: 0.9em;
            color: #555;
            display: block;
        }
        
        .order-actions a {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            margin-left: 10px;
            white-space: nowrap; 
            transition: background-color 0.2s;
        }
        .order-actions a:hover {
            background-color: #4a3899;
        }
        
        .status-tag {
            background-color: #ff9800; /* Orange - Default untuk 'dikirim' */
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            display: inline-block;
            margin-top: 5px;
        }

        /* Gaya untuk ikon SVG di sidebar */
        .nav-link svg {
            width: 20px; 
            height: 20px;
            margin-right: 10px; 
            stroke: var(--text-dark); 
            fill: none; 
            stroke-width: 3; 
            transition: stroke 0.2s;
        }
        .nav-link.active svg {
            stroke: white;
        }

    </style>

    <script>
    // FUNGSI JAVASCRIPT ASLI ANDA
    function lihatDetail(id) {
        // Mengarahkan ke detail pesanan saat item diklik (kecuali di tombol aksi)
        window.location = "detail_pesanan.php?id=" + id;
    }
    </script>

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
    
    <a href="dashboard.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Beranda
    </a>
    <a href="cek_pengiriman.php" class="nav-link active">
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
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
            <input 
                type="text" 
                name="search" 
                placeholder="Cari ID kirim, barang, atau alamat..." 
                value="<?= htmlspecialchars($search_term); ?>"
                autocomplete="off"
            >
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
            <?php if (!empty($search_term)): ?>
                <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn-action" style="background: #dc3545; padding: 8px 10px; font-size: 0.8em; margin-left: 5px;">Clear</a>
            <?php endif; ?>
        </form>
        
        <a href="logout.php" class="btn-action" style="background: var(--primary-color);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out" style="width: 16px; height: 16px; margin-right: 5px;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
    
    <div class="header-pengiriman">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Tugas Anda</p>
        <h2 style="margin: 5px 0 10px 0; font-size: 1.8em;">Daftar Tugas Pengantaran Hari Ini (<?= $total_tugas_dikirim; ?> Paket)</h2>
        <?php if (!empty($search_term)): ?>
            <p style="font-size: 0.9em;">Menampilkan hasil pencarian untuk: "<strong><?= htmlspecialchars($search_term); ?></strong>"</p>
        <?php else: ?>
            <p style="font-size: 0.9em;">Semua paket di bawah ini berstatus 'dikirim' dan menunggu aksi Anda.</p>
        <?php endif; ?>
    </div>
    
    <div class="shipping-list-container">
        <h3>Paket Siap Diantar</h3>
        
        <?php if ($data->num_rows == 0): ?>
             <p>🎉 Tidak ada pesanan aktif dengan status 'dikirim' untuk Anda saat ini.
             <?php if (!empty($search_term)): ?>
                 (Tidak ditemukan hasil untuk "<?= htmlspecialchars($search_term); ?>")
             <?php endif; ?>
             </p>
        <?php else: ?>
            <?php while ($p = $data->fetch_assoc()): ?>
                <div class="order-item" onclick="lihatDetail(<?= htmlspecialchars($p['id_kirim']); ?>)">
                    <div class="order-info">
                        <strong>ID #<?= htmlspecialchars($p['id_kirim']); ?>: <?= htmlspecialchars($p['nama_barang']); ?></strong>
                        <span>Tujuan: <?= htmlspecialchars($p['alamat_pengiriman']); ?></span>
                        <span>Jumlah: <?= htmlspecialchars($p['jumlah']); ?></span>
                        <span class="status-tag"><?= strtoupper(htmlspecialchars($p['status'])); ?></span>
                    </div>
                    
                    <div class="order-actions">
                        <span style="font-weight: bold; color: var(--primary-color); margin-right: 15px;">
                            Rp <?= number_format($p['harga_total'], 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        
    </div>

</div>

</body>
</html>