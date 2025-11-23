<?php
session_start();
// PERBAIKAN JALUR INCLUDE: Dari '../db.php' menjadi '../../config/db.php'
include '../../config/db.php';

// Cek hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['id_pesanan']) || !isset($_POST['pengantar_id'])) {
    die("ID Pesanan atau ID Pengantar tidak ditemukan.");
}

$id_pesanan = intval($_POST['id_pesanan']);
$pengantar_id = intval($_POST['pengantar_id']);

if ($pengantar_id === 0) {
    echo "<script>alert('Harap pilih Pengantar yang valid.'); window.location='pesanan_pelanggan.php';</script>";
    exit;
}

// 1. Ambil data pesanan dari tabel pesanan_pelanggan 
$stmt_select = $conn->prepare("SELECT * FROM pesanan_pelanggan WHERE id_pesanan = ?");
$stmt_select->bind_param("i", $id_pesanan);
$stmt_select->execute();
$result = $stmt_select->get_result();
$data = $result->fetch_assoc();
$stmt_select->close();

if (!$data) {
    echo "<script>alert('Data pesanan tidak ditemukan. Mungkin sudah diproses?'); window.location='pesanan_pelanggan.php';</script>";
    exit;
}

// =============================================================
// 2. Masukkan ke tabel pesanan_dikirim (Menggunakan harga_total)
// =============================================================
$stmt_insert = $conn->prepare("
    INSERT INTO pesanan_dikirim 
    (id_pesanan, user_id, barang_id, nama_barang, jumlah, harga_total, alamat_pengiriman, pengantar_id, status, waktu)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dikirim', NOW())
");

// Bind parameters: iiisissi (8 parameter: id, user_id, barang_id, nama_barang(s), jumlah, total(s), alamat_pengiriman(s), pengantar_id)
$stmt_insert->bind_param(
    "iiisissi",
    $data['id_pesanan'],
    $data['user_id'],
    $data['barang_id'],
    $data['nama_barang'],
    $data['jumlah'],
    $data['total'], // Menggunakan data['total'] sebagai nilai untuk kolom harga_total
    $data['alamat_pengiriman'],
    $pengantar_id
);

if (!$stmt_insert->execute()) {
    $error_message = $conn->error;
    $stmt_insert->close();
    echo "<script>alert('❌ Gagal memasukkan data ke pesanan_dikirim. Error DB: {$error_message}.'); window.location='pesanan_pelanggan.php';</script>";
    exit;
}
$stmt_insert->close();


// =============================================================
// 3. Hapus data dari SUMBER
// =============================================================
// Hapus dari pesanan_pelanggan (Antrian Admin)
$stmt_delete_admin = $conn->prepare("DELETE FROM pesanan_pelanggan WHERE id_pesanan = ?");
$stmt_delete_admin->bind_param("i", $id_pesanan);
$stmt_delete_admin->execute();
$stmt_delete_admin->close();

// Hapus dari pesanan_saya (Antrian User 'Diproses')
$stmt_delete_user = $conn->prepare("DELETE FROM pesanan_saya WHERE id_pesanan = ? AND user_id = ?");
$stmt_delete_user->bind_param("ii", $id_pesanan, $data['user_id']);
$stmt_delete_user->execute();
$stmt_delete_user->close();


// 4. Kirim notifikasi ke User (Opsional)
$pesan_notif = "Pesanan Anda #{$id_pesanan} ({$data['nama_barang']}) telah diserahkan kepada pengantar dan sedang dikirim.";
$stmt_notif = $conn->prepare("INSERT INTO notifikasi_user (user_id, pesan) VALUES (?, ?)");
$stmt_notif->bind_param("is", $data['user_id'], $pesan_notif);
$stmt_notif->execute();
$stmt_notif->close();


// 5. Sukses
echo "<script>alert('Pesanan #{$id_pesanan} berhasil ditetapkan ke pengantar #{$pengantar_id} dan siap dikirim!'); window.location='pesanan_pelanggan.php';</script>";
exit;
?>