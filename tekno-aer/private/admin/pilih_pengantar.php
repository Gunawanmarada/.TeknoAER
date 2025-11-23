<?php
session_start();
// PERBAIKAN JALUR INCLUDE: Dari private/admin/ ke config/
include '../../config/db.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data admin yang sedang login (untuk Sidebar)
$admin_id = $_SESSION['admin_id'];
$admin = $conn->query("SELECT * FROM admin WHERE admin_id = $admin_id")->fetch_assoc();
$nama_admin = isset($admin['nama_lengkap']) ? $admin['nama_lengkap'] : (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin');
$role_admin = isset($admin['role']) ? $admin['role'] : 'Superuser';

// Pastikan ID pesanan tersedia
if (!isset($_GET['id'])) {
    die("ID pesanan tidak ditemukan");
}

$id_pesanan = intval($_GET['id']);

// =========================================================
// LOGIKA PERUBAHAN KRUSIAL: Ambil SEMUA data item dalam satu id_pesanan
// =========================================================

// 1. Ambil semua detail item (berpotensi banyak baris)
$q_items = $conn->query("SELECT * FROM pesanan_pelanggan WHERE id_pesanan=$id_pesanan");
$items_pesanan = $q_items->fetch_all(MYSQLI_ASSOC);

if (empty($items_pesanan)) {
    die("Data pesanan tidak ditemukan untuk ID Transaksi ini.");
}

// Ambil data detail transaksi dari baris pertama (karena user, alamat, dan tanggal sama)
$data_transaksi = $items_pesanan[0]; 

// Hitung Grand Total (Total seluruh item dalam grup ini)
$grand_total = 0;
foreach ($items_pesanan as $item) {
    $grand_total += $item['total'];
}

// 2. Ambil daftar semua pengantar yang ada di tabel 'pengantar'
$q_pengantar = $conn->query("SELECT pengantar_id, nama FROM pengantar ORDER BY nama ASC");
$pengantars = $q_pengantar->fetch_all(MYSQLI_ASSOC);

// 3. Hitung jumlah pesanan total untuk Sidebar (jumlah transaksi unik)
$jumlah_pesanan_unik = $conn->query("SELECT COUNT(DISTINCT id_pesanan) AS total FROM pesanan_pelanggan")->fetch_assoc()['total'];
// Kita gunakan variabel baru untuk kejelasan, tapi dalam template HTML, variabel lama masih digunakan.
$jumlah_pesanan = $jumlah_pesanan_unik; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Pengantar | Pesanan #<?= $id_pesanan; ?></title>
    <link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
    <style>
        /* CSS DARI KODE REFERENSI ADMIN DASHBOARD (TIDAK DIUBAH) */
        :root {
            --primary-color: #5b46b6; /* Ungu Utama */
            --secondary-color: #6c54c3; /* Ungu Lebih Terang */
            --text-dark: #333;
            --bg-light: #f4f6f9;
            --color-total: #dc3545; /* Merah untuk Total */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            display: flex; 
            color: var(--text-dark);
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

        .nav-link svg {
            width: 20px; height: 20px; margin-right: 10px; stroke: var(--text-dark);
            fill: none; stroke-width: 3; transition: stroke 0.2s;
        }
        .nav-link.active svg, .nav-link:hover svg { stroke: white; }
        
        /* ------------------ Main Content Style ------------------ */
        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        /* Top Bar - Header Putih */
        .top-bar {
            display: flex;
            justify-content: flex-end; /* Hanya Logout di kanan */
            align-items: center;
            background-color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        /* Style untuk Tombol Aksi (Logout) - DITAMBAHKAN KEMBALI */
        .btn-action {
            display: inline-flex; /* Menggunakan inline-flex untuk penempatan ikon dan teks yang rapi */
            align-items: center;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9em;
            transition: background-color 0.2s;
            color: white !important; 
            flex-shrink: 0; 
            background: var(--primary-color);
        }
        .btn-action:hover { opacity: 0.9; }

        .btn-action svg {
            width: 16px;
            height: 16px;
            margin-right: 5px; 
            stroke: white;
            fill: none;
            stroke-width: 3;
        }
        
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

        /* Tambahan CSS untuk Form Pilih Kurir */
        .data-pesanan {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            background-color: #fcfcfc;
            margin-bottom: 20px;
        }
        .data-pesanan p {
            margin: 5px 0;
            font-size: 1em;
        }
        .data-pesanan b {
            font-weight: 600;
            color: var(--primary-color);
            display: inline-block;
            width: 150px;
        }
        
        form label { 
            display: block; 
            margin-top: 20px; 
            font-weight: bold; 
            color: var(--text-dark); 
            margin-bottom: 5px;
        }
        
        form select, form input[type="submit"] {
            width: 100%;
            padding: 12px 15px;
            margin-top: 5px; 
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        form select {
            border: 1px solid #ccc;
            background-color: white;
        }
        
        form input[type="submit"] {
            background: #28a745; /* Hijau untuk kirim/submit */
            color: white;
            border: none;
            cursor: pointer;
            margin-top: 30px;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        form input[type="submit"]:hover {
            background: #1e7e34;
        }

        .btn-back { 
            display: inline-block; 
            margin-bottom: 20px; 
            padding: 10px 15px; 
            background: #6c757d; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .btn-back:hover {
            background: #5a6268;
        }

        /* Styling untuk Daftar Item */
        .item-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .item-list-table th, .item-list-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
            font-size: 0.95em;
        }
        .item-list-table th {
            background-color: #f8f9fa;
        }
        .grand-total-row td {
            font-weight: bold;
            background-color: #ffe0e0;
            color: var(--color-total);
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
            <strong><?= htmlspecialchars($nama_admin); ?></strong>
            <div style="font-size: 0.8em; color: #888;">Role: <?= htmlspecialchars($role_admin); ?></div>
        </div>
    </div>
    
    <a href="dashboard.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Dashboard
    </a>
    <a href="pesanan_pelanggan.php" class="nav-link active">
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
        <a href="logout.php" class="btn-action">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
    
    <div class="header-dashboard">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Aksi Pesanan</p>
        <h2>Penugasan Pengantar (Transaksi #<?= $id_pesanan; ?>)</h2> 
        <p style="font-size: 0.9em;">Tentukan kurir yang akan bertugas untuk mengantar grup pesanan ini.</p>
    </div>
    
    <div class="content-container">
        
        <a href="pesanan_pelanggan.php" class="btn-back">Kembali ke Daftar Pesanan</a>

        <h3 style="margin-top: 10px;">Detail Transaksi</h3>
        <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;">
        
        <div class="data-pesanan">
            <p><b>ID Transaksi:</b> <?= $id_pesanan; ?></p>
            <p><b>Nama Pelanggan:</b> <?= htmlspecialchars($data_transaksi['nama_pelanggan']); ?></p>
            <p><b>Alamat Tujuan:</b> <?= htmlspecialchars($data_transaksi['alamat_pengiriman']); ?></p>
            <p style="font-size: 1.1em; color: var(--color-total); margin-top: 15px;">
                <b>Grand Total:</b> Rp **<?= number_format($grand_total, 0, ',', '.'); ?>**
            </p>
        </div>

        <h3 style="margin-top: 30px;">Daftar Item dalam Transaksi</h3>
        <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;">

        <table class="item-list-table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Jumlah</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items_pesanan as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nama_barang']); ?></td>
                    <td><?= $item['jumlah']; ?>x</td>
                    <td>Rp <?= number_format($item['harga'], 0, ',', '.'); ?></td>
                    <td>Rp <?= number_format($item['total'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>


        <form action="pesanan_antar.php" method="POST">
            <input type="hidden" name="id_pesanan" value="<?= $id_pesanan; ?>">
            
            <label for="pengantar_id">Pilih Pengantar yang Tersedia:</label>
            <select name="pengantar_id" id="pengantar_id" required>
                <option value="">-- Pilih Pengantar --</option>
                <?php foreach ($pengantars as $p): ?>
                    <option value="<?= $p['pengantar_id']; ?>">
                        <?= htmlspecialchars($p['nama']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if (empty($pengantars)): ?>
                <p style="color:#C71E64; margin-top:15px; font-weight: bold;">
                    ❌ Belum ada data Pengantar. Harap tambahkan data pengantar terlebih dahulu.
                </p>
            <?php else: ?>
                <input type="submit" name="submit_antar" value="✅ Tetapkan Pengantar & Proses Pengiriman">
            <?php endif; ?>
        </form>

    </div>

</div>

</body>
</html>