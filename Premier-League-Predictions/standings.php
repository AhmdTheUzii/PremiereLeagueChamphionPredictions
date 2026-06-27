<?php
require_once 'config/config.php';

requireAdminLogin();

$admin = getCurrentAdmin();
$pdo = getDBConnection();

$deleteError = '';
$deleteSuccess = '';

// Check for flash messages from redirect
$flash = getFlash();
if ($flash['type'] === 'error') $deleteError = $flash['message'];
if ($flash['type'] === 'success') $deleteSuccess = $flash['message'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_season') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $deleteError = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } else {
        $seasonId = (int) ($_POST['season_id'] ?? 0);
        $seasonLabel = '';
        if ($seasonId > 0) {
            try {
                // Verify record exists before deleting
                $checkStmt = $pdo->prepare("SELECT season_id, year_start, year_end FROM season WHERE season_id = ?");
                $checkStmt->execute([$seasonId]);
                $seasonData = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$seasonData) {
                    $deleteError = 'Season tidak ditemukan.';
                } else {
                    $seasonLabel = $seasonData['year_start'] . '/' . $seasonData['year_end'];
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM dataset WHERE season_id = ?")->execute([$seasonId]);
                    $pdo->prepare("DELETE FROM prediction_result WHERE season_id = ?")->execute([$seasonId]);
                    $pdo->prepare("DELETE FROM season WHERE season_id = ?")->execute([$seasonId]);
                    $pdo->commit();
                    setFlash('success', "Season $seasonLabel berhasil dihapus.");
                    redirect('standings.php');
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $deleteError = dbErrorMessage();
                error_log('Standings delete error: ' . $e->getMessage());
            }
        } else {
            $deleteError = 'ID season tidak valid.';
        }
    }
}

$stmt = $pdo->query("SELECT season_id, year_start, year_end, champion_team_id FROM season ORDER BY year_start DESC");
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedSeasonId = !empty($seasons) && isset($_GET['season']) ? intval($_GET['season']) : (!empty($seasons) ? $seasons[0]['season_id'] : 0);

$stmt = $pdo->prepare("
    SELECT ts.*, t.name as team_name, t.short_name as team_short
    FROM team_season ts
    JOIN team t ON ts.team_id = t.team_id
    WHERE ts.season_id = ?
    ORDER BY ts.position ASC
");
$stmt->execute([$selectedSeasonId]);
$standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT s.*, t.name as champion_name
    FROM season s
    LEFT JOIN team t ON s.champion_team_id = t.team_id
    WHERE s.season_id = ?
");
$stmt->execute([$selectedSeasonId]);
$seasonInfo = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klasemen - Premier League Predictions</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">Premier League Predictions</a>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="standings.php" class="active">Klasemen</a>
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
            <h2>Klasemen Premier League</h2>
            <p>Lihat klasemen akhir setiap musim kompetisi Premier League</p>
        </div>

        <?php if ($deleteError): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>

        <?php if ($deleteSuccess): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($deleteSuccess); ?></div>
        <?php endif; ?>

        <div class="card-elevated" style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">

    <form method="GET">
        <div class="form-group" style="margin-bottom:0">
            <label for="season">Pilih Musim</label>
            <select id="season" name="season" class="form-control" style="max-width:300px" onchange="this.form.submit()">
                <?php foreach ($seasons as $season): ?>
                    <option value="<?php echo $season['season_id']; ?>"
                            <?php echo $season['season_id'] == $selectedSeasonId ? 'selected' : ''; ?>>
                        <?php echo $season['year_start']; ?>/<?php echo $season['year_end']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($seasonInfo): ?>
    <form method="POST" id="deleteSeasonForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="delete_season">
        <input type="hidden" name="season_id" value="<?php echo $selectedSeasonId; ?>">
        <button type="button" id="deleteSeasonBtn"
                class="btn"
                style="background:#dc3545;color:#fff;padding:8px 16px;font-size:13px"
                data-year-start="<?php echo htmlspecialchars($seasonInfo['year_start'] ?? '', ENT_QUOTES); ?>"
                data-year-end="<?php echo htmlspecialchars($seasonInfo['year_end'] ?? '', ENT_QUOTES); ?>">
            Hapus Season
        </button>
    </form>
    <?php endif; ?>

</div>

        <?php if ($seasonInfo && !empty($seasonInfo['champion_name'])): ?>
            <div class="champion-banner">
                <div class="trophy">&#x1F3C6;</div>
                <h3>Juara Musim <?php echo $seasonInfo['year_start']; ?>/<?php echo $seasonInfo['year_end']; ?></h3>
                <div class="champion-name"><?php echo htmlspecialchars($seasonInfo['champion_name']); ?></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Klasemen Akhir</h3>
                <div class="btn-group">
                    <a href="export.php?report=standings&format=pdf&season=<?php echo $selectedSeasonId; ?>" target="_blank" class="btn-export">Unduh PDF</a>
                    <a href="export.php?report=standings&format=csv&season=<?php echo $selectedSeasonId; ?>" class="btn-export">Unduh CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tim</th>
                            <th>Main</th>
                            <th>M</th>
                            <th>S</th>
                            <th>K</th>
                            <th>GM</th>
                            <th>GK</th>
                            <th>SG</th>
                            <th>Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $position = 1;
                        foreach ($standings as $team):
                            $rowClass = '';
                            if ($position <= 4) {
                                $rowClass = 'champions-league';
                            } elseif ($position <= 6) {
                                $rowClass = 'europa-league';
                            } elseif ($position >= count($standings) - 2) {
                                $rowClass = 'relegation';
                            }
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><strong><?php echo $position; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($team['team_name']); ?>
                                    <?php if ($team['is_champion'] === 'Ya'): ?>
                                        &#x1F3C6;
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $team['played']; ?></td>
                                <td><?php echo $team['won']; ?></td>
                                <td><?php echo $team['drawn']; ?></td>
                                <td><?php echo $team['lost']; ?></td>
                                <td><?php echo $team['goals_for']; ?></td>
                                <td><?php echo $team['goals_against']; ?></td>
                                <td><?php echo $team['goal_difference']; ?></td>
                                <td><strong><?php echo $team['points']; ?></strong></td>
                            </tr>
                        <?php
                            $position++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>

           <div class="stats-grid mt-36">
    <div class="stat-card">
        <h4>Total Tim</h4>
        <div class="value"><?php echo count($standings); ?></div>
    </div>
    <div class="stat-card">
        <h4>Total Pertandingan</h4>
        <div class="value"><?php echo isset($standings[0]) ? $standings[0]['played'] : '-'; ?></div>
    </div>
    <div class="stat-card">
        <h4>Total Gol</h4>
        <div class="value">
            <?php
            $totalGoals = !empty($standings) ? array_sum(array_column($standings, 'goals_for')) : 0;
            echo number_format($totalGoals);
            ?>
        </div>
    </div>
    <div class="stat-card">
        <h4>Poin Tertinggi</h4>
        <div class="value"><?php echo !empty($standings) ? max(array_column($standings, 'points')) : '-'; ?></div>
    </div>
</div>
        </div>
    </div>

<script>
document.getElementById('deleteSeasonBtn')?.addEventListener('click', function () {
    const yearStart = this.dataset.yearStart;
    const yearEnd   = this.dataset.yearEnd;

    if (!confirm('Yakin ingin menghapus season ' + yearStart + '/' + yearEnd + ' beserta semua data terkait? Tindakan ini tidak dapat dibatalkan.')) return;

    this.disabled = true;
    this.textContent = 'Menghapus...';
    document.getElementById('deleteSeasonForm').submit();
});
</script>

</body>
</html>
