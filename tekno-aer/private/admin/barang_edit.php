<?php
session_start();
// Path ke db.php tetap benar: naik dua tingkat (dari private/admin/ ke tekno-aer/config/)
include '../../config/db.php'; 

// Pastikan hanya admin yang login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 🛠️ Tambahan: Cek koneksi database
if (!isset($conn)) {
    echo "<script>alert('Error: Koneksi database gagal.'); window.location='dashboard.php';</script>";
    exit;
}

// Logika untuk Sidebar (Menggunakan Prepared Statement untuk Admin Data)
$admin_id = $_SESSION['admin_id'];
$stmt_admin = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$admin = $stmt_admin->get_result()->fetch_assoc();
$stmt_admin->close();

$nama_admin = isset($admin['nama_lengkap']) ? $admin['nama_lengkap'] : (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin');
$role_admin = isset($admin['role']) ? $admin['role'] : 'Superuser';

// Menggunakan Prepared Statement untuk jumlah pesanan
$stmt_pesanan = $conn->prepare("SELECT COUNT(*) AS total FROM pesanan_pelanggan");
$stmt_pesanan->execute();
$jumlah_pesanan = $stmt_pesanan->get_result()->fetch_assoc()['total'];
$stmt_pesanan->close();

// Logika Ambil Data Barang berdasarkan ID (Menggunakan Prepared Statement)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'];
$stmt_data = $conn->prepare("SELECT * FROM barang WHERE barang_id=?");
$stmt_data->bind_param("i", $id);
$stmt_data->execute();
$data = $stmt_data->get_result()->fetch_assoc();
$stmt_data->close();

// Jika barang tidak ditemukan
if (!$data) {
    echo "<script>alert('Barang tidak ditemukan.'); window.location='dashboard.php';</script>";
    exit;
}


// =========================================================
// Logika Edit Barang (Menggunakan Prepared Statement)
// =========================================================
if (isset($_POST['update'])) {
    $nama_barang = trim($_POST['nama_barang']);
    $kategori = trim($_POST['kategori']);
    $kondisi = $_POST['kondisi'];
    $keterangan = trim($_POST['keterangan']);
    $harga = (int)$_POST['harga'];
    $gambar = $data['gambar']; // Gambar lama

    // Upload gambar baru jika ada
    if (!empty($_FILES['gambar']['name'])) {
        // 🛠️ PERBAIKAN 1: Path target diubah menjadi relatif ke folder uploads di private/assets
        // Path PHP untuk upload: dari private/admin/ ke private/assets/uploads/
        $target_dir = "../assets/uploads/"; 
        
        // Pastikan folder ada (gunakan path relatif)
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        // Amankan nama file
        $file_extension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
            $gambar = $file_name;
            // Opsional: Hapus gambar lama jika ada
            if (!empty($data['gambar']) && file_exists($target_dir . $data['gambar'])) {
                // 🛠️ PERBAIKAN 3: Menggunakan path target yang sudah disesuaikan
                unlink($target_dir . $data['gambar']);
            }
        } else {
            echo "<script>alert('Error: Gagal mengupload gambar baru.'); window.location='barang_edit.php?id={$id}';</script>";
            exit;
        }
    }

    // Update data menggunakan Prepared Statement
    $sql_update = "UPDATE barang SET 
                 nama_barang=?,
                 kategori=?,
                 kondisi=?,
                 keterangan=?,
                 harga=?,
                 gambar=?
               WHERE barang_id=?";
               
    $stmt_update = $conn->prepare($sql_update);
    
    // Bind parameter: sssssii (string, string, string, string, integer, string, integer)
    $stmt_update->bind_param("ssssisi", $nama_barang, $kategori, $kondisi, $keterangan, $harga, $gambar, $id);
    
    if ($stmt_update->execute()) {
        echo "<script>alert('Data barang berhasil diperbarui!'); window.location='dashboard.php';</script>";
    } else {
        echo "<script>alert('Error saat update database: " . $stmt_update->error . "'); window.location='barang_edit.php?id={$id}';</script>";
    }
    
    $stmt_update->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Barang #<?= htmlspecialchars($id); ?> - Admin TeknoAER</title>
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
    .content-container h3 {
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
        background: #007bff; /* Biru untuk Update */
        color: white;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.2s;
        font-size: 1em;
        display: inline-flex;
        align-items: center;
    }
    .btn-submit:hover {
        background: #0056b3;
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

    .current-image {
        margin-top: 10px; 
        display: flex;
        flex-direction: column;
        gap: 5px;
        font-size: 0.9em;
    }
    .current-image img { 
        max-width: 200px; /* Batasi lebar gambar agar tidak terlalu besar */
        height: auto; 
        border-radius: 8px; 
        border: 1px solid #eee;
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
        <h2>Edit Barang #<?= htmlspecialchars($id); ?></h2> 
        <p style="font-size: 0.9em;">Perbarui informasi untuk barang **<?= htmlspecialchars($data['nama_barang']); ?>**.</p>
    </div>
    
    <div class="content-container">
        
        <h3>Formulir Edit Barang</h3>
        
        <form method="post" enctype="multipart/form-data">
            <label>Nama Barang</label>
            <input type="text" name="nama_barang" value="<?= htmlspecialchars($data['nama_barang']); ?>" required>

            <label>Kategori</label>
            <input type="text" name="kategori" value="<?= htmlspecialchars($data['kategori']); ?>" required>

            <label>Kondisi</label>
            <select name="kondisi" required>
                <option value="baru" <?= $data['kondisi'] == 'baru' ? 'selected' : ''; ?>>Baru</option>
                <option value="bekas" <?= $data['kondisi'] == 'bekas' ? 'selected' : ''; ?>>Bekas</option>
            </select>

            <label>Harga (Rp)</label>
            <input type="number" name="harga" min="0" value="<?= htmlspecialchars($data['harga']); ?>" required>

            <label>Keterangan</label>
            <textarea name="keterangan" rows="4" required><?= htmlspecialchars($data['keterangan']); ?></textarea>

            <label>Foto Barang (Opsional: Pilih file baru jika ingin mengganti)</label>
            <input type="file" name="gambar" accept="image/*">
            
            <?php if (!empty($data['gambar'])): ?>
                <div class="current-image">
                    **Gambar Saat Ini:**
                    <img src="../assets/uploads/<?= htmlspecialchars($data['gambar']); ?>" alt="Gambar barang">
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" name="update" class="btn-submit">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 5px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Simpan Perubahan
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