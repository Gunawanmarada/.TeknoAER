<?php
// data_keuangan.php - tahan banting: FPDF jika tersedia, fallback HTML dashboard
session_start();
// PERBAIKAN JALUR DB.PHP: Dari private/admin/ ke config/
include '../../config/db.php';

// cek admin login
if (!isset($_SESSION['admin_id'])) {
    echo "<script>alert('Silakan login sebagai admin.'); window.location='login.php';</script>";
    exit;
}

// ----------------------------------------------------
// 1. LOGIKA PENCARIAN & DATA KEUANGAN (Perbaikan Krusial)
// ----------------------------------------------------
$search_term = '';
$search_condition = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    // Pengamanan input
    $search_term_sql = $conn->real_escape_string($search_term);
    
    // Kondisi WHERE untuk mencari di NAMA PELANGGAN (u.nama_lengkap) atau NAMA BARANG (rp.nama_barang)
    $search_condition = " WHERE 
        u.nama_lengkap LIKE '%$search_term_sql%' OR 
        rp.nama_barang LIKE '%$search_term_sql%' ";
}

// PERBAIKAN: Membaca dari riwayat_pengiriman, JOIN ke user, dan GROUPING
$sql = "
    SELECT
        rp.id_kirim,
        u.nama_lengkap AS nama_pelanggan,
        -- Menggabungkan semua nama barang dalam satu transaksi menjadi satu string
        GROUP_CONCAT(rp.nama_barang SEPARATOR ', ') AS item_list,
        -- Menjumlahkan total harga dari semua item dalam satu transaksi
        SUM(rp.total) AS total_order, 
        MAX(rp.waktu_selesai) AS tanggal_transaksi -- Mengambil waktu selesai sebagai tanggal transaksi
    FROM 
        riwayat_pengiriman rp
    JOIN 
        user u ON rp.user_id = u.user_id
    {$search_condition} -- Tempatkan kondisi pencarian di sini
    GROUP BY 
        rp.id_kirim, u.user_id, u.nama_lengkap
    ORDER BY 
        tanggal_transaksi DESC
";

$q = $conn->query($sql);

$financial_report = [
    'transactions' => [],
    'total_income' => 0,
];

while ($r = $q->fetch_assoc()) {
    // Memetakan hasil query ke array report
    $financial_report['transactions'][] = [
        'nama_pelanggan' => $r['nama_pelanggan'],
        'nama_barang' => $r['item_list'], // Berisi list barang
        'total' => (int)$r['total_order'], // Total pesanan
        'tanggal' => $r['tanggal_transaksi'], // Tanggal selesai
    ];
    // Menghitung total pendapatan keseluruhan dengan menjumlahkan TOTAL ORDER (bukan total per item)
    $financial_report['total_income'] += (int)$r['total_order']; 
}

$data = $financial_report['transactions'];
$total_all = $financial_report['total_income'];


// ----------------------------------------------------
// 2. PERBAIKAN: HITUNG JUMLAH PESANAN UNIK (Untuk Sidebar) 
// ----------------------------------------------------
$admin_id = $_SESSION['admin_id'];
$admin = $conn->query("SELECT * FROM admin WHERE admin_id = $admin_id")->fetch_assoc();

// MENGHITUNG JUMLAH GRUP TRANSAKSI UNIK di pesanan_pelanggan
$q_pesanan = @$conn->query("SELECT COUNT(DISTINCT id_pesanan) AS total_pesanan FROM pesanan_pelanggan");

if ($q_pesanan && $r_pesanan = $q_pesanan->fetch_assoc()) {
    $jumlah_pesanan = $r_pesanan['total_pesanan'];
} else {
    $jumlah_pesanan = 0; 
}

$nama_admin = isset($admin['nama_lengkap']) ? $admin['nama_lengkap'] : (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin');
$role_admin = isset($admin['role']) ? $admin['role'] : 'Superuser';
$default_path = '../assets/images/default_admin.jpg'; 

// coba muat FPDF (Logika Utama Anda)
$fpdf_path = __DIR__ . '/fpdf/fpdf.php';
$use_fpdf = false;
if (is_file($fpdf_path)) {
    require_once $fpdf_path;
    if (class_exists('FPDF')) {
        $use_fpdf = true;
    }
}

if ($use_fpdf) {
    // FPDF LOGIC (Menggunakan $data dan $total_all yang sudah diperbarui)
    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,8,'LAPORAN DATA KEUANGAN - TEKNOAER',0,1,'C');
    if (!empty($search_term)) {
        $pdf->SetFont('Arial','I',10);
        $pdf->Cell(0,6,'Filter: ' . iconv('UTF-8','ISO-8859-1//TRANSLIT',$search_term),0,1,'C'); 
    }
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'Dicetak: '.date('d M Y H:i'),0,1,'C');
    $pdf->Ln(4);

    // Header tabel
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(10,8,'No',1,0,'C');
    $pdf->Cell(50,8,'Nama Pelanggan',1,0,'C');
    $pdf->Cell(70,8,'Item Pesanan',1,0,'C'); // Lebar diperbesar
    $pdf->Cell(30,8,'Total (Rp)',1,0,'C');
    $pdf->Cell(30,8,'Tanggal',1,1,'C');

    $pdf->SetFont('Arial','',10);
    $no=1;
    foreach ($data as $row) {
        $pdf->Cell(10,8,$no++,1,0,'C');
        $pdf->Cell(50,8,iconv('UTF-8','ISO-8859-1//TRANSLIT',$row['nama_pelanggan']),1,0); 
        // Menggunakan MultiCell untuk daftar item yang panjang
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell(70, 8/2, iconv('UTF-8','ISO-8859-1//TRANSLIT',$row['nama_barang']), 1, 'L');
        $new_y = $pdf->GetY();
        $pdf->SetXY($x + 70, $y); 
        $pdf->Cell(30,8,number_format($row['total'],0,',','.'),1,0,'R');
        $pdf->Cell(30,8,date('d-m-Y', strtotime($row['tanggal'])),1,1,'C');
        // Reset posisi Y setelah MultiCell
        $pdf->SetY($new_y);
    }

    // Total
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(130,8,'TOTAL PENDAPATAN',1,0,'R');
    $pdf->Cell(30,8,'Rp '.number_format($total_all,0,',','.'),1,1,'R');
    $pdf->Cell(30,8,'',0,1);

    $pdf->Output('I', 'Laporan_Keuangan_TeknoAER.pdf');
    exit;
}

// --- Fallback: TAMPILAN DASHBOARD LENGKAP ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Keuangan | TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../assets/logo/logo3.jpg">
    <style>
        /* CSS DARI DASHBOARD ANDA (Tidak Berubah) */
        :root {
            --primary-color: #5b46b6; 
            --secondary-color: #6c54c3; 
            --text-dark: #333;
            --bg-light: #f4f6f9;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            display: flex; 
            color: var(--text-dark);
        }
        .sidebar {
            width: 250px;
            background-color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .profile-avatar-wrapper { 
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .profile-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .nav-link {
            padding: 12px 15px;
            margin-bottom: 5px;
            color: var(--text-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: background-color 0.2s, color 0.2s;
        }

        .nav-link.active, .nav-link:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .nav-link svg {
            width: 20px; 
            height: 20px;
            margin-right: 10px; 
            stroke: var(--text-dark); 
            fill: none; 
            stroke-width: 3; 
        }

        .nav-link.active svg, .nav-link:hover svg {
            stroke: white; 
        }

        /* ------------------ Main Content Style ------------------ */
        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        /* Top Bar - Disesuaikan untuk Search dan Logout */
        .top-bar {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            background-color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* Style Form Pencarian BARU */
        .search-form { 
            display: flex; 
            align-items: center;
            gap: 5px; 
        }
        .search-form input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9em;
            width: 250px;
            transition: border-color 0.2s;
        }
        .search-form input[type="text"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .search-form button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.9em;
            display: flex;
            align-items: center;
        }
        .search-form button:hover {
            background-color: var(--secondary-color);
        }
        .search-form button svg {
            width: 16px;
            height: 16px;
            stroke: white;
            fill: none;
            stroke-width: 3;
        }


        .header-dashboard {
            background-color: var(--secondary-color);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .header-dashboard h2 {
            margin: 5px 0 10px 0;
            font-size: 1.8em;
        }
        
        .content-container {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .btn-action {
            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9em;
            transition: opacity 0.2s;
            color: white !important; 
            flex-shrink: 0; 
            display: flex; 
            align-items: center;
        }
        .btn-action:hover { opacity: 0.9; }

        /* Stylings for button SVG */
        .btn-action svg {
             width: 16px; 
             height: 16px;
             vertical-align: middle;
             margin-right: 5px; 
             stroke: white;
             fill: none;
             stroke-width: 3; 
        }
        
        /* TOMBOL Cetak */
        .btn-primary { 
            background: var(--primary-color); 
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.2s;
            margin-bottom: 15px; 
        }
        .btn-primary:hover { 
            background: var(--secondary-color); 
        }

        /* ... CSS Tabel (Tidak diubah) ... */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        table th, table td {
            border: none;
            border-bottom: 1px solid #f0f0f0;
            padding: 15px 12px;
            text-align: center;
        }

        table th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
            border-bottom: none; 
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background-color: #fcfcfc;
        }

        .tfoot td { 
            font-weight: bold; 
            padding: 15px 12px; 
            font-size: 1.1em;
            background-color: #f7f7f7;
            border-top: 2px solid #ddd;
        }
        .tfoot .label-total { text-align: right !important; }
        .tfoot .value-total { text-align: center !important; }
        
        .content-container h3 svg {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
            stroke: var(--text-dark);
            fill: none;
            stroke-width: 3;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo-section" style="cursor: default;">
        <div class="profile-avatar-wrapper">
            <img src="../assets/logo/logo.jpg" alt="Logo Toko"> 
        </div>
        <div>
            <strong><?= htmlspecialchars($nama_admin); ?></strong>
            <div style="font-size: 0.8em; color: #888;">Role: <?= htmlspecialchars($role_admin); ?></div>
        </div>
        </div>
    
    <a href="dashboard.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Dashboard
    </a>
    <a href="pesanan_pelanggan.php" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package">
            <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
            <path d="M2.5 14.4V9.4l9-5 9 5v5l-9 5z"></path>
            <polyline points="2.5 7.6 12 13 21.5 7.6"></polyline>
            <polyline points="12 22.4 12 13"></polyline>
        </svg>
        Pesanan Masuk (<?= $jumlah_pesanan; ?>)
    </a>
    <a href="data_keuangan.php" class="nav-link active">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        Data Keuangan
    </a>
</div>

<div class="main-content">
    
    <div class="top-bar">
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
            <input type="text" name="search" placeholder="Cari Pelanggan/Barang..." value="<?= htmlspecialchars($search_term); ?>">
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
            <?php if (!empty($search_term)): ?>
                <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn-action" style="background: #dc3545; padding: 8px 10px; font-size: 0.8em;">Clear</a>
            <?php endif; ?>
        </form>
        
        <a href="logout.php" class="btn-action" style="background: var(--primary-color);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
    
    <div class="header-dashboard">
        <p class="section-title" style="margin-top: 0; font-size: 0.9em;">Data Keuangan</p>
        <h2>Daftar Transaksi (Selesai)</h2> 
        <p style="font-size: 0.9em;">Rincian Total Penjualan yang telah berhasil diantar.</p>
    </div>
    
    <div class="content-container">
        
        <h3>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            Data Keuangan (Dicetak: <?= date('d M Y, H:i'); ?>)
        </h3><br>
            <a href="?print=pdf<?= !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Cetak Laporan
            </a>


        <table>
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th style="width:25%;">Nama Pelanggan</th>
                    <th style="width:40%;">Item Pesanan (Total Pesanan)</th>
                    <th style="width:15%;">Total (Rp)</th>
                    <th style="width:15%;">Tanggal Transaksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data) === 0): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 20px;">**Tidak ada data keuangan** yang tercatat.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach ($data as $row): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                            <td style="text-align: left; font-size: 0.9em;"><?= nl2br(htmlspecialchars($row['nama_barang'])); ?></td>
                            <td><?= number_format($row['total'],0,',','.'); ?></td>
                            <td><?= date('d M Y, H:i', strtotime($row['tanggal'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="tfoot">
                    <td colspan="3" class="label-total">Total Pendapatan (Halaman Ini) :</td>
                    <td colspan="2" class="value-total">
                        **Rp <?= number_format($total_all,0,',','.'); ?>**
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

</body>
</html>