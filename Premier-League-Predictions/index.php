<?php
require_once 'config/config.php';

requireAdminLogin();

$admin = getCurrentAdmin();
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT MIN(year_start) AS min_year, MAX(year_end) AS max_year FROM season");
$seasonRange = $stmt->fetch();
$dataRange = $seasonRange ? $seasonRange['min_year'] . '-' . $seasonRange['max_year'] : '-' ;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Premier League Predictions</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">Premier League Predictions</a>
            <div class="nav-links">
                <a href="index.php" class="active">Dashboard</a>
                <a href="standings.php">Klasemen</a>
                <a href="teams.php">Statistik Tim</a>
                <a href="predict.php">Prediksi</a>
                <a href="evaluate.php">Evaluasi Model</a>
                <a href="datasets.php">Dataset</a>
                <span class="nav-user"><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Selamat Datang, <?php echo htmlspecialchars($admin['full_name'] ?: $admin['username']); ?>!</h2>
            <p>Sistem Prediksi Premier League menggunakan Naive Bayes. Gunakan menu di bawah untuk mengelola data dan model prediksi.</p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Database</h3>
                <p>Status koneksi database</p>
                <div class="value">Aktif</div>
            </div>
            <div class="info-card">
                <h3>Model</h3>
                <p>Algoritma prediksi</p>
                <div class="value">Naive Bayes</div>
            </div>
            <div class="info-card">
                <h3>Data</h3>
                <p>Season Premier League</p>
                <div class="value"><?php echo htmlspecialchars($dataRange); ?></div>
            </div>
        </div>
    </div>
</body>
</html>
