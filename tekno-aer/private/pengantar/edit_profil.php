<?php
session_start();
include '../../config/db.php'; // FIX: Jalur ke db.php (Diasumsikan: dua tingkat keluar, masuk config)

// Cek apakah sudah login sebagai pengantar
if (!isset($_SESSION['pengantar_id'])) {
    header("Location: login.php");
    exit;
}

$pengantar_id = $_SESSION['pengantar_id'];
$message = '';
$message_type = '';

// --- 1. Ambil Data Profil Saat Ini ---
$query_pengantar = $conn->query("SELECT pengantar_id, nama, foto_profil FROM pengantar WHERE pengantar_id = '$pengantar_id'");
$pengantar = $query_pengantar->fetch_assoc();

if (!$pengantar) {
    header("Location: logout.php"); 
    exit;
}

$nama_lengkap = $pengantar['nama']; 
$foto_profil_db = $pengantar['foto_profil']; 

// Menentukan path foto yang akan ditampilkan
// FIX: Jalur default ke assets di root
$default_path = '../../assets/images/default.jpg'; 
// FIX: Jalur folder upload kurir yang baru
$upload_dir = '../assets/profil/pengantar/'; 

$photo_path = (empty($foto_profil_db) || $foto_profil_db === 'default.jpg' || !file_exists($upload_dir . $foto_profil_db)) 
             ? $default_path 
             : $upload_dir . $foto_profil_db;


// ************************************************************
// --- LOGIKA UPDATE NAMA (HANYA JALAN JIKA update_nama DITEKAN) ---
// ************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nama'])) {
    $nama_baru = trim($_POST['nama_baru']);
    
    if (empty($nama_baru)) {
        $message = "Nama tidak boleh kosong.";
        $message_type = 'error';
    } else {
        // Hanya update kolom 'nama'
        $q_update = $conn->prepare("UPDATE pengantar SET nama = ? WHERE pengantar_id = ?");
        $q_update->bind_param("si", $nama_baru, $pengantar_id);
        
        if ($q_update->execute()) {
            $message = "Nama profil berhasil diperbarui! 🎉";
            $message_type = 'success';
            $nama_lengkap = $nama_baru;
            $_SESSION['pengantar_nama'] = $nama_baru;
        } else {
            $message = "Gagal menyimpan perubahan nama ke database.";
            $message_type = 'error';
        }
    }
}


// ************************************************************
// --- LOGIKA UPLOAD FOTO (HANYA JALAN JIKA upload_foto DITEKAN) ---
// ************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_foto'])) {
    
    // Cek apakah file diunggah tanpa error
    if (isset($_FILES['foto_baru']) && $_FILES['foto_baru']['error'] === 0) {
        
        $file = $_FILES['foto_baru'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = array('jpg', 'jpeg', 'png');
        $max_size = 2 * 1024 * 1024; // 2 MB

        if (!in_array($file_ext, $allowed_ext)) {
            $message = "Gagal! Hanya file JPG, JPEG, & PNG yang diizinkan.";
            $message_type = 'error';
        } 
        elseif ($file_size > $max_size) {
            $message = "Gagal! Ukuran file maksimal adalah 2MB.";
            $message_type = 'error';
        } 
        else {
            $new_file_name = $pengantar_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (move_uploaded_file($file_tmp, $destination)) {
                
                // Hapus Foto Lama 
                if (!empty($foto_profil_db) && $foto_profil_db !== 'default.jpg' && file_exists($upload_dir . $foto_profil_db)) {
                    unlink($upload_dir . $foto_profil_db);
                }

                // Hanya update kolom 'foto_profil'
                $q_update = $conn->prepare("UPDATE pengantar SET foto_profil = ? WHERE pengantar_id = ?");
                $q_update->bind_param("si", $new_file_name, $pengantar_id);
                
                if ($q_update->execute()) {
                    $message = "Foto profil berhasil diperbarui! 📸";
                    $message_type = 'success';
                    // Update $photo_path agar foto baru langsung muncul
                    $photo_path = $upload_dir . $new_file_name; 
                } else {
                    $message = "Gagal menyimpan perubahan foto ke database.";
                    $message_type = 'error';
                    unlink($destination);
                }
            } else {
                $message = "Gagal mengunggah file. Cek izin folder server.";
                $message_type = 'error';
            }
        }

    } elseif (isset($_POST['upload_foto'])) {
           $message = "Mohon pilih file foto untuk diunggah.";
           $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil Pengantar</title>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* Style Umum */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; color: #5b46b6; }
        .alert-success { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .alert-error { padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 15px; }

        /* Style Profil Display */
        .profile-display { text-align: center; margin-bottom: 30px; }
        .current-photo { 
            width: 120px; 
            height: 120px; 
            border-radius: 50%; 
            object-fit: cover; 
            margin-bottom: 10px; 
            border: 4px solid #5b46b6; 
        }
        .profile-display h3 { margin: 5px 0 0; }
        .profile-display p { font-size: 0.9em; color: #888; margin-top: 5px; }
        
        /* Style Form */
        .form-section { border: 1px solid #eee; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .form-section h3 { color: #5b46b6; margin-top: 0; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.95em; }
        
        /* Style Input */
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }

        /* Style File Input (Foto) */
        input[type="file"] { display: block; margin-top: 5px; }

        /* Style Button */
        .btn-submit {
            padding: 10px 20px; 
            background: #5b46b6; 
            color: white; 
            border: none;
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            width: auto;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-submit:hover { background: #4a3899; }
        
        .id-readonly {
            background-color: #eee;
            cursor: default;
            color: #555;
        }

    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="color: #5b46b6; text-decoration: none;">&larr; Kembali ke Dashboard</a>
    <h2>Pengaturan Profil</h2>

    <?php if (!empty($message)): ?>
        <div class="alert-<?= $message_type; ?>"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="profile-display">
        <img src="<?= htmlspecialchars($photo_path); ?>" alt="Foto Profil Saat Ini" class="current-photo">
        <h3><?= htmlspecialchars($nama_lengkap); ?></h3>
        <p>ID Kurir: #<?= htmlspecialchars($pengantar_id); ?></p>
    </div>
    
        <hr style="margin: 30px 0;">

    <div class="form-section">
        <h3>📸 Ganti Foto Profil</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="foto_baru">Pilih Foto Baru (JPG/PNG, Max 2MB):</label>
                <input type="file" name="foto_baru" id="foto_baru" accept=".jpg, .jpeg, .png">
            </div>
            
            <button type="submit" name="upload_foto" class="btn-submit">Ubah Foto Profil</button>
        </form>
    </div>
    <div class="form-section">
        <h3>✏️ Edit Informasi Personal</h3>
        <form method="POST">
            <div class="form-group">
                <label for="nama_baru">Nama Lengkap</label>
                <input type="text" name="nama_baru" id="nama_baru" value="<?= htmlspecialchars($nama_lengkap); ?>" required>
            </div>
            
            <button type="submit" name="update_nama" class="btn-submit">Simpan Perubahan Nama</button>
        </form>
    </div>
</div>
</body>
</html>