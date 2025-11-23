<?php
session_start();
// PERBAIKAN JALUR INCLUDE: Dari '../db.php' menjadi '../../config/db.php'
include '../../config/db.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: pesanan_pelanggan.php");
    exit;
}

// Gunakan intval untuk memastikan ID adalah integer
$id_pesanan = intval($_GET['id']);

// Ambil data pesanan (Menggunakan Prepared Statement)
$stmt_select = $conn->prepare("SELECT user_id, nama_pelanggan, nama_barang FROM pesanan_pelanggan WHERE id_pesanan = ?");
$stmt_select->bind_param("i", $id_pesanan);
$stmt_select->execute();
$result = $stmt_select->get_result();
$pesanan = $result->fetch_assoc();
$stmt_select->close();

if (!$pesanan) {
    echo "<script>alert('Pesanan tidak ditemukan atau sudah diproses.'); window.location='pesanan_pelanggan.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input alasan dari form
    $alasan = $_POST['alasan']; 

    $user_id = $pesanan['user_id'];
    $nama = $pesanan['nama_pelanggan'];
    $barang = $pesanan['nama_barang'];
    
    // 1. Simpan ke tabel penjelasan_pengiriman (Menggunakan Prepared Statement untuk keamanan)
    $stmt_insert_penjelasan = $conn->prepare("INSERT INTO penjelasan_pengiriman (user_id, nama_pelanggan, nama_barang, alasan) VALUES (?, ?, ?, ?)");
    $stmt_insert_penjelasan->bind_param("isss", $user_id, $nama, $barang, $alasan);
    
    if (!$stmt_insert_penjelasan->execute()) {
         // Handle error jika penyimpanan penjelasan gagal
         $error_message = $conn->error;
         $stmt_insert_penjelasan->close();
         echo "<script>alert('❌ Gagal mencatat alasan. Error: {$error_message}'); window.location='pesanan_pelanggan.php';</script>";
         exit;
    }
    $stmt_insert_penjelasan->close();

    // 2. Kirim notifikasi ke user (Menggunakan Prepared Statement)
    $pesan = "Pesanan Anda untuk barang '{$barang}' tidak dapat diantar. Alasan: {$alasan}";
    $stmt_notif = $conn->prepare("INSERT INTO notifikasi_user (user_id, pesan) VALUES (?, ?)");
    $stmt_notif->bind_param("is", $user_id, $pesan);
    $stmt_notif->execute(); 
    $stmt_notif->close();

    // 3. Hapus dari pesanan pelanggan (Menggunakan Prepared Statement)
    $stmt_delete = $conn->prepare("DELETE FROM pesanan_pelanggan WHERE id_pesanan = ?");
    $stmt_delete->bind_param("i", $id_pesanan);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 4. Sukses
    echo "<script>alert('Pesanan #{$id_pesanan} berhasil dibatalkan dan notifikasi telah dikirim ke pelanggan.'); window.location='pesanan_pelanggan.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Penjelasan Pengiriman Gagal</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding-top: 100px;
    text-align: center;
}
form {
    background: white;
    width: 400px;
    max-width: 90%; 
    margin: 0 auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
h2 {
    color: #C71E64; /* Warna merah/cancel */
    margin-bottom: 5px;
}
p {
    color: #666;
    margin-bottom: 20px;
}
textarea {
    width: calc(100% - 22px);
    height: 100px;
    padding: 10px;
    margin-top: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}
button {
    margin-top: 20px;
    padding: 10px 20px;
    border: none;
    background: #C71E64; /* Merah untuk Batal */
    color: white;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.2s;
}
button:hover { background: #9b174d; }

.item-info {
    font-weight: bold;
    color: #5b46b6; /* Warna ungu primary */
}
</style>
</head>
<body>

<h2>⚠️ Pembatalan Pesanan #<?= htmlspecialchars($id_pesanan); ?></h2>
<p>Pesanan untuk barang <span class="item-info"><?= htmlspecialchars($pesanan['nama_barang']); ?></span> tidak dapat diproses.</p>
<p>Silakan masukkan alasan kenapa pesanan ini dibatalkan.</p>

<form method="post">
    <textarea name="alasan" required placeholder="Contoh: Stok habis, Alamat tidak terjangkau kurir, dll."></textarea><br>
    <button type="submit">Kirim Penjelasan & Batalkan Pesanan</button>
</form>

</body>
</html>