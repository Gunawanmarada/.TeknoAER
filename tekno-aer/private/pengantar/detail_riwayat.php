<?php
session_start();
// PERBAIKAN: Menyesuaikan jalur include db.php agar konsisten
include '../../config/db.php'; 

// 1. Cek Login dan ID Pesanan (Tetap penting)
if (!isset($_SESSION['pengantar_id']) || !isset($_GET['id'])) {
    echo "<script>alert('Akses ditolak atau ID Riwayat tidak ditemukan.'); window.location='dashboard.php';</script>";
    exit;
}

$id_kirim = intval($_GET['id']);
// Menggunakan 'pengantar_id' dari session (sudah benar)
$pengantar_id = $_SESSION['pengantar_id']; 
$nama_kurir = $_SESSION['nama_lengkap'] ?? 'Kurir'; 

// 2. Query untuk mengambil detail dari riwayat_pengiriman
// !!! PERHATIAN: Pastikan tabel 'riwayat_pengiriman' dan 'user' benar di database Anda.
$sql = "
    SELECT 
        r.*, 
        u.nama_lengkap AS nama_pelanggan 
    FROM 
        riwayat_pengiriman r
    LEFT JOIN 
        user u ON r.user_id = u.user_id
    WHERE 
        r.id_kirim = ? AND r.pengantar_id = ?
";

// Menggunakan LEFT JOIN agar detail tetap tampil meskipun data user_id tidak ada
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Jika prepare gagal, tampilkan error MySQL yang sebenarnya.
    die("Fatal Error: Query preparation failed. Cek nama tabel (riwayat_pengiriman/user) atau nama kolom (user_id). MySQL Error: " . $conn->error);
}

// Lanjutkan dengan eksekusi
// Asumsi tipe data id_kirim dan pengantar_id adalah Integer atau String yang cocok
$stmt->bind_param("is", $id_kirim, $pengantar_id); // Mengasumsikan id_kirim INT (i), pengantar_id STRING (s)
$stmt->execute();
$result = $stmt->get_result();
$data_riwayat = $result->fetch_assoc();
$stmt->close();

if (!$data_riwayat) {
    // Pesan ini hanya muncul jika query berhasil tapi data kosong (data Kurir/ID Kirim salah)
    echo "<script>alert('Riwayat tugas tidak ditemukan atau bukan tugas Anda. (ID Kirim: $id_kirim / Kurir ID: $pengantar_id)'); window.location='riwayat_tugas.php';</script>";
    exit;
}

// Data sudah aman di sini
$waktu_selesai_format = date('d M Y, H:i:s', strtotime($data_riwayat['waktu_selesai']));
$status_class = ($data_riwayat['status'] == 'selesai') ? 'status-selesai' : 'status-gagal';
$status_text = ($data_riwayat['status'] == 'selesai') ? '✅ Selesai' : '❌ Gagal';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Riwayat #<?= $id_kirim; ?></title>
    <style>
        /* CSS Dasar */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; justify-content: center; min-height: 100vh; }
        .main-container { padding: 30px; width: 100%; max-width: 1100px; }
        
        /* Header Ungu */
        .purple-header { background-color: #6a0dad; color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; }
        .purple-header h1 { margin-top: 0; font-size: 24px; font-weight: 600; }
        
        /* Card Styles */
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); height: 100%; }
        .card h3 { margin-top: 0; font-size: 18px; padding-bottom: 10px; border-bottom: 1px solid #eee; color: #6a0dad; }
        .detail-item { padding: 8px 0; border-bottom: 1px dotted #eee; }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { font-weight: 500; color: #777; font-size: 14px; display: block; }
        .detail-value { font-weight: 600; color: #333; font-size: 16px; margin-top: 3px; }

        /* Status & Catatan */
        .status { padding: 5px 10px; border-radius: 5px; font-weight: bold; display: inline-block; }
        .status-selesai { background-color: #d4edda; color: #155724; }
        .status-gagal { background-color: #f8d7da; color: #721c24; }
        .catatan-box { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 5px solid #6a0dad; margin-top: 10px; }
        .catatan-box h3 { border-bottom: none; padding-bottom: 0; }
        
        /* Layout Grid */
        .row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
        .col-4 { flex: 0 0 33.333%; padding: 0 10px; margin-bottom: 20px; }
        .col-8 { flex: 0 0 66.666%; padding: 0 10px; margin-bottom: 20px; }
        .col-12 { flex: 0 0 100%; padding: 0 10px; margin-bottom: 20px; }

        .btn { padding: 10px 18px; border-radius: 6px; color: white; text-decoration: none; display: inline-block; cursor: pointer; border: none; font-weight: 600; background-color: #6c757d; margin-top: 10px; }
    </style>
</head>
<body>

<div class="main-container">

    <div class="purple-header">
        <h1>Detail Riwayat Tugas #<?= $id_kirim; ?></h1>
        <p>Ringkasan tugas pengiriman Kurir ID: <?= $pengantar_id; ?></p>
    </div>

    <div class="row">
        
        <div class="col-4">
            <div class="card">
                <h3>Status Tugas</h3>
                <div class="detail-item" style="border-bottom: none;">
                    <span class="detail-label">Status Akhir</span>
                    <span class="detail-value">
                        <span class="status <?= $status_class; ?>"><?= $status_text; ?></span>
                    </span>
                </div>
                <div class="detail-item" style="border-bottom: none;">
                    <span class="detail-label">Waktu Pencatatan</span>
                    <span class="detail-value"><?= $waktu_selesai_format; ?></span>
                </div>
            </div>
        </div>

        <div class="col-4">
            <div class="card">
                <h3>Detail Barang</h3>
                <div class="detail-item">
                    <span class="detail-label">Nama Item</span>
                    <span class="detail-value"><?= htmlspecialchars($data_riwayat['nama_barang']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Jumlah</span>
                    <span class="detail-value"><?= $data_riwayat['jumlah']; ?> Unit</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total Harga (Bayar)</span>
                    <span class="detail-value">Rp. <?= number_format($data_riwayat['total'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <div class="col-4">
            <div class="card">
                <h3>Info Pelanggan</h3>
                <div class="detail-item">
                    <span class="detail-label">Nama Pelanggan</span>
                    <span class="detail-value"><?= htmlspecialchars($data_riwayat['nama_pelanggan'] ?? 'Tidak Dikenal'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">ID Pelanggan</span>
                    <span class="detail-value">#<?= $data_riwayat['user_id']; ?></span>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <h3>Alamat & Catatan Kurir</h3>
                
                <div class="detail-item">
                    <span class="detail-label">Alamat Pengiriman Lengkap</span>
                    <span class="detail-value"><?= htmlspecialchars($data_riwayat['alamat_pengiriman']); ?></span>
                </div>

                <div class="catatan-box">
                    <h3>Catatan Kurir:</h3>
                    <p><?= nl2br(htmlspecialchars($data_riwayat['catatan'])); ?></p>
                </div>
            </div>
        </div>

    </div>
    <a href="riwayat_tugas.php" class="btn">← Kembali ke Daftar Riwayat</a>

</div>

</body>
</html>