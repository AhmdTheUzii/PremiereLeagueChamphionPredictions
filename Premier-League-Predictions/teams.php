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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_team') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $deleteError = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } else {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId > 0) {
            try {
                // Verify record exists before deleting
                $checkStmt = $pdo->prepare("SELECT team_id, name FROM team WHERE team_id = ?");
                $checkStmt->execute([$teamId]);
                $teamData = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$teamData) {
                    $deleteError = 'Tim tidak ditemukan.';
                } else {
                    $teamName = $teamData['name'];
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM dataset WHERE team_id = ?")->execute([$teamId]);
                    $pdo->prepare("DELETE FROM prediction_result WHERE team_id = ?")->execute([$teamId]);
                    $pdo->prepare("UPDATE season SET champion_team_id = NULL WHERE champion_team_id = ?")->execute([$teamId]);
                    $pdo->prepare("DELETE FROM team WHERE team_id = ?")->execute([$teamId]);
                    $pdo->commit();
                    setFlash('success', "Tim $teamName berhasil dihapus.");
                    redirect('teams.php');
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $deleteError = dbErrorMessage();
                error_log('Teams delete error: ' . $e->getMessage());
            }
        } else {
            $deleteError = 'ID tim tidak valid.';
        }
    }
}

$stmt = $pdo->query("SELECT team_id, name, short_name, city, founded_year FROM team ORDER BY name ASC");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedTeamId = !empty($teams) && isset($_GET['team']) ? intval($_GET['team']) : (!empty($teams) ? $teams[0]['team_id'] : 0);

$stmt = $pdo->prepare("
    SELECT ts.*, s.year_start, s.year_end
    FROM team_season ts
    JOIN season s ON ts.season_id = s.season_id
    WHERE ts.team_id = ?
    ORDER BY s.year_start DESC
");
$stmt->execute([$selectedTeamId]);
$teamStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM team WHERE team_id = ?");
$stmt->execute([$selectedTeamId]);
$teamInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$totalSeasons = count($teamStats);
$totalPlayed = array_sum(array_column($teamStats, 'played'));
$totalWon = array_sum(array_column($teamStats, 'won'));
$totalDrawn = array_sum(array_column($teamStats, 'drawn'));
$totalLost = array_sum(array_column($teamStats, 'lost'));
$totalGoalsFor = array_sum(array_column($teamStats, 'goals_for'));
$totalGoalsAgainst = array_sum(array_column($teamStats, 'goals_against'));
$totalPoints = array_sum(array_column($teamStats, 'points'));
$championships = count(array_filter($teamStats, function($stat) {
    return $stat['is_champion'] === 'Ya';
}));

$overallWinRate = $totalPlayed > 0 ? ($totalWon / $totalPlayed) * 100 : 0;
$avgPoints = $totalSeasons > 0 ? $totalPoints / $totalSeasons : 0;

$bestSeason = null;
$maxPoints = 0;
foreach ($teamStats as $stat) {
    if ($stat['points'] > $maxPoints) {
        $maxPoints = $stat['points'];
        $bestSeason = $stat;
    }
}

$championshipSeasons = array_filter($teamStats, function($stat) {
    return $stat['is_champion'] === 'Ya';
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik Tim - Premier League Predictions</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">Premier League Predictions</a>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="standings.php">Klasemen</a>
                <a href="teams.php" class="active">Statistik Tim</a>
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
            <h2>Statistik Tim</h2>
            <p>Lihat statistik performa tim sepanjang sejarah Premier League</p>
        </div>

        <?php if ($deleteError): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>

        <?php if ($deleteSuccess): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($deleteSuccess); ?></div>
        <?php endif; ?>

        <div class="card-elevated">
            <form method="GET" style="display:inline-block;vertical-align:middle">
                <div class="form-group" style="margin-bottom:0">
                    <label for="team">Pilih Tim</label>
                    <select id="team" name="team" class="form-control" style="max-width:300px" onchange="this.form.submit()">
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['team_id']; ?>"
                                    <?php echo $team['team_id'] == $selectedTeamId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($teamInfo): ?>
        <div class="team-header">
            <div>
                <h3 style="margin-bottom:5px"><?php echo htmlspecialchars($teamInfo['name']); ?></h3>
                <div class="team-info">
                    <?php if ($teamInfo['city']): ?>
                        &#x1F4CD; <?php echo htmlspecialchars($teamInfo['city']); ?>
                    <?php endif; ?>
                    <?php if ($teamInfo['founded_year']): ?>
                        | &#x1F4C5; Didirikan: <?php echo $teamInfo['founded_year']; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="btn-group">
                <a href="export.php?report=team_stats&format=pdf&team=<?php echo $selectedTeamId; ?>" target="_blank" class="btn-export">Unduh PDF</a>
                <a href="export.php?report=team_stats&format=csv&team=<?php echo $selectedTeamId; ?>" class="btn-export">Unduh CSV</a>
                <form method="POST" style="display:inline" onsubmit="return confirmDeleteTeam('<?php echo htmlspecialchars($teamInfo['name'] ?? '', ENT_QUOTES); ?>')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_team">
                    <input type="hidden" name="team_id" value="<?php echo $selectedTeamId; ?>">
                    <button type="submit" class="btn-export" style="background:#dc3545;color:#fff" onclick="this.style.pointerEvents='none';this.style.opacity=0.6">Hapus Tim</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card featured">
                <h4>&#x1F3C6; Juara</h4>
                <div class="value"><?php echo count($championshipSeasons); ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Musim</h4>
                <div class="value"><?php echo $totalSeasons; ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Kemenangan</h4>
                <div class="value"><?php echo number_format($totalWon); ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Poin</h4>
                <div class="value"><?php echo number_format($totalPoints); ?></div>
            </div>
            <div class="stat-card">
                <h4>Win Rate</h4>
                <div class="value"><?php echo number_format($overallWinRate, 1); ?>%</div>
            </div>
            <div class="stat-card">
                <h4>Rata-rata Poin/Musim</h4>
                <div class="value"><?php echo number_format($avgPoints, 1); ?></div>
            </div>
        </div>

        <?php if ($championshipSeasons): ?>
            <div class="card">
                <h3>Musim Juara</h3>
                <ul class="championship-list">
                    <?php foreach ($championshipSeasons as $season): ?>
                        <li>
                            <span class="trophy">&#x1F3C6;</span>
                            <span><?php echo $season['year_start']; ?>/<?php echo $season['year_end']; ?> - Posisi <?php echo $season['position']; ?> (<?php echo $season['points']; ?> poin)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($bestSeason): ?>
            <div class="card">
                <h3>Musim Terbaik</h3>
                <p class="text-muted mb-24">
                    Musim <?php echo $bestSeason['year_start']; ?>/<?php echo $bestSeason['year_end']; ?>
                    dengan <?php echo $bestSeason['points']; ?> poin (Posisi <?php echo $bestSeason['position']; ?>)
                </p>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h4>Kemenangan</h4>
                        <div class="value"><?php echo $bestSeason['won']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h4>Gol Dicetak</h4>
                        <div class="value"><?php echo $bestSeason['goals_for']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h4>Selisih Gol</h4>
                        <div class="value"><?php echo $bestSeason['goal_difference']; ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Riwayat Per Musim</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Musim</th>
                            <th>Pos</th>
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
                        <?php foreach ($teamStats as $stat): ?>
                            <tr class="<?php echo $stat['is_champion'] === 'Ya' ? 'champions-league' : ''; ?>">
                                <td>
                                    <?php echo $stat['year_start']; ?>/<?php echo $stat['year_end']; ?>
                                    <?php if ($stat['is_champion'] === 'Ya'): ?>
                                        &#x1F3C6;
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $stat['position']; ?></td>
                                <td><?php echo $stat['played']; ?></td>
                                <td><?php echo $stat['won']; ?></td>
                                <td><?php echo $stat['drawn']; ?></td>
                                <td><?php echo $stat['lost']; ?></td>
                                <td><?php echo $stat['goals_for']; ?></td>
                                <td><?php echo $stat['goals_against']; ?></td>
                                <td><?php echo $stat['goal_difference']; ?></td>
                                <td><strong><?php echo $stat['points']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
function confirmDeleteTeam(teamName) {
    return confirm('Yakin ingin menghapus tim ' + teamName + ' beserta semua data terkait? Tindakan ini tidak dapat dibatalkan.');
}
</script>

</body>
</html>
