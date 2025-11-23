<?php
session_start();
// PERBAIKAN JALUR (PATH) FILE: Keluar dua tingkat untuk mencapai config/db.php
include '../../config/db.php'; 

// Cek Koneksi dan ID Pesanan
if (!isset($_SESSION['pengantar_id']) || !isset($_POST['id_kirim']) || !isset($_POST['catatan'])) {
    echo "<script>alert('Akses ditolak atau Data formulir (ID/Catatan) tidak lengkap.'); window.location='dashboard.php';</script>";
    exit;
}

$id_kirim = intval($_POST['id_kirim']);
$catatan = trim($_POST['catatan']);
$pengantar_id = $_SESSION['pengantar_id'];

if (empty($catatan)) {
    echo "<script>alert('Catatan kegagalan harus diisi.'); window.location='form_gagal_antar.php?id={$id_kirim}';</script>";
    exit;
}

// Mulai Transaksi untuk memastikan semua query sukses atau gagal bersamaan
// PENTING: Panggil begin_transaction() setelah variabel $conn tersedia
$conn->begin_transaction();

try {
    
    // ===========================================
    // 1. Ambil data pesanan lengkap berdasarkan ID KIRIM
    // ===========================================
    $stmt_select = $conn->prepare("SELECT * FROM pesanan_dikirim WHERE id_kirim = ? AND pengantar_id = ?");
    $stmt_select->bind_param("ii", $id_kirim, $pengantar_id);
    $stmt_select->execute();
    $results = $stmt_select->get_result();
    $stmt_select->close();

    if ($results->num_rows === 0) {
        throw new Exception("Pesanan dengan ID Kirim #{$id_kirim} tidak ditemukan, sudah diselesaikan, atau bukan tugas Anda.");
    }

    // Ambil data pelanggan sekali saja
    $row_first = $results->fetch_assoc(); // Ambil satu baris untuk user_id dan info pelanggan
    $user_id = intval($row_first['user_id']);
    
    $q_user = $conn->query("SELECT nama_lengkap FROM user WHERE user_id='$user_id'");
    $d_user = $q_user->fetch_assoc();
    $nama_pelanggan = $conn->real_escape_string($d_user['nama_lengkap'] ?? 'Pelanggan Tidak Dikenal');
    $alamat_pengiriman = $conn->real_escape_string($row_first['alamat_pengiriman'] ?? 'Alamat Tidak Tercatat');
    
    // Pindahkan pointer result set kembali ke awal untuk loop
    $results->data_seek(0);


    // ===========================================
    // 2. Loop melalui setiap item pesanan untuk ROLLBACK dan LOGGING
    // ===========================================
    $status_akhir = 'gagal'; 
    $catatan_riwayat = $conn->real_escape_string($catatan);

    while ($p = $results->fetch_assoc()) {
        
        $barang_id           = intval($p['barang_id']);
        $nama_barang         = $conn->real_escape_string($p['nama_barang']);
        $jumlah              = intval($p['jumlah']);
        $harga_satuan        = $p['harga'] ?? 0; 
        $harga_total_amount  = $p['harga_total'] ?? 0; 
        
        // A. INSERT: KEMBALIKAN ke pesanan_pelanggan (Rollback ke Admin)
        $stmt_insert_fail = $conn->prepare("
            INSERT INTO pesanan_pelanggan 
            (user_id, nama_pelanggan, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman, alamat, tanggal_pesanan, harga_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', NOW(), ?)
        ");
        
        // KOREKSI bind_param (Baris 81 yang error):
        // 9 placeholders (?), 9 variabel. Tipe: (i) user_id, (s) nama_pelanggan, (i) barang_id, (s) nama_barang, (i) jumlah, (s) harga, (s) total, (s) alamat_pengiriman, (s) harga_total
        $stmt_insert_fail->bind_param("isissssss", 
            $user_id, $nama_pelanggan, $barang_id, $nama_barang, $jumlah, 
            $harga_satuan, $harga_total_amount, $alamat_pengiriman, $harga_total_amount 
        );

        if (!$stmt_insert_fail->execute()) {
            throw new Exception("Gagal mengembalikan item: {$nama_barang}. Error SQL: " . $conn->error);
        }
        $stmt_insert_fail->close();
        
        
        // B. INSERT: LOGGING ke riwayat_pengiriman DENGAN STATUS GAGAL (Per item)
        $stmt_insert_log = $conn->prepare("
            INSERT INTO riwayat_pengiriman 
            (id_kirim, user_id, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman, pengantar_id, status, waktu_selesai, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        // KOREKSI bind_param:
        // 11 placeholders (?), 11 variabel. Tipe: (i) id_kirim, (i) user_id, (i) barang_id, (s) nama_barang, (i) jumlah, (s) harga, (s) total, (s) alamat_pengiriman, (i) pengantar_id, (s) status, (s) catatan
        $stmt_insert_log->bind_param("iiisississs", 
            $id_kirim, $user_id, $barang_id, $nama_barang, $jumlah, 
            $harga_satuan, $harga_total_amount, $alamat_pengiriman, $pengantar_id,
            $status_akhir, $catatan_riwayat
        );
        if (!$stmt_insert_log->execute()) {
            throw new Exception("Gagal mencatat riwayat kegagalan item: {$nama_barang}. Error SQL: " . $conn->error);
        }
        $stmt_insert_log->close();
    } // END while loop


    // ===========================================
    // 3. DELETE: Hapus dari pesanan_dikirim (Hapus tugas kurir)
    // ===========================================
    $stmt_delete = $conn->prepare("DELETE FROM pesanan_dikirim WHERE id_kirim = ? AND pengantar_id = ?");
    $stmt_delete->bind_param("ii", $id_kirim, $pengantar_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Gagal menghapus pesanan dari daftar tugas aktif. Error: " . $conn->error);
    }
    $stmt_delete->close();
    
    
    // ===========================================
    // 4. Notifikasi user (Pesan Gagal)
    // ===========================================
    $pesan_user = "❌ Pesanan Anda (#{$id_kirim}) GAGAL DIANTAR. Alasan: {$catatan}. Pesanan dikembalikan ke admin untuk tindak lanjut.";
    $pesan_aman_user = $conn->real_escape_string($pesan_user);
    $conn->query("INSERT INTO notifikasi_user (user_id, pesan) VALUES ('$user_id', '$pesan_aman_user')");


    // COMMIT: Transaksi berhasil
    $conn->commit();
    echo "<script>alert('✅ Gagal antar dicatat, pesanan berhasil dikembalikan ke antrian admin, dan tugas Anda dipindahkan ke riwayat!'); window.location='dashboard.php';</script>";

} catch (Exception $e) {
    // ROLLBACK: Batalkan semua jika ada yang gagal
    $conn->rollback();
    echo "<script>alert('❌ Proses Gagal Total. Detail: " . $e->getMessage() . "'); window.location='detail_pesanan.php?id={$id_kirim}';</script>";
}
?>