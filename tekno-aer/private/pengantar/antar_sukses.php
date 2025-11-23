<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari private/pengantar/) ke tekno-aer/config/
include '../../config/db.php'; 

// 1. Cek Login dan ID Pesanan
// Pastikan $conn ada.
if (!isset($conn) || !isset($_SESSION['pengantar_id']) || !isset($_GET['id'])) {
    // Jika koneksi gagal atau data sesi hilang, arahkan ke dashboard.
    header("Location: dashboard.php");
    exit;
}

$id_kirim = intval($_GET['id']);
$pengantar_id = $_SESSION['pengantar_id'];

// Mulai Transaksi untuk memastikan semua query berhasil atau gagal bersamaan
$conn->begin_transaction();

try {
    // 2. Ambil data dari pesanan_dikirim (SUMBER)
    // Query yang baik, tidak ada perbaikan.
    $stmt_select = $conn->prepare("SELECT * FROM pesanan_dikirim WHERE id_kirim = ? AND pengantar_id = ?");
    $stmt_select->bind_param("ii", $id_kirim, $pengantar_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $data = $result->fetch_assoc();
    $stmt_select->close();

    if (!$data) {
        // Tambahkan Rollback sebelum throw exception
        $conn->rollback();
        throw new Exception("Pesanan tidak ditemukan atau bukan tugas Anda.");
    }
    
    // Asumsikan struktur data:
    $user_id = $data['user_id'];
    $barang_id = $data['barang_id'];
    $nama_barang = $data['nama_barang'];
    $jumlah = $data['jumlah'];
    // Gunakan nilai default 0 agar tipe data integer tetap valid.
    $harga_satuan = $data['harga'] ?? 0; 
    $harga_total = $data['total'] ?? $data['harga_total'] ?? 0; 
    $alamat = $data['alamat_pengiriman'];
    
    // 3. Masukkan data ke tabel RIWAYAT_PENGIRIMAN (TARGET)
    $status_akhir = 'selesai';
    $catatan = 'Pesanan berhasil diantar dan diterima oleh pelanggan.';

    $stmt_insert = $conn->prepare("
        INSERT INTO riwayat_pengiriman 
        (id_kirim, user_id, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman, pengantar_id, status, waktu_selesai, catatan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    // Perhatikan bind_param harus sesuai dengan urutan kolom di tabel riwayat_pengiriman Anda.
    // Asumsi Tipe data: i (id_kirim), i (user_id), i (barang_id), s (nama_barang), i (jumlah), 
    //                 i (harga_satuan), i (harga_total), s (alamat), i (pengantar_id), s (status), s (catatan)
    // Total: iiisisisiss (11 parameter)
    $stmt_insert->bind_param(
        "iiisisisiss", 
        $id_kirim, 
        $user_id, 
        $barang_id, 
        $nama_barang, 
        $jumlah, 
        $harga_satuan, 
        $harga_total, 
        $alamat, 
        $pengantar_id,
        $status_akhir,
        $catatan
    );

    if (!$stmt_insert->execute()) {
        $conn->rollback();
        throw new Exception("Gagal menyimpan data ke riwayat: " . $stmt_insert->error);
    }
    $stmt_insert->close();


    // 4. Hapus data dari tabel pesanan_dikirim
    $stmt_delete = $conn->prepare("DELETE FROM pesanan_dikirim WHERE id_kirim = ? AND pengantar_id = ?");
    $stmt_delete->bind_param("ii", $id_kirim, $pengantar_id);
    if (!$stmt_delete->execute()) {
        $conn->rollback();
        throw new Exception("Gagal menghapus pesanan dari daftar tugas aktif.");
    }
    $stmt_delete->close();
    
    
    // 5. Kirim Notifikasi ke User (Opsional)
    $pesan_notif = "Pesanan Anda #{$id_kirim} ({$nama_barang}) telah berhasil diantar dan diterima oleh Pengantar ID: {$pengantar_id}.";
    $stmt_notif = $conn->prepare("INSERT INTO notifikasi_user (user_id, pesan) VALUES (?, ?)");
    $stmt_notif->bind_param("is", $user_id, $pesan_notif);
    // Eksekusi notifikasi tanpa perlu check error, karena ini opsional.
    $stmt_notif->execute();
    $stmt_notif->close();


    // Commit Transaksi jika semua berhasil
    $conn->commit();

    echo "<script>alert('✅ Tugas pengantaran berhasil diselesaikan dan dicatat dalam riwayat!'); window.location='cek_pengiriman.php';</script>";

} catch (Exception $e) {
    // Rollback Transaksi jika ada yang gagal
    $conn->rollback();

    // Tampilkan pesan error dan arahkan kembali
    echo "<script>alert('❌ Proses Gagal: " . $e->getMessage() . "'); window.location='dashboard.php';</script>";
}

// Tutup koneksi di akhir skrip
if (isset($conn)) {
    $conn->close();
}
?>