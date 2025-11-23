<?php
session_start();
// PERBAIKAN JALUR (PATH) FILE: Keluar dua tingkat untuk mencapai config/db.php
include '../../config/db.php'; 

// Cek Login dan ID Pesanan
if (!isset($_SESSION['pengantar_id']) || !isset($_GET['id'])) {
    echo "<script>alert('Akses ditolak atau ID Pesanan tidak ditemukan.'); window.location='dashboard.php';</script>";
    exit;
}

$id_kirim = intval($_GET['id']);
// Asumsi: Anda sudah punya $nama_kurir dari SESSION atau query
$nama_kurir = $_SESSION['nama_lengkap'] ?? 'Kurir'; 
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporkan Gagal Antar</title>
    <style>
        /* BASE STYLES */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f4f6f9; /* Background Abu Muda */
            margin: 0; 
            padding: 0; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        /* CONTAINER PUSAT */
        .main-card {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Bayangan Lembut */
            width: 100%;
            max-width: 500px;
        }

        /* HEADER & JUDUL (Warna Ungu Dominan) */
        .form-header {
            background-color: #6a0dad; /* Ungu Khas */
            color: white;
            padding: 20px 30px;
            margin: -30px -30px 25px -30px; /* Extends beyond card padding */
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            text-align: center;
        }
        .form-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .form-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* FORM ELEMENTS */
        label { 
            font-weight: 600; 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
        }
        textarea { 
            width: 100%; 
            height: 150px; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            box-sizing: border-box; 
            resize: vertical; 
            font-size: 15px;
            transition: border-color 0.3s;
        }
        textarea:focus {
            border-color: #6a0dad; /* Ungu saat focus */
            outline: none;
        }
        
        /* BUTTON GROUP */
        .btn-group { 
            display: flex; 
            justify-content: flex-end; 
            gap: 10px;
        }
        .btn { 
            padding: 12px 20px; 
            border-radius: 8px; 
            color: white; 
            text-decoration: none; 
            cursor: pointer; 
            border: none; 
            font-weight: 600; 
            font-size: 15px;
            transition: background-color 0.2s;
        }
        .btn-submit { 
            background-color: #dc3545; /* Merah untuk Aksi Gagal */
        }
        .btn-submit:hover { 
            background-color: #c82333; 
        }
        .btn-cancel { 
            background-color: #6c757d; 
        }
        .btn-cancel:hover { 
            background-color: #5a6268; 
        }
    </style>
</head>
<body>

<div class="main-card">
    
    <div class="form-header">
        <h1>LAPORAN KEGAGALAN ANTAR</h1>
        <p>Pesanan #<?= $id_kirim; ?> akan dikembalikan ke antrian Admin.</p>
    </div>

    <form action="antar_gagal.php" method="POST">
        
        <input type="hidden" name="id_kirim" value="<?= htmlspecialchars($id_kirim); ?>">
        
        <label for="catatan">Alasan Detail Kegagalan:</label>
        <textarea 
            name="catatan" 
            id="catatan" 
            required 
            placeholder="Contoh: Penerima tidak berada di alamat saat kurir tiba. Sudah dihubungi 2x, tidak diangkat. Mohon reschedule atau hubungi Admin."
        ></textarea>
        
        <div class="btn-group">
            <a href="detail_pesanan.php?id=<?= $id_kirim; ?>" class="btn btn-cancel">Batal / Kembali</a>
            <button type="submit" class="btn btn-submit">Laporkan Gagal Antar</button>
        </div>
    </form>

</div>

</body>
</html>