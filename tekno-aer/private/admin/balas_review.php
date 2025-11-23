<?php
session_start(); // Wajib ada untuk membaca $_SESSION
// 🛠️ PERBAIKAN 1: Mengubah path ke db.php (Naik dua tingkat)
include '../../config/db.php'; 

// =========================================================
// 1. Verifikasi Admin Login & Role
// =========================================================

// Redirect jika sesi admin_id tidak ditemukan
if (!isset($_SESSION['admin_id'])) {
    // Diasumsikan login.php ada di folder yang sama
    header("Location: login.php"); 
    exit;
}

$admin_id = $_SESSION['admin_id'];

// 🛠️ Tambahan: Cek koneksi database
if (!isset($conn)) {
    // Jika koneksi gagal, arahkan ke halaman utama admin dengan pesan error
    header("Location: dashboard.php?err=" . urlencode("Koneksi database gagal."));
    exit;
}

// =========================================================
// 2. Proses Data POST
// =========================================================

if (isset($_POST['balas'])) {
    
    // Validasi input wajib
    if (empty($_POST['id_review']) || empty($_POST['barang_id']) || empty(trim($_POST['balasan_admin']))) {
        // Balik ke halaman sebelumnya (review_barang.php) dengan pesan error
        $msg = "Error: ID Review, ID Barang, dan Balasan wajib diisi.";
        $barang_id_err = isset($_POST['barang_id']) ? (int) $_POST['barang_id'] : 0;
        $redirect_url_err = "review_barang.php?id=" . $barang_id_err . "&msg=" . urlencode($msg);
        header("Location: {$redirect_url_err}");
        exit;
    }

    $id_review      = (int) $_POST['id_review'];
    $barang_id      = (int) $_POST['barang_id'];
    $balasan_admin  = trim($_POST['balasan_admin']);
    
    // Halaman tujuan redirect setelah aksi selesai
    $redirect_url = "review_barang.php?id=" . $barang_id; 

    // =========================================================
    // 3. Update Tabel Review menggunakan Prepared Statement
    // =========================================================
    
    // Perintah SQL untuk mengupdate balasan, admin_id, dan waktu_balas
    $sql = "UPDATE review 
            SET balasan_admin=?, admin_id=?, waktu_balas=NOW() 
            WHERE id_review=? AND barang_id=?";
            
    $stmt = $conn->prepare($sql);
    
    // Bind parameter: s (string) untuk balasan, i (integer) untuk admin_id, id_review, dan barang_id
    $stmt->bind_param("siii", $balasan_admin, $admin_id, $id_review, $barang_id);
    
    if ($stmt->execute()) {
        $msg = "Balasan berhasil dikirim!";
        // Redirect dengan pesan sukses
        header("Location: {$redirect_url}&msg=" . urlencode($msg));
    } else {
        $msg = "Gagal mengirim balasan: " . $stmt->error;
        // Redirect dengan pesan gagal
        header("Location: {$redirect_url}&msg=" . urlencode($msg));
    }
    
    $stmt->close();
    
} else {
    // Jika diakses tanpa submit form, alihkan ke dashboard
    header("Location: dashboard.php");
    exit;
}
?>