<?php
session_start();
include '../../config/db.php'; // FIX: Jalur ke db.php (keluar 2 tingkat, masuk config)

// Cek ID
if (!isset($_GET['id'])) {
    die("ID tidak ditemukan");
}

$id = intval($_GET['id']);

// ************************************************************
// --- LOGIKA PHP ASLI (TIDAK DIUBAH) ---
// ************************************************************
// Ambil data pesanan dari tabel pesanan_dikirim
$q = $conn->query("
    SELECT * FROM pesanan_dikirim WHERE id_kirim=$id
");

$d = $q->fetch_assoc();
if (!$d) die("Data pesanan tidak ditemukan");

// Ambil nama, email, dan FOTO PROFIL penerima dari tabel user
$q2 = $conn->query("
    SELECT nama_lengkap, email, foto_profil 
    FROM user 
    WHERE user_id=" . $d['user_id']
);
$user = $q2->fetch_assoc();

// Tentukan path foto profil user
$user_foto = $user['foto_profil'] ?? 'default.jpg';
// FIX: Jalur User/Penerima (Diasumsikan di folder assets global)
$user_upload_dir = '../../assets/uploads/profiles/'; 

$photo_path_user = (empty($user_foto) || $user_foto === 'default.jpg' || !file_exists($user_upload_dir . $user_foto)) 
                  ? $user_upload_dir . 'default.jpg' 
                  : $user_upload_dir . $user_foto;

// ************************************************************
// --- LOGIKA PHP UNTUK SIDEBAR (DATA KURIR) ---
// ************************************************************
// Cek jika belum login sebagai pengantar
if (!isset($_SESSION['pengantar_id'])) {
    header("Location: login.php");
    exit;
}
$pengantar_id = $_SESSION['pengantar_id'];
$pengantar_nama = isset($_SESSION['pengantar_nama']) ? $_SESSION['pengantar_nama'] : 'Kurir'; 

// Ambil foto profil pengantar dari DB
$query_pengantar_sidebar = $conn->query("SELECT foto_profil FROM pengantar WHERE pengantar_id = '$pengantar_id'");
$p_sidebar = $query_pengantar_sidebar->fetch_assoc();
$foto_profil_kurir_db = $p_sidebar ? $p_sidebar['foto_profil'] : '';

// FIX: Jalur Kurir/Pengantar
$default_path_kurir = '../../assets/images/default.jpg'; // FIX: Jalur default di assets global
$upload_dir_kurir = '../assets/profil/pengantar/'; // FIX: Jalur folder upload kurir yang baru

$photo_path_kurir = (empty($foto_profil_kurir_db) || $foto_profil_kurir_db === 'default.jpg' || !file_exists($upload_dir_kurir . $foto_profil_kurir_db)) 
                    ? $default_path_kurir 
                    : $upload_dir_kurir . $foto_profil_kurir_db; 

?>
<!DOCTYPE html>
<html>
<head>
<title>Detail Pesanan #<?= $id; ?></title>
<style>
/* CSS DARI DASHBOARD.PHP (DIPERLUKAN UNTUK KONSISTENSI) */
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
    display: flex; /* Menggunakan flexbox untuk layout utama */
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
.nav-link span { margin-left: 10px; }

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
.search-input {
    width: 500px;
    padding: 10px 15px;
    border: 1px solid #ccc;
    border-radius: 8px;
}

/* Header Section (Mirip dashboard.php) */
.header-section {
    background-color: var(--secondary-color);
    color: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* *************************************************** */
/* STYLE KHUSUS DETAIL PESANAN */
/* *************************************************** */
.detail-grid {
    display: grid;
    grid-template-columns: 1fr; /* Satu kolom untuk layout ini */
    gap: 20px;
}

.detail-card {
    background-color: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.detail-card h3 {
    color: var(--primary-color);
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
    margin-bottom: 20px;
    font-size: 1.3em;
}

.info-item {
    display: flex;
    margin-bottom: 12px;
    font-size: 0.95em;
    line-height: 1.5;
}
.info-item strong {
    width: 140px; /* Lebar tetap untuk label */
    flex-shrink: 0;
    color: #555;
}
.info-item span {
    flex-grow: 1;
    color: var(--text-dark);
}

/* Bagian Penerima */
.receiver-info-block {
    display: flex;
    align-items: center;
    background: #f8f8ff; /* Latar belakang untuk menonjolkan */
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9e9e9;
    margin-bottom: 20px;
}
.receiver-photo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    border: 3px solid var(--secondary-color);
    box-shadow: 0 0 5px rgba(0,0,0,0.1);
    flex-shrink: 0;
}
.receiver-details strong {
    font-size: 1.1em;
    color: var(--primary-color);
    display: block;
    margin-bottom: 3px;
}
.receiver-details span {
    color: #777;
    font-size: 0.9em;
    display: block;
}

/* Bagian Aksi */
.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}
.btn {
    padding: 12px 25px;
    border-radius: 8px;
    color: white;
    text-decoration: none; 
    display: inline-block; 
    font-size: 1em;
    font-weight: bold;
    transition: background-color 0.2s;
}
.btn-green { background: #28a745; }
.btn-green:hover { background: #218838; }
.btn-red { background: #dc3545; }
.btn-red:hover { background: #c82333; }
.btn-back-top { /* Gaya untuk tombol kembali di top-bar */
    padding: 8px 15px; 
    background: #6c757d; 
    color: white; 
    text-decoration: none; 
    border-radius: 8px;
    font-size: 0.9em;
}
.btn-back-top:hover { background: #5a6268; }

.status-label {
    background-color: #ff9800; /* Orange untuk status 'dikirim' */
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: bold;
    font-size: 0.85em;
    display: inline-block;
    margin-left: 10px;
}

</style>
</head>
<body>

<div class="sidebar">
    <a href="edit_profil.php" class="logo-section-link" title="Edit Profil">
        <div class="logo-section" style="padding-bottom: 0; border-bottom: none; margin-bottom: 0;">
            <div class="profile-avatar-wrapper">
                <img src="<?= htmlspecialchars($photo_path_kurir); ?>" alt="Foto Profil Kurir">
            </div>
            <div>
                <strong><?= htmlspecialchars($pengantar_nama); ?></strong>
                <div style="font-size: 0.8em; color: #888;">Kurir ID: <?= htmlspecialchars($pengantar_id); ?></div>
            </div>
        </div>
    </a>
    <div style="padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px;"></div>
    
    <a href="dashboard.php" class="nav-link">🏠 Beranda</a>
    <a href="cek_pengiriman.php" class="nav-link active">📦 Cek Pengiriman</a>
    <a href="riwayat_tugas.php" class="nav-link">📜 Riwayat Tugas</a>
    <a href="logout.php" class="nav-link" style="margin-top: auto;">➡️ Keluar</a>
</div>

<div class="main-content">
    
    <div class="top-bar">
        <input type="text" class="search-input" placeholder="Cari resi, order ID, atau menu yang ingin diakses...">
        <div>
            <a href="cek_pengiriman.php" class="btn-back-top">⬅️ Kembali ke Daftar Tugas</a>
        </div>
    </div>
    
    <div class="header-section">
        <p style="margin-top: 0; font-size: 0.9em;">Detail Tugas</p>
        <h2 style="margin: 5px 0 10px 0; font-size: 1.8em;">Pesanan ID #<?= htmlspecialchars($id); ?> <span class="status-label"><?= htmlspecialchars(strtoupper($d['status'])); ?></span></h2>
        <p style="font-size: 0.9em;">Rincian lengkap pesanan yang sedang Anda tangani.</p>
    </div>
    
    <div class="detail-grid">
        <div class="detail-card">
            <h3>Informasi Penerima</h3>
            <div class="receiver-info-block">
                <img src="<?= htmlspecialchars($photo_path_user); ?>" alt="Foto Penerima" class="receiver-photo">
                <div class="receiver-details">
                    <strong><?= htmlspecialchars($user['nama_lengkap'] ?? 'N/A'); ?></strong>
                    <span>Email: <?= htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-item">
                <strong>Alamat Lengkap:</strong>
                <span><?= nl2br(htmlspecialchars($d['alamat_pengiriman'])); ?></span>
            </div>
        </div>

            <div class="detail-card">
                <h3>Rincian Barang</h3>
                <div class="info-item">
                    <strong>Nama Barang:</strong>
                    <span><?= htmlspecialchars($d['nama_barang']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Jumlah:</strong>
                    <span><?= htmlspecialchars($d['jumlah']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Total Harga:</strong>
                    <span style="font-weight: bold; color: #dc3545;">
                        Rp <?= number_format($d['harga_total'], 0, ',', '.'); ?> 
                        (<?= ($d['harga_total'] > 0) ? 'COD' : 'Sudah Dibayar'; ?>)
                    </span>
                </div>
            </div>
        </div>

    <div class="action-buttons">
        <a href="antar_sukses.php?id=<?= htmlspecialchars($id); ?>" 
            class="btn btn-green" 
            onclick="return confirm('Yakin pesanan sudah diantar dan diterima pelanggan? Status akan diubah menjadi Selesai.')"
        >
            ✅ Pesanan Diterima
        </a>
        <a href="form_gagal_antar.php?id=<?= htmlspecialchars($id); ?>" 
            class="btn btn-red" 
            onclick="return confirm('Yakin pesanan GAGAL diantar? Tindakan ini akan menghapus pesanan dari daftar kirim dan mencatat alasan kegagalan.')"
        >
            ⚠️ Gagal Diantar
        </a>
    </div>

</div>

</body>
</html>