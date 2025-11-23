<?php
session_start();
// PERBAIKAN JALUR INCLUDE: Dari private/admin/ ke config/
include '../../config/db.php';

// Cegah akses tanpa login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data admin yang sedang login
$admin_id = $_SESSION['admin_id'];
$admin = [];

if (isset($conn)) {
    // Menggunakan Prepared Statement untuk mengambil data admin
    $stmt_admin = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    // Penanganan error saat prepare
    if ($stmt_admin === false) {
        die('MySQL prepare error: ' . $conn->error);
    }
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();
    $admin = $result_admin->fetch_assoc();
    $stmt_admin->close();
}

// Data Admin untuk Tampilan
$nama_admin = isset($admin['nama_lengkap']) ? htmlspecialchars($admin['nama_lengkap']) : (isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin');
$role_admin = isset($admin['role']) ? htmlspecialchars($admin['role']) : 'Superuser';

// --- LOGIKA PENCARIAN PESANAN DIMULAI ---
$search_query = "";
$result = false;
$jumlah_pesanan = 0;

if (isset($conn)) {
    
    // 1. Ambil jumlah pesanan total untuk Sidebar (jumlah transaksi unik)
    $jumlah_pesanan = $conn->query("SELECT COUNT(DISTINCT id_pesanan) AS total FROM pesanan_pelanggan")->fetch_assoc()['total'];

    // 2. Siapkan klausa pencarian (tetap menggunakan GROUP BY)
    $where_clause = "";
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        // Gunakan prepared statement untuk pencarian
        $search_term = "%" . trim($_GET['search']) . "%";
        $search_query = trim($_GET['search']);
        
        // Klausa WHERE akan digunakan untuk memfilter sebelum GROUP BY
        $where_clause = " WHERE id_pesanan LIKE ? OR nama_pelanggan LIKE ? OR nama_barang LIKE ? ";
        
        $sql = "SELECT 
                    id_pesanan,
                    user_id,
                    nama_pelanggan,
                    alamat_pengiriman,
                    GROUP_CONCAT(CONCAT(nama_barang, ' (', jumlah, 'x)') SEPARATOR '<br>') AS daftar_barang,
                    SUM(total) AS grand_total_transaksi
                FROM pesanan_pelanggan" . $where_clause . " 
                GROUP BY id_pesanan, user_id, nama_pelanggan, alamat_pengiriman
                ORDER BY id_pesanan DESC";

        $stmt = $conn->prepare($sql);
        // Bind parameter untuk 3 placeholder LIKE
        $stmt->bind_param("sss", $search_term, $search_term, $search_term); 
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
    } else {
        // Tanpa Pencarian
        $sql = "SELECT 
                    id_pesanan,
                    user_id,
                    nama_pelanggan,
                    alamat_pengiriman,
                    GROUP_CONCAT(CONCAT(nama_barang, ' (', jumlah, 'x)') SEPARATOR '<br>') AS daftar_barang,
                    SUM(total) AS grand_total_transaksi
                FROM pesanan_pelanggan 
                GROUP BY id_pesanan, user_id, nama_pelanggan, alamat_pengiriman
                ORDER BY id_pesanan DESC";
        $result = $conn->query($sql);
    }
} else {
    // Jika koneksi tidak ada
    $result = (object) ['num_rows' => 0];
}
// --- LOGIKA PENCARIAN PESANAN SELESAI ---

$search_term = $search_query; // Untuk nilai input form
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Pelanggan | Dashboard Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
    <style>
        /* CSS Umum dan Warna (Meniru Tampilan Dashboard.php) */
        :root {
            --primary-color: #5b46b6; /* Ungu Utama */
            --secondary-color: #6c54c3; /* Ungu Lebih Terang */
            --text-dark: #333;
            --bg-light: #f4f6f9;
            --btn-process: #009FBD; /* Biru/Cyan untuk Aksi Proses */
            --btn-cancel: #C71E64; /* Merah Tua untuk Batal */
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
        .btn-action:hover { opacity: 0.9; }
        
        /* Styling untuk ikon di tombol aksi (Logout, Clear) */
        .btn-action svg {
             width: 16px;
             height: 16px;
             vertical-align: middle;
             margin-right: 5px; /* Jarak antara ikon dan teks */
             stroke: white;
             fill: none;
             stroke-width: 3; 
        }

        /* Style Form Pencarian */
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
            margin-right: 0; /* Override margin-right dari btn-action svg */
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
           display: inline-flex;
           align-items: center;
        }
        .btn-process { background: var(--btn-process); } /* Aksi utama: Proses/Pilih Kurir */
        .btn-cancel { background: var(--btn-cancel); color: white; } /* Aksi Batal */
        
        .btn-table svg {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 4px;
            stroke: white;
            fill: none;
            stroke-width: 3; 
        }
        
        /* Icon di dalam H3 */
        .content-container h3 svg {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
            stroke: var(--text-dark);
            fill: none;
            stroke-width: 3;
        }
        
        /* Style tambahan untuk daftar barang yang digabungkan */
        .item-list {
            font-size: 0.9em;
            line-height: 1.4;
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
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
            <input 
                type="text" 
                name="search" 
                placeholder="Cari ID Pesanan/Pelanggan/Barang..." 
                value="<?= htmlspecialchars($search_term); ?>"
            >
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
            <?php if (!empty($search_term)): ?>
                <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn-action" style="background: #dc3545; padding: 8px 10px; font-size: 0.8em;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x-square" style="margin-right: 0;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
        
        <a href="logout.php" class="btn-action" style="background: var(--primary-color);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
    
    <div class="header-dashboard">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Manajemen Transaksi</p>
        <h2>Daftar Pesanan Pelanggan</h2> 
        <p style="font-size: 0.9em;">Kelola pesanan masuk dan tentukan kurir pengiriman untuk setiap transaksi.</p>
    </div>
    
    <div class="content-container">
        
        <h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-cart">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Pesanan yang Perlu Diproses (<?= $result->num_rows; ?> Transaksi)
        </h3> 

        <table>
            <tr>
                <th>ID Transaksi</th>
                <th>Nama Pelanggan</th>
                <th>Daftar Barang & Jumlah</th>
                <th>Total Transaksi</th>
                <th>Alamat Pengiriman</th>
                <th>Aksi</th>
            </tr>
            <?php 
            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id_pesanan']; ?></td>
                <td><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                <td class="item-list"><?= $row['daftar_barang']; ?></td> 
                <td>Rp.<?= number_format($row['grand_total_transaksi'], 0, ',', '.'); ?></td>
                <td><?= htmlspecialchars($row['alamat_pengiriman']); ?></td>
                <td>
                    <a href="pilih_pengantar.php?id=<?= $row['id_pesanan']; ?>" class="btn-table btn-process">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                        Pilih Kurir
                    </a>
                    
                    <a href="pesanan_tidak_antar.php?id=<?= $row['id_pesanan']; ?>" class="btn-table btn-cancel" onclick="return confirm('Yakin ingin membatalkan transaksi #<?= $row['id_pesanan']; ?>? Tindakan ini tidak dapat diurungkan.');"> 
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x-circle">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        Batal
                    </a>
                </td>
            </tr>
            <?php endwhile; 
            else: ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px;">
                    <?php if (!empty($search_term)): ?>
                        Pesanan tidak ditemukan untuk kata kunci "**<?= htmlspecialchars($search_term); ?>**".
                    <?php else: ?>
                        Belum ada pesanan masuk saat ini yang perlu diproses.
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

</div>

</body>
</html>