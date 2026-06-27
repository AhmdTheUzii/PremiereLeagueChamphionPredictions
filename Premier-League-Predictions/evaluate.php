<?php
require_once 'config/config.php';
require_once 'includes/naive_bayes.php';

requireAdminLogin();

$admin = getCurrentAdmin();
$error = '';
$success = '';
$evaluationResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $error = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } else {
        try {
            $pdo = getDBConnection();
            $classifier = new NaiveBayesClassifier($pdo);

            $datasetInfo = $classifier->prepareDataset(0.8);

            $trainingStart = $datasetInfo['training_season_start'];
            $trainingEnd = $datasetInfo['training_season_end'];

            $trainResult = $classifier->train($trainingStart, $trainingEnd);

            $testingStart = $datasetInfo['testing_season_start'];
            $testingEnd = $datasetInfo['testing_season_end'];
            $evaluationResult = $classifier->evaluate($testingStart, $testingEnd);

            $success = 'Evaluasi model berhasil dilakukan!';

        } catch (PDOException $e) {
            error_log('Evaluate model DB error: ' . $e->getMessage());
            $error = dbErrorMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT * FROM model_performance
    ORDER BY created_at DESC
    LIMIT 10
");
$performanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deletePerfError = '';
$deletePerfSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_performance') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $deletePerfError = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } else {
        $perfId = (int) ($_POST['performance_id'] ?? 0);
        if ($perfId > 0) {
            try {
                // Verify record exists before deleting
                $checkStmt = $pdo->prepare("SELECT performance_id FROM model_performance WHERE performance_id = ?");
                $checkStmt->execute([$perfId]);
                if (!$checkStmt->fetch()) {
                    $deletePerfError = 'Riwayat evaluasi tidak ditemukan.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM model_performance WHERE performance_id = ?");
                    $stmt->execute([$perfId]);
                    $deletePerfSuccess = 'Riwayat evaluasi berhasil dihapus.';
                    $performanceHistory = $pdo->query("SELECT * FROM model_performance ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                error_log('Evaluate performance delete DB error: ' . $e->getMessage());
                $deletePerfError = dbErrorMessage();
            } catch (Exception $e) {
                $deletePerfError = $e->getMessage();
            }
        } else {
            $deletePerfError = 'ID performa tidak valid.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_predictions') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $deletePerfError = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } else {
        try {
            $pdo->exec("TRUNCATE TABLE prediction_result");
            $deletePerfSuccess = 'Semua hasil prediksi berhasil dihapus.';
        } catch (PDOException $e) {
            error_log('Evaluate clear predictions DB error: ' . $e->getMessage());
            $deletePerfError = dbErrorMessage();
        } catch (Exception $e) {
            $deletePerfError = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluasi Model - Premier League Predictions</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">Premier League Predictions</a>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="standings.php">Klasemen</a>
                <a href="teams.php">Statistik Tim</a>
                <a href="predict.php">Prediksi</a>
                <a href="evaluate.php" class="active">Evaluasi Model</a>
                <a href="datasets.php">Dataset</a>
                <span class="nav-user"><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Evaluasi Model Naive Bayes</h2>
            <p>Evaluasi performa model prediksi menggunakan metrik accuracy, precision, recall, dan F1-score</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($deletePerfError): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($deletePerfError); ?></div>
        <?php endif; ?>

        <?php if ($deletePerfSuccess): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($deletePerfSuccess); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <?php echo csrfField(); ?>
                <p class="text-muted">Klik tombol di bawah untuk melakukan evaluasi model menggunakan data testing yang tersedia. Sistem akan membagi data menjadi 80% training dan 20% testing, kemudian menghitung metrik evaluasi.</p>
                <button type="submit" class="btn">Evaluasi Model</button>
            </form>
        </div>

        <?php if ($evaluationResult): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Hasil Evaluasi Model</h3>
                    <div class="btn-group">
                        <a href="export.php?report=evaluation&format=pdf" target="_blank" class="btn-export">Unduh PDF</a>
                        <a href="export.php?report=evaluation&format=csv" class="btn-export">Unduh CSV</a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card featured">
                        <h4>Accuracy</h4>
                        <div class="value"><?php echo number_format($evaluationResult['accuracy'] * 100, 2); ?>%</div>
                    </div>
                    <div class="stat-card">
                        <h4>Precision</h4>
                        <div class="value"><?php echo number_format($evaluationResult['precision'] * 100, 2); ?>%</div>
                    </div>
                    <div class="stat-card">
                        <h4>Recall</h4>
                        <div class="value"><?php echo number_format($evaluationResult['recall'] * 100, 2); ?>%</div>
                    </div>
                    <div class="stat-card">
                        <h4>F1-Score</h4>
                        <div class="value"><?php echo number_format($evaluationResult['f1_score'] * 100, 2); ?>%</div>
                    </div>
                </div>

                <h3>Confusion Matrix</h3>
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Prediksi Juara</th>
                                <th>Prediksi Tidak Juara</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Aktual Juara</th>
                                <td class="highlight">TP: <?php echo $evaluationResult['confusion_matrix']['true_positive']; ?></td>
                                <td>FN: <?php echo $evaluationResult['confusion_matrix']['false_negative']; ?></td>
                            </tr>
                            <tr>
                                <th>Aktual Tidak Juara</th>
                                <td>FP: <?php echo $evaluationResult['confusion_matrix']['false_positive']; ?></td>
                                <td class="highlight">TN: <?php echo $evaluationResult['confusion_matrix']['true_negative']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-36">Detail Prediksi</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>Tim</th>
                                <th>Aktual</th>
                                <th>Prediksi</th>
                                <th>Peluang Juara</th>
                                <th>Peluang Tidak Juara</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluationResult['predictions'] as $pred): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pred['season']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['team']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['actual']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['predicted']); ?></td>
                                    <td><?php echo number_format($pred['prob_juara'] * 100, 2); ?>%</td>
                                    <td><?php echo number_format($pred['prob_not_juara'] * 100, 2); ?>%</td>
                                    <td class="<?php echo $pred['actual'] === $pred['predicted'] ? 'correct' : 'incorrect'; ?>">
                                        <?php echo $pred['actual'] === $pred['predicted'] ? '&#x2713; Benar' : '&#x2717; Salah'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($performanceHistory): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Riwayat Evaluasi Model</h3>
                    <div class="btn-group">
                        <a href="export.php?report=evaluation&format=pdf" target="_blank" class="btn-export">Unduh PDF</a>
                        <a href="export.php?report=evaluation&format=csv" class="btn-export">Unduh CSV</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Season Training</th>
                                <th>Season Testing</th>
                                <th>Accuracy</th>
                                <th>Precision</th>
                                <th>Recall</th>
                                <th>F1-Score</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performanceHistory as $history): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($history['created_at'])); ?></td>
                                    <td><?php echo $history['training_season_start']; ?> - <?php echo $history['training_season_end']; ?></td>
                                    <td><?php echo $history['testing_season']; ?></td>
                                    <td><?php echo number_format($history['accuracy'] * 100, 2); ?>%</td>
                                    <td><?php echo number_format($history['precision'] * 100, 2); ?>%</td>
                                    <td><?php echo number_format($history['recall'] * 100, 2); ?>%</td>
                                    <td><?php echo number_format($history['f1_score'] * 100, 2); ?>%</td>
                                    <td>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus riwayat evaluasi tanggal <?php echo date('d M Y H:i', strtotime($history['created_at'])); ?>? Tindakan ini tidak dapat dibatalkan.')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete_performance">
                                            <input type="hidden" name="performance_id" value="<?php echo $history['performance_id']; ?>">
                                            <button type="submit" class="btn-export" style="background:#dc3545;color:#fff;padding:4px 10px;font-size:12px" onclick="this.style.opacity=0.5;this.style.pointerEvents='none'">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Kelola Data</h3>
            </div>
            <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus SEMUA hasil prediksi? Data training tidak akan terhapus. Tindakan ini tidak dapat dibatalkan.')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="clear_predictions">
                <button type="submit" class="btn" style="background:#dc3545;color:#fff" onclick="this.style.opacity=0.5;this.style.pointerEvents='none'">Hapus Semua Hasil Prediksi</button>
            </form>
        </div>
    </div>

</body>
</html>
