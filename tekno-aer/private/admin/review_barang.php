<?php
session_start();
// Menggunakan path yang sudah diperbaiki
include '../../config/db.php'; 

// 1. Verifikasi Admin Login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
$admin_id = $_SESSION['admin_id'];

// Ambil data admin yang sedang login, sinkron dengan dashboard.php
$admin = [];
if (isset($conn)) {
    // Menggunakan Prepared Statement untuk mengambil data admin
    $stmt_admin = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();
    $admin = $result_admin->fetch_assoc();
    $stmt_admin->close();
} 

// Data Tambahan untuk Tampilan (Sinkron dengan dashboard.php)
// Cek apakah data admin ditemukan, jika tidak gunakan fallback
$nama_admin = isset($admin['nama_lengkap']) ? htmlspecialchars($admin['nama_lengkap']) : (isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin');
$role_admin = isset($admin['role']) ? htmlspecialchars($admin['role']) : 'Superuser';

// Ambil ID Barang dan Validasi
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Barang tidak valid.");
}
$barang_id = intval($_GET['id']);

// 3. Ambil Data Barang (Menggunakan Prepared Statement)
$stmt_barang = $conn->prepare("SELECT nama_barang FROM barang WHERE barang_id = ?");
$stmt_barang->bind_param("i", $barang_id);
$stmt_barang->execute();
$q_barang = $stmt_barang->get_result();
$data_barang = $q_barang->fetch_assoc();
$stmt_barang->close();

if (!$data_barang) {
    die("Barang tidak ditemukan.");
}

// 4. Ambil Kolom Nama dan Foto User
$col_nama_user = 'nama_lengkap'; 
$col_foto_user = 'foto_profil'; 

// 5. Ambil Semua Review untuk Barang ini (Menggunakan Prepared Statement)
$stmt_review = $conn->prepare("
    SELECT r.*, u." . $col_nama_user . " AS nama_user, u." . $col_foto_user . " AS foto_profil
    FROM review r
    LEFT JOIN user u ON r.user_id = u.user_id
    WHERE r.barang_id = ?
    ORDER BY r.tanggal DESC 
");
$stmt_review->bind_param("i", $barang_id);
$stmt_review->execute();
$review_query = $stmt_review->get_result();

// 6. Handle Pesan Sukses/Gagal dari Aksi Balas
$message = '';
if (isset($_GET['msg'])) {
    $message = htmlspecialchars(urldecode($_GET['msg']));
}

// Ambil jumlah pesanan (jika diperlukan untuk sidebar)
$jumlah_pesanan = 0; // Default
if (isset($conn)) {
    $jumlah_pesanan = $conn->query("SELECT COUNT(*) AS total FROM pesanan_pelanggan")->fetch_assoc()['total'] ?? 0;
}

$search_query = $_GET['search'] ?? ''; // Untuk form pencarian di top-bar
// =========================================================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balas Review | <?= htmlspecialchars($data_barang['nama_barang']); ?></title>
    <link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
    <style>
        /* CSS Umum dan Warna (Meniru Tampilan Kurir) */
        :root {
            --primary-color: #5b46b6; /* Ungu Utama */
            --secondary-color: #6c54c3; /* Ungu Lebih Terang */
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
            min-height: 100vh;
        }

        /* ------------------ Sidebar Style ------------------ */
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

        .nav-link.active svg, .nav-link:hover svg {
            stroke: white;
        }
        
        /* ------------------ Main Content Style ------------------ */
        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        /* Top Bar - Header Putih */
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

        /* Gaya Tombol Umum */
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
            border: none; 
            cursor: pointer;
        }
        .btn-action:hover {
            opacity: 0.9;
        }
        
        .btn-tambah { background: #4E31AA; } 
        
        /* Gaya untuk ikon SVG di tombol action (Logout, Clear, Tambah) */
        .btn-action svg {
             width: 16px;
             height: 16px;
             vertical-align: middle;
             margin-right: 5px;
             stroke: white;
             fill: none;
             stroke-width: 3; 
        }

        /* START: Style Form Pencarian BARU (dengan ikon) */
        .search-form { 
            display: flex; 
            align-items: center;
            gap: 5px; 
        }
        .search-form input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9em;
            width: 250px;
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
            margin-right: 0; 
        }
        /* END: Style Form Pencarian BARU */

        /* Header Dashboard Ungu */
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
        
        /* Konten Utama Container */
        .content-container {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        /* Tombol Aksi (di bawah H3) */
        .action-buttons {
            display: flex;
            flex-wrap: wrap; 
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        
        /* Tambahkan style untuk Review spesifik */
        .review-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .reviewer-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .reviewer-photo {
            width: 45px; height: 45px; border-radius: 50%; object-fit: cover;
            margin-right: 10px; border: 2px solid var(--primary-color);
        }
        .star { 
            color: gold; 
            font-size: 18px; 
        }
        .admin-reply {
            margin-top: 10px;
            padding: 10px;
            border-left: 4px solid #28a745;
            background: #e6f7ea;
            border-radius: 4px;
        }
        .reply-form textarea {
            width: 100%; 
            height: 80px; 
            padding: 8px; 
            border-radius: 4px;
            border: 1px solid #ccc; 
            box-sizing: border-box; 
            resize: vertical;
        }
        .alert-success {
            padding: 10px; 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
            border-radius: 5px; 
            margin-bottom: 15px;
        }
        
        /* Icon header untuk halaman review */
         .content-container h3 svg {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
            stroke: var(--text-dark);
            fill: none;
            stroke-width: 3; 
        }

        /* Style untuk tombol Kembali (btn-cancel) */
        .btn-cancel { 
            background: #6c757d; /* Warna abu-abu netral */
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo-section" style="cursor: default;">
        <div class="profile-avatar-wrapper">
            <img src="../assets/logo/logo.jpg" alt="Logo Toko"> 
        </div>
        <div>
            <strong><?= $nama_admin; ?></strong>
            <div style="font-size: 0.8em; color: #888;">Role: <?= $role_admin; ?></div>
        </div>
    </div>
    
    <a href="dashboard.php" class="nav-link active"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Dashboard
    </a>
    <a href="pesanan_pelanggan.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package">
            <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
            <path d="M2.5 14.4V9.4l9-5 9 5v5l-9 5z"></path>
            <polyline points="2.5 7.6 12 13 21.5 7.6"></polyline>
            <polyline points="12 22.4 12 13"></polyline>
        </svg>
        Pesanan Masuk (<?= $jumlah_pesanan; ?>)
    </a>
    <a href="data_keuangan.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        Data Keuangan
    </a>
</div>

<div class="main-content">
    
    <div class="top-bar">
        <form action="dashboard.php" method="get" class="search-form">
            <input 
                type="text" 
                name="search" 
                placeholder="Cari nama barang atau kategori..." 
                value="<?= htmlspecialchars($search_query); ?>"
            >
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
            <?php if (!empty($search_query)): ?>
                <a href="dashboard.php" class="btn-action" style="background: #dc3545; padding: 8px 10px; font-size: 0.8em;">Clear</a>
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
    <div class="header-dashboard">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Review Management</p>
        <h2>Balas dan Kelola Ulasan</h2> 
        <p style="font-size: 0.9em;">Anda sedang melihat ulasan untuk barang: **<?= htmlspecialchars($data_barang['nama_barang']); ?>**</p>
    </div>
    
    <div class="content-container">
        
        <h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            Ulasan untuk: <?= htmlspecialchars($data_barang['nama_barang']); ?>
        </h3>
        
        <div class="action-buttons" style="border: none; background: transparent; padding: 0;">
            <a href="dashboard.php" class="btn-action btn-cancel">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 5px;">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Kembali
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert-success">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($review_query->num_rows == 0): ?>
            <p style="padding: 15px; text-align: center;">Belum ada review untuk barang ini.</p>
        <?php else: ?>
            <?php while ($r = $review_query->fetch_assoc()): 
                // Logika path foto
                $reviewer_photo_file = $r['foto_profil'] ?? 'default.jpg';
                $reviewer_photo_path = '../assets/uploads/profiles/' . htmlspecialchars($reviewer_photo_file);
                
                $absolute_path_check = dirname(__DIR__, 2) . '/assets/uploads/profiles/' . $reviewer_photo_file; 
                
                if (!file_exists($absolute_path_check) || empty($r['foto_profil'])) {
                    $reviewer_photo_path = '../assets/uploads/profiles/default.jpg'; 
                }
            ?>
                <div class="review-item">
                    <div class="reviewer-info">
                        <img src="<?= $reviewer_photo_path; ?>" alt="Foto Reviewer" class="reviewer-photo">
                        <div>
                            <b><?= htmlspecialchars($r['nama_user']); ?></b>
                            <p style="margin:0; color:#666; font-size:0.9em;">
                                <?= date('d M Y, H:i', strtotime($r['tanggal'])); ?> 
                            </p>
                        </div>
                    </div>

                    <p style="margin-top:0;">
                        <?php for ($i=1; $i<=5; $i++): ?>
                            <?= ($i <= $r['rating']) ? "<span class='star'>★</span>" : "<span class='star' style='opacity:0.3;'>★</span>" ?>
                        <?php endfor; ?>
                    </p>

                    <p><?= nl2br(htmlspecialchars($r['komentar'])); ?></p>
                    
                    <hr>

                    <?php if (!empty($r['balasan_admin'])): ?>
                        <div class="admin-reply">
                            <b>Balasan Admin:</b>
                            <p style="margin-top: 5px;"><?= nl2br(htmlspecialchars($r['balasan_admin'])); ?></p>
                            <p style="margin:0; font-size:0.8em; color:#444;">
                                Dibalas oleh Admin #<?= htmlspecialchars($r['admin_id']); ?> pada 
                                <?= date('d M Y, H:i', strtotime($r['waktu_balas'] ?? $r['tanggal'])); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <form action="balas_review.php" method="POST" class="reply-form">
                            <input type="hidden" name="id_review" value="<?= htmlspecialchars($r['id_review']); ?>">
                            <input type="hidden" name="barang_id" value="<?= htmlspecialchars($barang_id); ?>">
                            
                            <label for="balasan_<?= htmlspecialchars($r['id_review']); ?>">Balas Review:</label>
                            <textarea name="balasan_admin" id="balasan_<?= htmlspecialchars($r['id_review']); ?>" required class="form-control"></textarea>
                            
                            <button type="submit" name="balas" class="btn-action" style="background: #28a745; float: right; margin-top: 5px;">
                                Kirim Balasan
                            </button>
                            <div style="clear: both;"></div>
                        </form>
                    <?php endif; ?>

                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>