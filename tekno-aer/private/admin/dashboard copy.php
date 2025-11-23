<?php
session_start();
include '../db.php';

// Cegah akses tanpa login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil data admin yang sedang login
$admin_id = $_SESSION['admin_id'];
$admin = $conn->query("SELECT * FROM admin WHERE admin_id = $admin_id")->fetch_assoc();

// Ambil semua data barang
$result = $conn->query("SELECT * FROM barang ORDER BY barang_id DESC");

// Hitung jumlah pesanan
$jumlah_pesanan = $conn->query("SELECT COUNT(*) AS total FROM pesanan_pelanggan")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Admin - TeknoAER</title>
<style>
body {
  font-family: Arial, sans-serif;
  margin: 0;
  background: #f4f6f9;
}
header {
  background: linear-gradient(135deg, #007bff, #6fb1fc);
  color: white;
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
header h2 {
  margin: 0;
}
.nav-right a {
  color: white;
  text-decoration: none;
  margin-left: 20px;
  font-weight: bold;
}
.nav-right a:hover {
  text-decoration: underline;
}
.container {
  width: 90%;
  margin: 30px auto;
  background: white;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
table, th, td {
  border: 1px solid #ddd;
}
th {
  background: #007bff;
  color: white;
  text-align: left;
  padding: 10px;
}
td {
  padding: 10px;
}
a.btn {
  display: inline-block;
  padding: 6px 10px;
  background: #007bff;
  color: white;
  border-radius: 4px;
  text-decoration: none;
  font-size: 14px;
}
a.btn:hover {
  background: #0056b3;
}
a.btn-del {
  background: #dc3545;
}
a.btn-del:hover {
  background: #b02a37;
}
.profile-box {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}
.btn-success {
  background: #28a745;
}
.btn-success:hover {
  background: #1e7e34;
}
</style>
</head>
<body>

<header>
  <h2>⚙️ Dashboard Admin</h2>
  <div class="nav-right">
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="#profil">👤 Profil</a>
    <a href="logout.php">🚪 Logout</a>
  </div>
</header>

<div class="container">
  <h3>👋 Selamat datang, 👤 <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?>
 !</h3>

  <div id="profil" class="profile-box">
    <h4>Profil Admin</h4>
    <p><b>Nama Lengkap:</b> <?= htmlspecialchars($admin['nama_lengkap']); ?></p>
    <p><b>Username:</b> <?= htmlspecialchars($admin['username']); ?></p>
    <p><b>Role:</b> <?= htmlspecialchars($admin['role']); ?></p>
  </div>

  <h4>📦 Data Barang</h4>
<div style="margin-bottom: 15px;">
  <a href="barang_tambah.php" class="btn" style="background:#007bff;">+ Tambah Barang</a>
  <a href="pesanan_pelanggan.php" class="btn" style="background:#28a745;">📦 Pesanan Masuk</a>
  <a href="data_keuangan.php" class="btn" style="background:#17a2b8;">💰 Data Keuangan</a>
  <a href="cetak_keuangan.php" class="btn" style="background:#dc3545;">🖨️ Cetak Keuangan</a>
</div>


  <table>
    <tr>
      <th>ID</th>
      <th>Nama Barang</th>
      <th>Kategori</th>
      <th>Kondisi</th>
      <th>Harga</th>
      <th>Gambar</th>
      <th>Aksi</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $row['barang_id']; ?></td>
      <td><?= htmlspecialchars($row['nama_barang']); ?></td>
      <td><?= htmlspecialchars($row['kategori']); ?></td>
      <td><?= htmlspecialchars($row['kondisi']); ?></td>
      <td>Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
      <td>
        <?php if (!empty($row['gambar'])): ?>
          <img src="../assets/uploads/<?= $row['gambar']; ?>" width="60">
        <?php else: ?>
          (Tanpa Gambar)
        <?php endif; ?>
      </td>
      <td>
        <a href="barang_edit.php?id=<?= $row['barang_id']; ?>" class="btn">✏️ Edit</a>
        <a href="barang_hapus.php?id=<?= $row['barang_id']; ?>" class="btn btn-del" onclick="return confirm('Yakin hapus barang ini?');">🗑️ Hapus</a>
        <a href="review_barang.php?id=<?= $row['barang_id']; ?>" class="btn">Lihat Review</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

</body>
</html>
