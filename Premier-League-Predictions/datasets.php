<?php
require_once 'config/config.php';
require_once 'includes/epl_importer.php';

requireAdminLogin();

$admin = getCurrentAdmin();

$error = '';
$success = '';
$importResult = null;
$currentYear = (int) date('Y');
$defaultFrom = isset($_POST['from_year']) ? (int) $_POST['from_year'] : max(2025, $currentYear - 1);
$defaultTo = isset($_POST['to_year']) ? (int) $_POST['to_year'] : $defaultFrom;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $error = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_seasons') {
        try {
            $fromYear = (int) ($_POST['from_year'] ?? 0);
            $toYear = (int) ($_POST['to_year'] ?? $fromYear);

            if ($fromYear < 1992 || $toYear < 1992) {
                throw new Exception('Tahun season tidak valid');
            }

            if ($fromYear > $toYear) {
                throw new Exception('Season awal tidak boleh lebih besar dari season akhir');
            }

            $pdo = getDBConnection();
            $pdo->beginTransaction();

            for ($y = $fromYear; $y <= $toYear; $y++) {
                $stmt = $pdo->prepare("SELECT season_id FROM season WHERE year_start = ?");
                $stmt->execute([$y]);
                $seasonIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($seasonIds as $sid) {
                    $pdo->prepare("DELETE FROM dataset WHERE season_id = ?")->execute([$sid]);
                    $pdo->prepare("DELETE FROM prediction_result WHERE season_id = ?")->execute([$sid]);
                    $pdo->prepare("DELETE FROM season WHERE season_id = ?")->execute([$sid]);
                }
            }

            $pdo->commit();
            $success = "Data season $fromYear/" . ($fromYear + 1) . " sampai $toYear/" . ($toYear + 1) . " berhasil dihapus.";
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('Datasets bulk delete DB error: ' . $e->getMessage());
            $error = dbErrorMessage();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_season_single') {
        try {
            $seasonId = (int) ($_POST['season_id'] ?? 0);
            if ($seasonId <= 0) throw new Exception('ID season tidak valid');

            $pdo = getDBConnection();

            // Verify record exists before deleting
            $checkStmt = $pdo->prepare("SELECT season_id, year_start, year_end FROM season WHERE season_id = ?");
            $checkStmt->execute([$seasonId]);
            $seasonData = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$seasonData) {
                throw new Exception('Season tidak ditemukan.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM dataset WHERE season_id = ?")->execute([$seasonId]);
            $pdo->prepare("DELETE FROM prediction_result WHERE season_id = ?")->execute([$seasonId]);
            $pdo->prepare("DELETE FROM season WHERE season_id = ?")->execute([$seasonId]);
            $pdo->commit();
            $success = 'Season ' . $seasonData['year_start'] . '/' . $seasonData['year_end'] . ' berhasil dihapus.';
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('Datasets single delete DB error: ' . $e->getMessage());
            $error = dbErrorMessage();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    } else {
        try {
            $fromYear = (int) ($_POST['from_year'] ?? 0);
            $toYear = (int) ($_POST['to_year'] ?? $fromYear);
            $mode = $_POST['mode'] ?? 'replace';

            if ($fromYear < 1992 || $toYear < 1992) {
                throw new Exception('Tahun season tidak valid');
            }

            if ($fromYear > $toYear) {
                throw new Exception('Season awal tidak boleh lebih besar dari season akhir');
            }

            $pdo = getDBConnection();
            $importer = new EplDataImporter($pdo);
            $importResult = $importer->importFootballDataRange($fromYear, $toYear, $mode === 'replace');
            $success = 'Dataset EPL berhasil diperbarui.';
        } catch (PDOException $e) {
            error_log('Datasets import DB error: ' . $e->getMessage());
            $error = dbErrorMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$pdo = getDBConnection();
$existingSeasons = $pdo->query("SELECT season_id, year_start, year_end, total_teams FROM season ORDER BY year_start DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Dataset - Premier League Predictions</title>
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
                <a href="evaluate.php">Evaluasi Model</a>
                <a href="datasets.php" class="active">Dataset</a>
                <span class="nav-user"><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Update Dataset EPL</h2>
            <p>Ambil data pertandingan terbaru dari Football-Data, lalu sistem akan mengubahnya menjadi statistik musim untuk model Gaussian Naive Bayes.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="from_year">Season Awal</label>
                        <input type="number" id="from_year" name="from_year" class="form-control" min="1992" max="2100" value="<?php echo htmlspecialchars($defaultFrom); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="to_year">Season Akhir</label>
                        <input type="number" id="to_year" name="to_year" class="form-control" min="1992" max="2100" value="<?php echo htmlspecialchars($defaultTo); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="mode">Mode Import</label>
                        <select id="mode" name="mode" class="form-control">
                            <option value="replace">Ganti semua data lama</option>
                            <option value="append">Tambah/perbarui season saja</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn">Update Dataset</button>
                <p class="helper-text mt-12">
                    Contoh: isi 2025 sampai 2025 untuk musim 2025/2026. Untuk musim 2026/2027 nanti, isi 2026 sampai 2026 setelah data tersedia.
                </p>
            </form>
        </div>

        <?php if ($importResult): ?>
            <div class="card">
                <h3>Ringkasan Import</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>Pertandingan Selesai</th>
                                <th>Tim</th>
                                <th>Sumber</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importResult['sources'] as $source): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($source['season']); ?></td>
                                    <td><?php echo htmlspecialchars($source['completed_matches']); ?></td>
                                    <td><?php echo htmlspecialchars($source['teams']); ?></td>
                                    <td><?php echo htmlspecialchars($source['source']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Hapus Data Season</h3>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_seasons">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="del_from_year">Season Awal</label>
                        <input type="number" id="del_from_year" name="from_year" class="form-control" min="1992" max="2100" value="<?php echo $currentYear - 1; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="del_to_year">Season Akhir</label>
                        <input type="number" id="del_to_year" name="to_year" class="form-control" min="1992" max="2100" value="<?php echo $currentYear - 1; ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn" style="background:#dc3545;color:#fff" onclick="var f=this.form,y=f.from_year.value,t=f.to_year.value;if(!confirm('Yakin ingin menghapus data season '+y+'/'+(parseInt(y)+1)+' sampai '+t+'/'+(parseInt(t)+1)+'? Semua data terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.'))return false;this.style.opacity=0.5;this.style.pointerEvents='none'">Hapus Data Season</button>
            </form>
        </div>

        <?php if ($existingSeasons): ?>
            <div class="card">
                <h3>Data Season Tersimpan</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>Total Tim</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingSeasons as $s): ?>
                                <tr>
                                    <td><?php echo $s['year_start']; ?>/<?php echo $s['year_end']; ?></td>
                                    <td><?php echo $s['total_teams']; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline" onsubmit="var s='<?php echo $s['year_start']; ?>/<?php echo $s['year_end']; ?>';return confirm('Yakin ingin menghapus season '+s+' beserta semua data terkait? Tindakan ini tidak dapat dibatalkan.')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete_season_single">
                                            <input type="hidden" name="season_id" value="<?php echo $s['season_id']; ?>">
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
    </div>
</body>
</html>
