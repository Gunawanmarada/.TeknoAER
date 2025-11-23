<?php
session_start();
// 🛠️ PERBAIKAN 1: Mengubah path ke db.php (Naik dua tingkat ke folder config/)
include '../../config/db.php';

// Pastikan ID barang dan status login admin tersedia
if (!isset($_GET['id']) || !isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$barang_id = intval($_GET['id']);
$conn_status = isset($conn) && $conn;

if (!$conn_status) {
    echo "<script>alert('Koneksi database gagal!'); window.location='dashboard.php';</script>";
    exit;
}

// Mulai proses penghapusan data
try {
    // 1. Ambil nama file gambar (Menggunakan Prepared Statement)
    $stmt_select_img = $conn->prepare("SELECT gambar FROM barang WHERE barang_id = ?");
    $stmt_select_img->bind_param("i", $barang_id);
    $stmt_select_img->execute();
    $result_img = $stmt_select_img->get_result();
    $data_img = $result_img->fetch_assoc();
    $gambar_file = $data_img['gambar'] ?? null;
    $stmt_select_img->close();

    // 2. Hapus entri dari tabel yang merujuk ke barang ini (Foreign Key)
    // Menggunakan Prepared Statement untuk keamanan
    $related_tables = ["review", "pesanan_saya", "pesanan_selesai", "pesanan_dikirim"];

    foreach ($related_tables as $table) {
        $stmt_delete_related = $conn->prepare("DELETE FROM $table WHERE barang_id = ?");
        $stmt_delete_related->bind_param("i", $barang_id);
        
        if (!$stmt_delete_related->execute()) {
            // Jika gagal menghapus relasi, batalkan dan tampilkan error
            throw new Exception("Gagal menghapus data terkait di tabel {$table}: " . $conn->error);
        }
        $stmt_delete_related->close();
    }
    
    // 3. Hapus data barang itu sendiri (Menggunakan Prepared Statement)
    $stmt_delete_barang = $conn->prepare("DELETE FROM barang WHERE barang_id = ?");
    $stmt_delete_barang->bind_param("i", $barang_id);
    
    if ($stmt_delete_barang->execute() && $stmt_delete_barang->affected_rows > 0) {
        
        // 4. Hapus file gambar dari server (Path: ../assets/uploads/ sudah benar)
        $upload_dir = '../assets/uploads/';
        if ($gambar_file && file_exists($upload_dir . $gambar_file)) {
            // Menggunakan @unlink untuk menekan error jika file gagal dihapus (misalnya izin)
            @unlink($upload_dir . $gambar_file); 
        }
        
        $stmt_delete_barang->close();
        echo "<script>alert('Barang berhasil dihapus!'); window.location='dashboard.php';</script>";

    } else {
        // ID Barang tidak ditemukan atau gagal dihapus
        throw new Exception("ID Barang tidak ditemukan atau gagal dihapus dari tabel utama.");
    }

} catch (Exception $e) {
    // Tangani semua error yang muncul
    $error_msg = addslashes($e->getMessage());
    echo "<script>alert('Gagal menghapus barang: {$error_msg}'); window.location='dashboard.php';</script>";
}

// Tutup koneksi
if (isset($conn) && $conn) {
    $conn->close();
}
?>