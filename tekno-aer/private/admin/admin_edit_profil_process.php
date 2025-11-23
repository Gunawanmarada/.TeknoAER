<?php
session_start();
// PERBAIKAN PATH: Sesuaikan path ke file db.php yang benar
include '../../config/db.php'; 

// Cek jika koneksi gagal (fatal error jika $conn tidak ada)
if (!isset($conn) || $conn->connect_error) {
    $_SESSION['status_message'] = "❌ Error koneksi database.";
    header("Location: admin_edit_profil.php");
    exit;
}

// Pastikan hanya admin yang login yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil data dari form (Tidak perlu real_escape_string jika pakai prepared statement)
    $nama_lengkap = $_POST['nama_lengkap'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password_baru = $_POST['password_baru'];
    
    $foto_profil_filename = null;
    $status_message = ''; // Inisialisasi pesan status
    
    // --- Bagian Pengolahan Foto Profil ---
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $file = $_FILES['foto_profil'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
        $upload_dir = '../assets/uploads/'; // Folder untuk menyimpan gambar

        // Validasi ekstensi
        if (in_array($file_ext, $allowed_ext)) {
            // Generate nama file unik
            $foto_profil_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $foto_profil_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Hapus foto lama jika ada (Menggunakan prepared statement untuk SELECT juga lebih baik)
                $old_photo_query = $conn->query("SELECT foto_profil FROM admin WHERE admin_id = $admin_id")->fetch_assoc();
                if ($old_photo_query && !empty($old_photo_query['foto_profil']) && file_exists($upload_dir . $old_photo_query['foto_profil'])) {
                    unlink($upload_dir . $old_photo_query['foto_profil']);
                }
            } else {
                $status_message = "Error: Gagal mengunggah foto profil.";
            }
        } else {
            $status_message = "Error: Ekstensi foto tidak diizinkan. Gunakan JPG, JPEG, PNG, atau GIF.";
        }
        
        // Jika ada error upload, segera kembali
        if (!empty($status_message)) {
            $_SESSION['status_message'] = "❌ " . $status_message;
            header("Location: admin_edit_profil.php");
            exit;
        }
    }
    // --- Akhir Pengolahan Foto Profil ---
    
    // --- Prepared Statement untuk UPDATE ---
    $set_clauses = ["nama_lengkap = ?", "username = ?", "email = ?"];
    $param_types = "sss";
    $param_values = [$nama_lengkap, $username, $email];

    // Handle Password Baru
    if (!empty($password_baru)) {
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
        $set_clauses[] = "password = ?";
        $param_types .= "s";
        $param_values[] = $hashed_password;
    }

    // Handle Foto Profil
    if ($foto_profil_filename) {
        $set_clauses[] = "foto_profil = ?";
        $param_types .= "s";
        $param_values[] = $foto_profil_filename;
    }
    
    // Gabungkan klausa UPDATE
    $sql = "UPDATE admin SET " . implode(", ", $set_clauses) . " WHERE admin_id = ?";
    $param_types .= "i";
    $param_values[] = $admin_id;

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Bind parameters menggunakan call_user_func_array
        $stmt->bind_param($param_types, ...$param_values);
        
        if ($stmt->execute()) {
            // Update session jika nama berubah
            $_SESSION['nama'] = $nama_lengkap; 
            $_SESSION['status_message'] = "✅ Profil berhasil diperbarui!";
        } else {
            $_SESSION['status_message'] = "❌ Error saat memperbarui profil: " . $stmt->error;
        }
        $stmt->close();
    } else {
         $_SESSION['status_message'] = "❌ Error Prepared Statement: " . $conn->error;
    }

    // Arahkan kembali ke halaman edit profil
    header("Location: admin_edit_profil.php");
    exit;

} else {
    // Jika diakses tanpa metode POST
    header("Location: admin_edit_profil.php");
    exit;
}
?>