<?php
session_start();
// 🛠️ PERBAIKAN 1: Path ke db.php sudah benar (naik dua tingkat dari private/admin/ ke config/db.php)
include '../../config/db.php';

// Cegah akses tanpa login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data admin yang sedang login
$admin_id = $_SESSION['admin_id'];
// Pastikan variabel $conn tersedia dan koneksi berhasil sebelum query
if (isset($conn)) {
    // Menggunakan Prepared Statement untuk mengambil data admin
    $stmt_admin = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();
    $admin = $result_admin->fetch_assoc();
    $stmt_admin->close();
} else {
    // Fallback jika koneksi gagal
    $admin = [];
}

// --- LOGIKA PENCARIAN BARANG DIMULAI ---
$search_query = "";
$where_clause = "";
$result = false;
$jumlah_pesanan = 0;

if (isset($conn)) {
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        // Menggunakan Prepared Statement untuk pencarian guna keamanan
        $search_term = "%" . trim($_GET['search']) . "%";
        $sql = "SELECT * FROM barang WHERE nama_barang LIKE ? OR kategori LIKE ? ORDER BY barang_id DESC";
        
        $stmt_barang = $conn->prepare($sql);
        $stmt_barang->bind_param("ss", $search_term, $search_term);
        $stmt_barang->execute();
        $result = $stmt_barang->get_result();
        
        // Simpan query untuk ditampilkan di form
        $search_query = trim($_GET['search']);

    } else {
        // Ambil semua data barang tanpa filter
        $sql = "SELECT * FROM barang ORDER BY barang_id DESC";
        $result = $conn->query($sql);
    }
    
    // Hitung jumlah pesanan (menggunakan direct query jika sudah pasti hanya SELECT COUNT)
    $jumlah_pesanan = $conn->query("SELECT COUNT(*) AS total FROM pesanan_pelanggan")->fetch_assoc()['total'];
}
// --- LOGIKA PENCARIAN BARANG SELESAI ---

// Data Tambahan untuk Tampilan
$nama_admin = isset($admin['nama_lengkap']) ? htmlspecialchars($admin['nama_lengkap']) : (isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin');
$role_admin = isset($admin['role']) ? htmlspecialchars($admin['role']) : 'Superuser';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | TeknoAER</title>
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

        /* ************************************************* */
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
        /* ************************************************* */

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
        
        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        table th, table td {
            border: none;
            border-bottom: 1px solid #f0f0f0;
            padding: 15px 12px;
            text-align: left;
        }

        table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            border-bottom: none; 
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background-color: #fcfcfc;
        }

        table td img {
            border-radius: 4px;
            transition: transform 0.2s;
        }
        
        /* Tombol Aksi di Tabel */
        .btn-table {
           display: inline-block;
           padding: 5px 10px;
           border-radius: 5px;
           text-decoration: none;
           font-size: 0.9em;
           margin-right: 5px;
           color: white;
           font-weight: bold;
        }
        .btn-edit { background: #009FBD; }
        .btn-delete { background: #C71E64; color: white; }
        .btn-review { background: var(--primary-color); color: white; }
        
        .btn-table svg {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 4px;
            stroke: white;
            fill: none;
            stroke-width: 3; 
        }
        
        .content-container h3 svg {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
            stroke: var(--text-dark);
            fill: none;
            stroke-width: 3; 
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
    
    <a href="dashboard.php" class="nav-link active">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
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
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
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
                <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn-action" style="background: #dc3545; padding: 8px 10px; font-size: 0.8em;">Clear</a>
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
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Overview</p>
        <h2>Welcome back, <?= $nama_admin; ?>!</h2> 
        <p style="font-size: 0.9em;">You have full control over system's item and finance management.</p>
    </div>
    
    <div class="content-container">
        
        <h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package">
                <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                <path d="M2.5 14.4V9.4l9-5 9 5v5l-9 5z"></path>
                <polyline points="2.5 7.6 12 13 21.5 7.6"></polyline>
                <polyline points="12 22.4 12 13"></polyline>
            </svg>
            Data Barang
        </h3>
        
        <div class="action-buttons">
            <a href="barang_tambah.php" class="btn-action btn-tambah">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Tambah Barang
            </a>
        </div>


        <table>
            <tr>
                <th>ID</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Kondisi</th>
                <th>Harga</th>
                <th>Gambar</th>
                <th>Aksi</th>
            </tr>
            <?php 
            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['barang_id']; ?></td>
                <td><?= htmlspecialchars($row['nama_barang']); ?></td>
                <td><?= htmlspecialchars($row['kategori']); ?></td>
                <td><?= htmlspecialchars($row['kondisi']); ?></td>
                <td>Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                <td>
                    <?php if (!empty($row['gambar'])): ?>
                        <img src="../assets/uploads/<?= htmlspecialchars($row['gambar']); ?>" width="60" style="border-radius: 4px; object-fit: cover; height: 60px;">
                    <?php else: ?>
                        (Tanpa Gambar)
                    <?php endif; ?>
                </td>
                <td>
                    <a href="barang_edit.php?id=<?= $row['barang_id']; ?>" class="btn-table btn-edit">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit-2">
                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                        </svg>
                        Edit
                    </a>
                    <a href="barang_hapus.php?id=<?= $row['barang_id']; ?>" class="btn-table btn-delete" onclick="return confirm('Yakin hapus barang ini?');"> 
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                        Hapus
                    </a>
                    <a href="review_barang.php?id=<?= $row['barang_id']; ?>" class="btn-table btn-review">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        Lihat Review
                    </a>
                </td>
            </tr>
            <?php endwhile; 
            else: ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">
                    <?php if (isset($conn) && !empty($search_query)): ?>
                        **Barang tidak ditemukan** untuk kata kunci "<?= htmlspecialchars($search_query); ?>".
                    <?php elseif (!isset($conn)): ?>
                        **Koneksi database gagal!** Harap periksa file `config/db.php` Anda.
                    <?php else: ?>
                        **Belum ada data barang** yang dimasukkan.
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

</div>

</body>
</html>