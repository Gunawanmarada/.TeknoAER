<?php
session_start();
include '../../config/db.php'; // FIX: Jalur ke db.php (Diasumsikan: dua tingkat keluar, masuk config)

// Cek jika belum login
if (!isset($_SESSION['pengantar_id'])) {
    header("Location: login.php");
    exit;
}

$pengantar_id = $_SESSION['pengantar_id'];
$pengantar_nama = isset($_SESSION['pengantar_nama']) ? $_SESSION['pengantar_nama'] : 'Kurir'; 

// --- LOGIKA FOTO PROFIL KURIR ---
$query_pengantar = $conn->query("SELECT foto_profil FROM pengantar WHERE pengantar_id = '$pengantar_id'"); 
$p = $query_pengantar->fetch_assoc();
$foto_profil_db = $p ? $p['foto_profil'] : '';

// FIX: Jalur default ke assets di root
$default_path = '../../assets/images/default.jpg'; 
// FIX: Jalur folder upload kurir yang baru
$upload_dir = '../assets/profil/pengantar/'; 

$photo_path = (empty($foto_profil_db) || $foto_profil_db === 'default.jpg' || !file_exists($upload_dir . $foto_profil_db)) 
             ? $default_path 
             : $upload_dir . $foto_profil_db; 

// --- QUERY RIWAYAT (FIXED dan Ditambahkan Fitur Pencarian) ---
$search_query = "";
$search_term = $_GET['search'] ?? ''; // Ambil term pencarian dari URL, default kosong

if (!empty($search_term)) {
    // Sanitasi input untuk mencegah SQL Injection
    $safe_search_term = $conn->real_escape_string($search_term);
    
    // Logika pencarian: mencari di nama_barang, id_kirim, dan status
    $search_query = " AND (
        nama_barang LIKE '%$safe_search_term%' OR 
        id_kirim LIKE '%$safe_search_term%' OR 
        status LIKE '%$safe_search_term%'
    )";
}

$q_riwayat = $conn->query("
    SELECT id_kirim, nama_barang, total, status, waktu_selesai 
    FROM riwayat_pengiriman 
    WHERE pengantar_id='$pengantar_id' 
    $search_query 
    ORDER BY waktu_selesai DESC
");
$total_tugas_riwayat = $q_riwayat->num_rows; 

$primary_color = '#5b46b6'; 
$secondary_color = '#6c54c3';
$text_dark = '#333';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Tugas | Kurir</title>
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
        .top-bar { display: flex; justify-content: space-between; align-items: center; background-color: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        
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


        /* Header Riwayat */
        .header-riwayat {
            background-color: var(--secondary-color);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Daftar Riwayat */
        .history-list-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }

        .history-item:hover {
            background-color: #f7f7f7;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }

        .history-info {
            flex-grow: 1;
            display: flex;
            align-items: center;
        }

        .icon-status {
            font-size: 1.5em;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }

        .order-details strong {
            display: block;
            font-size: 1.1em;
            color: var(--text-dark);
            margin-bottom: 3px;
        }
        
        .order-details span {
            font-size: 0.9em;
            color: #777;
        }

        .status-tag {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-selesai { background-color: #d4edda; color: #155724; }
        .status-gagal { background-color: #f8d7da; color: #721c24; }

        .history-actions {
            display: flex;
            align-items: center;
        }
        
        .history-actions .date {
            margin-right: 20px;
            font-size: 0.9em;
            color: #555;
            white-space: nowrap;
        }

        .history-actions a {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }
        .history-actions a:hover {
            background-color: #0056b3;
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
    
    <a href="dashboard.php" class="nav-link">
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
    <a href="riwayat_tugas.php" class="nav-link active">
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
                placeholder="Cari ID riwayat, barang, atau status..." 
                value="<?= htmlspecialchars($search_term); ?>"
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
    
    <div class="header-riwayat">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Arsip</p>
        <h2 style="margin: 5px 0 10px 0; font-size: 1.8em;">Riwayat Tugas Pengantaran (<?= $total_tugas_riwayat; ?> Tugas)</h2>
        <?php if (!empty($search_term)): ?>
            <p style="font-size: 0.9em;">Menampilkan hasil pencarian untuk: "<strong><?= htmlspecialchars($search_term); ?></strong>"</p>
        <?php else: ?>
            <p style="font-size: 0.9em;">Daftar tugas yang telah Anda selesaikan atau laporkan kegagalannya.</p>
        <?php endif; ?>
    </div>
    
    <div class="history-list-container">
        <h3>Tugas Selesai & Gagal</h3>
        
        <?php if ($total_tugas_riwayat == 0): ?>
             <p>Belum ada riwayat tugas yang dicatat untuk Anda.
             <?php if (!empty($search_term)): ?>
                (Tidak ditemukan hasil untuk "<?= htmlspecialchars($search_term); ?>")
             <?php endif; ?>
             </p>
        <?php else: ?>
            <?php while ($r = $q_riwayat->fetch_assoc()): 
                $status_class = ($r['status'] == 'selesai') ? 'status-selesai' : 'status-gagal';
                $icon_emoji = ($r['status'] == 'selesai') ? '✅' : '❌';
            ?>
                <div class="history-item">
                    <div class="history-info">
                        <span class="icon-status"><?= $icon_emoji; ?></span>
                        <div class="order-details">
                            <strong>Riwayat ID #<?= htmlspecialchars($r['id_kirim']); ?>: <?= htmlspecialchars($r['nama_barang']); ?></strong>
                            <span>Total: Rp <?= number_format($r['total'] ?? 0, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="history-actions">
                        <span class="date"><?= date('d F Y H:i', strtotime($r['waktu_selesai'])); ?></span>
                        <span class="status-tag <?= $status_class; ?>"><?= htmlspecialchars($r['status']); ?></span>
                        <a href="detail_riwayat.php?id=<?= htmlspecialchars($r['id_kirim']); ?>" style="margin-left: 15px;">Lihat Detail</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        
    </div>

</div>

</body>
</html>