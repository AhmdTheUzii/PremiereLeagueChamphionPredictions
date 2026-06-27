<?php
require_once 'config/config.php';
require_once 'includes/naive_bayes.php';

requireAdminLogin();

$admin = getCurrentAdmin();
$error = '';
$success = '';
$predictionResult = null;
$csvPredictions = null;
$inputMode = isset($_POST['input_mode']) ? $_POST['input_mode'] : 'manual';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_csrf_token']) || !validateCsrfToken($_POST['_csrf_token'])) {
        $error = 'Sesi tidak valid. Silakan reload halaman dan coba lagi.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'clear_predictions') {
        try {
            $pdo = getDBConnection();
            $pdo->exec("TRUNCATE TABLE prediction_result");
            $success = 'Semua hasil prediksi berhasil dihapus.';
        } catch (PDOException $e) {
            error_log('Predict clear predictions DB error: ' . $e->getMessage());
            $error = dbErrorMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
    try {
        $pdo = getDBConnection();

        $csvPredictions = null;
        $parsedCsvRows = null;

        if ($inputMode === 'csv') {
            if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas upload PHP.',
                    UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimum (2MB).',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan.',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
                ];
                throw new Exception($errorMessages[$uploadError] ?? 'Gagal mengupload file.');
            }

            $fileName = $_FILES['csv_file']['name'];
            $fileSize = $_FILES['csv_file']['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExt !== 'csv') {
                throw new Exception('Hanya file CSV yang diperbolehkan.');
            }

            if ($fileSize > 2097152) {
                throw new Exception('Ukuran file maksimal 2MB.');
            }

            $csvFile = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($csvFile, 'r');

            if ($handle === false) {
                throw new Exception('Tidak dapat membaca file CSV');
            }

            $header = fgetcsv($handle);
            $requiredColumns = ['season', 'team', 'position', 'played', 'won', 'drawn', 'lost', 'gf', 'ga', 'gd', 'points', 'Juara'];

            if ($header === false) {
                fclose($handle);
                throw new Exception('File CSV kosong atau header tidak ditemukan');
            }

            $header = array_map('trim', $header);
            $missingColumns = array_diff($requiredColumns, $header);

            if (!empty($missingColumns)) {
                fclose($handle);
                throw new Exception('Format CSV tidak sesuai. Kolom yang kurang: ' . implode(', ', $missingColumns));
            }

            $parsedCsvRows = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($header)) {
                    fclose($handle);
                    throw new Exception("Jumlah kolom tidak sesuai pada baris $rowNumber");
                }

                $data = array_combine($header, $row);

                foreach ($requiredColumns as $column) {
                    if (!isset($data[$column]) || trim($data[$column]) === '') {
                        fclose($handle);
                        throw new Exception("Data kolom $column kosong pada baris $rowNumber");
                    }
                }

                $numericColumns = ['season', 'position', 'played', 'won', 'drawn', 'lost', 'gf', 'ga', 'gd', 'points'];
                foreach ($numericColumns as $column) {
                    if (!is_numeric($data[$column])) {
                        fclose($handle);
                        throw new Exception("Kolom $column harus numerik pada baris $rowNumber");
                    }
                }

                $parsedCsvRows[] = $data;
            }

            fclose($handle);

            importCsvToDatabase($pdo, $parsedCsvRows);
        }

        $classifier = new NaiveBayesClassifier($pdo);

        $datasetInfo = $classifier->prepareDataset(0.8);

        $trainingStart = $datasetInfo['training_season_start'];
        $trainingEnd = $datasetInfo['training_season_end'];

        $trainResult = $classifier->train($trainingStart, $trainingEnd);

        if ($inputMode === 'manual') {
            $inputData = [
                'won' => intval($_POST['won']),
                'drawn' => intval($_POST['drawn']),
                'lost' => intval($_POST['lost']),
                'goals_for' => intval($_POST['goals_for']),
                'goals_against' => intval($_POST['goals_against']),
                'goal_diff' => intval($_POST['goal_diff']),
                'points' => intval($_POST['points']),
                'win_rate' => floatval($_POST['win_rate'])
            ];

            $predictionResult = $classifier->predict($inputData);
            $_SESSION['last_manual_predict'] = [
                'input' => $inputData,
                'result' => $predictionResult
            ];
            $success = 'Prediksi berhasil dilakukan!';
        } elseif ($inputMode === 'csv') {
            $csvPredictions = [];

            foreach ($parsedCsvRows as $data) {
                $played = intval($data['played']);
                $won = intval($data['won']);
                $winRate = $played > 0 ? $won / $played : 0;

                $inputData = [
                    'won' => $won,
                    'drawn' => intval($data['drawn']),
                    'lost' => intval($data['lost']),
                    'goals_for' => intval($data['gf']),
                    'goals_against' => intval($data['ga']),
                    'goal_diff' => intval($data['gd']),
                    'points' => intval($data['points']),
                    'win_rate' => $winRate
                ];

                $result = $classifier->predict($inputData);

                $csvPredictions[] = [
                    'team_name' => $data['team'],
                    'season' => $data['season'],
                    'position' => $data['position'],
                    'predicted_class' => $result['predicted_class'],
                    'prob_juara' => $result['probabilities']['Juara'],
                    'prob_not_juara' => $result['probabilities']['Tidak Juara']
                ];
            }

            $_SESSION['last_csv_predict'] = $csvPredictions;
            $success = 'Prediksi dari CSV berhasil dilakukan! Total ' . count($csvPredictions) . ' tim diprediksi.';
        }
    } catch (PDOException $e) {
        error_log('Predict DB error: ' . $e->getMessage());
        $error = dbErrorMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    }
}

/**
 * Import CSV data into database tables for Naive Bayes training.
 * Truncates existing prediction-related tables and repopulates from the uploaded CSV.
 */
function importCsvToDatabase($pdo, $rows) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE team_season");
    $pdo->exec("TRUNCATE TABLE dataset");
    $pdo->exec("TRUNCATE TABLE prediction_result");
    $pdo->exec("TRUNCATE TABLE model_performance");
    $pdo->exec("TRUNCATE TABLE team");
    $pdo->exec("TRUNCATE TABLE season");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->beginTransaction();

    $seasonStmt = $pdo->prepare("INSERT INTO season (year_start, year_end, total_teams) VALUES (?, ?, ?)");
    $teamStmt = $pdo->prepare("INSERT INTO team (name, full_name, short_name) VALUES (?, ?, ?)");
    $teamSeasonStmt = $pdo->prepare("INSERT INTO team_season (season_id, team_id, position, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, is_champion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $seasonMap = [];
    $teamMap = [];

    foreach ($rows as $data) {
        $seasonYear = (int) $data['season'];
        $teamName = trim($data['team']);
        $normalizedTeamName = normalizeTeamNameForImport($teamName);

        if (!isset($seasonMap[$seasonYear])) {
            $yearStart = $seasonYear;
            $yearEnd = $seasonYear + 1;
            $seasonStmt->execute([$yearStart, $yearEnd, 0]);
            $seasonMap[$seasonYear] = (int) $pdo->lastInsertId();
        }

        if (!isset($teamMap[$normalizedTeamName])) {
            $shortName = extractShortNameForImport($normalizedTeamName);
            $teamStmt->execute([$normalizedTeamName, $normalizedTeamName, $shortName]);
            $teamMap[$normalizedTeamName] = (int) $pdo->lastInsertId();
        }

        $seasonId = $seasonMap[$seasonYear];
        $teamId = $teamMap[$normalizedTeamName];

        $isChampion = normalizeChampionStatusForImport($data['Juara']);

        $teamSeasonStmt->execute([
            $seasonId,
            $teamId,
            (int) $data['position'],
            (int) $data['played'],
            (int) $data['won'],
            (int) $data['drawn'],
            (int) $data['lost'],
            (int) $data['gf'],
            (int) $data['ga'],
            (int) $data['gd'],
            (int) $data['points'],
            $isChampion
        ]);
    }

    // Update total_teams per season
    $seasonCounts = [];
    foreach ($rows as $data) {
        $seasonYear = (int) $data['season'];
        $seasonCounts[$seasonYear] = ($seasonCounts[$seasonYear] ?? 0) + 1;
    }
    $updateCountStmt = $pdo->prepare("UPDATE season SET total_teams = ? WHERE season_id = ?");
    foreach ($seasonCounts as $year => $count) {
        $updateCountStmt->execute([$count, $seasonMap[$year]]);
    }

    // Update champion_team_id per season
    $seasonChampions = [];
    foreach ($rows as $data) {
        $seasonYear = (int) $data['season'];
        if (normalizeChampionStatusForImport($data['Juara']) === 'Ya') {
            $normalizedTeamName = normalizeTeamNameForImport(trim($data['team']));
            $seasonChampions[$seasonYear] = $teamMap[$normalizedTeamName];
        }
    }
    $updateChampionStmt = $pdo->prepare("UPDATE season SET champion_team_id = ? WHERE season_id = ?");
    foreach ($seasonChampions as $year => $teamId) {
        $updateChampionStmt->execute([$teamId, $seasonMap[$year]]);
    }

    $pdo->commit();
}

function normalizeTeamNameForImport($name) {
    $name = trim($name);
    $mapping = [
        'Manchester Utd' => 'Manchester United',
        'Newcastle Utd' => 'Newcastle United',
        'Sheffield Weds' => 'Sheffield Wednesday',
        'Tottenham' => 'Tottenham Hotspur',
        'Charlton Ath' => 'Charlton Athletic',
        'Blackburn' => 'Blackburn Rovers',
        'Bolton' => 'Bolton Wanderers',
        'Wigan Athletic' => 'Wigan Athletic',
        'Stoke City' => 'Stoke City',
        'West Brom' => 'West Bromwich Albion',
        'Swansea City' => 'Swansea City',
        'Cardiff City' => 'Cardiff City',
        'Hull City' => 'Hull City',
        'Brighton' => 'Brighton & Hove Albion',
        'Huddersfield' => 'Huddersfield Town',
        'Wolves' => 'Wolverhampton Wanderers',
        'Bournemouth' => 'AFC Bournemouth',
        'Sheffield Utd' => 'Sheffield United',
        'Leicester City' => 'Leicester City',
        'Leeds United' => 'Leeds United',
        'West Ham' => 'West Ham United',
        'QPR' => 'Queens Park Rangers',
        'Norwich City' => 'Norwich City',
        'Ipswich Town' => 'Ipswich Town',
        'Luton Town' => 'Luton Town',
        'Brentford' => 'Brentford',
        'Crystal Palace' => 'Crystal Palace',
        'Fulham' => 'Fulham',
        'Southampton' => 'Southampton',
        'Everton' => 'Everton',
        'Aston Villa' => 'Aston Villa',
        'Chelsea' => 'Chelsea',
        'Arsenal' => 'Arsenal',
        'Liverpool' => 'Liverpool',
        'Manchester City' => 'Manchester City',
        'Sunderland' => 'Sunderland',
        'Middlesbrough' => 'Middlesbrough',
        'Derby County' => 'Derby County',
        'Nottingham Forest' => 'Nottingham Forest',
        'Coventry City' => 'Coventry City',
        'Oldham Athletic' => 'Oldham Athletic',
        'Swindon Town' => 'Swindon Town',
        'Barnsley' => 'Barnsley',
        'Bradford City' => 'Bradford City',
        'Watford' => 'Watford',
        'Portsmouth' => 'Portsmouth',
        'Reading' => 'Reading',
        'Birmingham City' => 'Birmingham City',
        'Burnley' => 'Burnley',
        'Blackpool' => 'Blackpool',
    ];
    return $mapping[$name] ?? $name;
}

function normalizeChampionStatusForImport($status) {
    $status = strtolower(trim($status));
    return ($status === 'ya' || $status === 'juara') ? 'Ya' : 'Tidak';
}

function extractShortNameForImport($fullName) {
    $shortNames = [
        'Manchester United' => 'Man Utd',
        'Manchester City' => 'Man City',
        'Tottenham Hotspur' => 'Spurs',
        'West Bromwich Albion' => 'West Brom',
        'West Ham United' => 'West Ham',
        'Brighton & Hove Albion' => 'Brighton',
        'Huddersfield Town' => 'Huddersfield',
        'Wolverhampton Wanderers' => 'Wolves',
        'AFC Bournemouth' => 'Bournemouth',
        'Sheffield Wednesday' => 'Sheffield Weds',
        'Queens Park Rangers' => 'QPR',
        'Sheffield United' => 'Sheffield Utd',
        'Newcastle United' => 'Newcastle',
        'Blackburn Rovers' => 'Blackburn',
        'Bolton Wanderers' => 'Bolton',
        'Nottingham Forest' => 'Nottingham Forest',
        'Leicester City' => 'Leicester City',
        'Leeds United' => 'Leeds United',
    ];
    return $shortNames[$fullName] ?? $fullName;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Juara - Premier League Predictions</title>
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
                <a href="predict.php" class="active">Prediksi</a>
                <a href="evaluate.php">Evaluasi Model</a>
                <a href="datasets.php">Dataset</a>
                <span class="nav-user"><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Prediksi Peluang Juara</h2>
            <p>Masukkan statistik tim untuk memprediksi peluang menjadi juara Premier League</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="input-mode-tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('manual')">Input Manual</button>
                <button type="button" class="tab-btn" onclick="switchTab('csv')">Upload CSV</button>
            </div>

            <div id="manual-tab" class="tab-content active">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="input_mode" value="manual">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="won">Jumlah Kemenangan</label>
                            <input type="number" id="won" name="won" class="form-control" min="0" max="38" required value="<?php echo isset($_POST['won']) ? htmlspecialchars($_POST['won']) : ''; ?>">
                            <span class="helper-text">Total kemenangan dalam musim</span>
                        </div>

                        <div class="form-group">
                            <label for="drawn">Jumlah Seri</label>
                            <input type="number" id="drawn" name="drawn" class="form-control" min="0" max="38" required value="<?php echo isset($_POST['drawn']) ? htmlspecialchars($_POST['drawn']) : ''; ?>">
                            <span class="helper-text">Total pertandingan seri</span>
                        </div>

                        <div class="form-group">
                            <label for="lost">Jumlah Kekalahan</label>
                            <input type="number" id="lost" name="lost" class="form-control" min="0" max="38" required value="<?php echo isset($_POST['lost']) ? htmlspecialchars($_POST['lost']) : ''; ?>">
                            <span class="helper-text">Total kekalahan dalam musim</span>
                        </div>

                        <div class="form-group">
                            <label for="goals_for">Total Gol Dicetak</label>
                            <input type="number" id="goals_for" name="goals_for" class="form-control" min="0" required value="<?php echo isset($_POST['goals_for']) ? htmlspecialchars($_POST['goals_for']) : ''; ?>">
                            <span class="helper-text">Total gol yang dicetak tim</span>
                        </div>

                        <div class="form-group">
                            <label for="goals_against">Total Gol Kemasukan</label>
                            <input type="number" id="goals_against" name="goals_against" class="form-control" min="0" required value="<?php echo isset($_POST['goals_against']) ? htmlspecialchars($_POST['goals_against']) : ''; ?>">
                            <span class="helper-text">Total gol yang kemasukan</span>
                        </div>

                        <div class="form-group">
                            <label for="goal_diff">Selisih Gol</label>
                            <input type="number" id="goal_diff" name="goal_diff" class="form-control" required value="<?php echo isset($_POST['goal_diff']) ? htmlspecialchars($_POST['goal_diff']) : ''; ?>">
                            <span class="helper-text">Selisih gol (positif/negatif)</span>
                        </div>

                        <div class="form-group">
                            <label for="points">Total Poin</label>
                            <input type="number" id="points" name="points" class="form-control" min="0" max="114" required value="<?php echo isset($_POST['points']) ? htmlspecialchars($_POST['points']) : ''; ?>">
                            <span class="helper-text">Total poin klasemen</span>
                        </div>

                        <div class="form-group">
                            <label for="win_rate">Rasio Kemenangan</label>
                            <input type="number" id="win_rate" name="win_rate" class="form-control" min="0" max="1" step="0.0001" required value="<?php echo isset($_POST['win_rate']) ? htmlspecialchars($_POST['win_rate']) : ''; ?>">
                            <span class="helper-text">Win rate (0.0 - 1.0)</span>
                        </div>
                    </div>

                    <button type="submit" class="btn">Prediksi Peluang Juara</button>
                </form>
            </div>

            <div id="csv-tab" class="tab-content">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="input_mode" value="csv">
                    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                    <div class="csv-upload-zone">
                        <h3>Upload File CSV</h3>
                        <p>Upload file CSV berisi data statistik tim untuk diprediksi (maks 2MB)</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>

                    <div class="csv-template-block">
                        <strong>Format CSV:</strong><br>
                        File CSV harus memiliki header dengan kolom berikut:<br>
                        <code>season, team, position, played, won, drawn, lost, gf, ga, gd, points, Juara</code><br><br>
                        Contoh:<br>
                        <code>2024, Manchester City, 1, 38, 28, 5, 5, 95, 30, 65, 89, Ya</code><br>
                        <code>2024, Arsenal, 2, 38, 26, 6, 6, 88, 35, 53, 84, Tidak</code>
                    </div>

                    <button type="submit" class="btn mt-24">Prediksi dari CSV</button>
                </form>
            </div>
        </div>

        <?php if ($predictionResult): ?>
            <div class="card">
                <div class="prediction-badge-container">
                    <h3>Hasil Prediksi</h3>
                    <div class="prediction-badge <?php echo $predictionResult['predicted_class'] === 'Juara' ? 'juara' : 'tidak-juara'; ?>" style="margin-top:var(--space-24)">
                        <?php echo $predictionResult['predicted_class']; ?>
                    </div>
                    <div class="btn-group" style="justify-content:center;margin-top:var(--space-24)">
                        <a href="export.php?report=predict_manual&format=pdf" target="_blank" class="btn-export">Unduh PDF</a>
                        <a href="export.php?report=predict_manual&format=csv" class="btn-export">Unduh CSV</a>
                    </div>
                </div>

                <div class="probability-bar">
                    <div class="probability-label">
                        <span>Peluang Juara</span>
                        <span><?php echo number_format($predictionResult['probabilities']['Juara'] * 100, 2); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $predictionResult['probabilities']['Juara'] * 100; ?>%"></div>
                    </div>
                </div>

                <div class="probability-bar">
                    <div class="probability-label">
                        <span>Peluang Tidak Juara</span>
                        <span><?php echo number_format($predictionResult['probabilities']['Tidak Juara'] * 100, 2); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill error" style="width: <?php echo $predictionResult['probabilities']['Tidak Juara'] * 100; ?>%"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($csvPredictions): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Hasil Prediksi CSV</h3>
                    <div class="btn-group">
                        <a href="export.php?report=predict_csv&format=pdf" target="_blank" class="btn-export">Unduh PDF</a>
                        <a href="export.php?report=predict_csv&format=csv" class="btn-export">Unduh CSV</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Musim</th>
                                <th>Tim</th>
                                <th>Posisi</th>
                                <th>Prediksi</th>
                                <th>Peluang Juara</th>
                                <th>Peluang Tidak Juara</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvPredictions as $pred): ?>
                                <tr class="<?php echo $pred['predicted_class'] === 'Juara' ? 'champions-league' : ''; ?>">
                                    <td><?php echo htmlspecialchars($pred['season']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['position']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($pred['predicted_class']); ?></strong></td>
                                    <td><?php echo number_format($pred['prob_juara'] * 100, 2); ?>%</td>
                                    <td><?php echo number_format($pred['prob_not_juara'] * 100, 2); ?>%</td>
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
            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus SEMUA hasil prediksi dari database? Tindakan ini tidak dapat dibatalkan.')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="clear_predictions">
                <button type="submit" class="btn" style="background:#dc3545;color:#fff" onclick="this.style.opacity=0.5;this.style.pointerEvents='none'">Hapus Semua Hasil Prediksi</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(el) {
                el.classList.remove('active');
            });
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>

</body>
</html>
