<?php
session_start();
// 🛠️ PERBAIKAN 1: Mengubah path ke db.php (Naik dua tingkat)
include '../../config/db.php'; 

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 🛠️ Tambahan: Cek koneksi database
if (!isset($conn)) {
    echo "<script>alert('Error: Koneksi database gagal.'); window.location='dashboard.php';</script>";
    exit;
}

// Logika untuk mengambil data admin (Menggunakan Prepared Statement)
$admin_id = $_SESSION['admin_id'];
$stmt_admin = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$admin = $stmt_admin->get_result()->fetch_assoc();
$stmt_admin->close();

$nama_admin = isset($admin['nama_lengkap']) ? $admin['nama_lengkap'] : (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin');
$role_admin = isset($admin['role']) ? $admin['role'] : 'Superuser';

// Logika untuk menghitung jumlah pesanan total (Menggunakan Prepared Statement)
$stmt_pesanan = $conn->prepare("SELECT COUNT(*) AS total FROM pesanan_pelanggan");
$stmt_pesanan->execute();
$jumlah_pesanan = $stmt_pesanan->get_result()->fetch_assoc()['total'];
$stmt_pesanan->close();

// =========================================================
// Proses tambah barang (MENGGUNAKAN PREPARED STATEMENT)
// =========================================================
if (isset($_POST['submit'])) {
    
    // Ambil dan bersihkan data POST
    $nama_barang = trim($_POST['nama_barang']);
    $kategori = trim($_POST['kategori']);
    $kondisi = $_POST['kondisi'];
    $keterangan = trim($_POST['keterangan']);
    $harga = (int)$_POST['harga'];

    // Upload gambar
    $gambar = '';
    if (!empty($_FILES['gambar']['name'])) {
        // 🛠️ PERBAIKAN 2: Path target diubah menjadi relatif ke private/assets/uploads/
        // Path PHP untuk upload: dari private/admin/ ke private/assets/uploads/
        $target_dir = "../assets/uploads/";
        
        // Pastikan folder ada
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        // Amankan nama file
        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
            $gambar = $file_name;
        } else {
            echo "<script>alert('Error: Gagal mengupload gambar.'); window.history.back();</script>";
            exit;
        }
    }

    // Simpan ke database menggunakan Prepared Statement
    $sql = "INSERT INTO barang (nama_barang, kategori, kondisi, keterangan, harga, gambar)
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameter: ssssis (string, string, string, string, integer, string)
    $stmt->bind_param("ssssis", $nama_barang, $kategori, $kondisi, $keterangan, $harga, $gambar);
    
    if ($stmt->execute()) {
        echo "<script>alert('Barang berhasil ditambahkan!'); window.location='dashboard.php';</script>";
    } else {
        echo "<script>alert('Error saat menyimpan database: " . $stmt->error . "'); window.history.back();</script>";
    }
    
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Barang - Admin TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
<style>
    /* CSS DARI DASHBOARD YANG KONSISTEN */
    :root {
        --primary-color: #5b46b6; /* Ungu Utama */
        --secondary-color: #6c54c3; /* Ungu Lebih Terang */
        --text-dark: #333;
        --bg-light: #f4f6f9;
        --input-border: #ccc;
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

    /* Style untuk Tombol Aksi (Logout) */
    .btn-action {
        display: inline-flex;
        align-items: center;
        padding: 10px 15px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        font-size: 0.9em;
        transition: opacity 0.2s;
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
    
    /* Konten Utama Container (untuk form) */
    .content-container {
        background-color: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    /* Style Form Baru */
    .content-container h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
        color: var(--primary-color);
    }
    
    form label { 
        display: block; 
        margin-top: 15px; 
        font-weight: bold; 
        color: var(--text-dark); 
        margin-bottom: 5px;
    }
    
    input[type="text"], input[type="number"], textarea, select, input[type="file"] {
        width: 100%;
        padding: 10px 12px;
        margin-top: 5px; 
        border-radius: 6px;
        border: 1px solid var(--input-border);
        box-sizing: border-box;
        font-size: 1em;
        transition: border-color 0.2s;
    }
    input:focus, textarea:focus, select:focus {
        border-color: var(--primary-color);
        outline: none;
    }

    .form-actions {
        display: flex;
        justify-content: flex-start;
        gap: 15px;
        margin-top: 30px;
    }
    
    .btn-submit {
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        background: #28a745; /* Hijau untuk Simpan */
        color: white;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.2s;
        font-size: 1em;
        display: inline-flex;
        align-items: center;
    }
    .btn-submit:hover {
        background: #1e7e34;
    }

    .btn-cancel { 
        display: inline-flex; 
        align-items: center;
        padding: 12px 20px;
        background: #6c757d; /* Abu-abu untuk Kembali */
        color: white; 
        text-decoration: none; 
        border-radius: 8px;
        font-weight: bold;
        transition: background-color 0.2s;
    }
    .btn-cancel:hover {
        background: #5a6268;
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
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Manajemen Data</p>
        <h2>Tambah Barang Baru</h2> 
        <p style="font-size: 0.9em;">Isi formulir di bawah ini untuk menambahkan barang baru ke dalam inventaris toko.</p>
    </div>
    
    <div class="content-container">
        
        <h2>Formulir Tambah Barang</h2>
        
        <form method="post" enctype="multipart/form-data">
            <label>Nama Barang</label>
            <input type="text" name="nama_barang" required>

            <label>Kategori</label>
            <input type="text" name="kategori" required>

            <label>Kondisi</label>
            <select name="kondisi" required>
                <option value="baru">Baru</option>
                <option value="bekas">Bekas</option>
            </select>

            <label>Harga (Rp)</label>
            <input type="number" name="harga" min="0" placeholder="Masukkan harga barang" required>

            <label>Keterangan</label>
            <textarea name="keterangan" rows="4" required></textarea>

            <label>Foto Barang</label>
            <input type="file" name="gambar" accept="image/*">

            <div class="form-actions">
                <button type="submit" name="submit" class="btn-submit">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 5px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Simpan Barang
                </button>
                <a href="dashboard.php" class="btn-cancel">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 5px;">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Kembali
                </a>
            </div>
        </form>
    </div>

</div>

</body>
</html>