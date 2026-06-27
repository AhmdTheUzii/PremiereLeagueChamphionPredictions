<?php
/**
 * EPL data importer.
 *
 * Supports Football-Data match CSV files such as:
 * https://www.football-data.co.uk/mmz4281/2526/E0.csv
 *
 * The importer transforms match-level rows into season-level team statistics
 * used by the Gaussian Naive Bayes model.
 */

class EplDataImporter {
    private $pdo;
    private $urlTemplate;

    public function __construct($pdo, $urlTemplate = 'https://www.football-data.co.uk/mmz4281/{season_code}/E0.csv') {
        $this->pdo = $pdo;
        $this->urlTemplate = $urlTemplate;
    }

    public function importFootballDataRange($startYear, $endYear, $replaceExisting = true) {
        if ($startYear > $endYear) {
            throw new Exception('Season awal tidak boleh lebih besar dari season akhir');
        }

        $allTeamSeasons = [];
        $sources = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $url = $this->buildSeasonUrl($year);
            $csv = $this->readCsvFromUrl($url);
            $seasonData = $this->buildSeasonTableFromFootballDataCsv($csv, $year);

            if ($seasonData['completed_matches'] === 0) {
                throw new Exception("Tidak ada pertandingan selesai untuk season $year/" . ($year + 1));
            }

            $allTeamSeasons = array_merge($allTeamSeasons, $seasonData['team_seasons']);
            $sources[] = [
                'season' => $year . '/' . ($year + 1),
                'source' => $url,
                'completed_matches' => $seasonData['completed_matches'],
                'teams' => count($seasonData['team_seasons'])
            ];
        }

        $this->saveTeamSeasons($allTeamSeasons, $replaceExisting);

        return [
            'sources' => $sources,
            'total_seasons' => count($sources),
            'total_team_seasons' => count($allTeamSeasons)
        ];
    }

    public function importFootballDataFile($csvFile, $seasonStartYear, $replaceExisting = true) {
        if (!file_exists($csvFile)) {
            throw new Exception("File CSV tidak ditemukan: $csvFile");
        }

        $csv = file_get_contents($csvFile);
        if ($csv === false) {
            throw new Exception("Tidak dapat membaca file CSV: $csvFile");
        }

        $seasonData = $this->buildSeasonTableFromFootballDataCsv($csv, $seasonStartYear);
        $this->saveTeamSeasons($seasonData['team_seasons'], $replaceExisting);

        return [
            'sources' => [[
                'season' => $seasonStartYear . '/' . ($seasonStartYear + 1),
                'source' => $csvFile,
                'completed_matches' => $seasonData['completed_matches'],
                'teams' => count($seasonData['team_seasons'])
            ]],
            'total_seasons' => 1,
            'total_team_seasons' => count($seasonData['team_seasons'])
        ];
    }

    private function buildSeasonUrl($seasonStartYear) {
        $seasonCode = substr((string) $seasonStartYear, -2) . substr((string) ($seasonStartYear + 1), -2);
        return str_replace('{season_code}', $seasonCode, $this->urlTemplate);
    }

    private function readCsvFromUrl($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "User-Agent: Premier-League-Predictions/1.0\r\n"
            ]
        ]);

        $csv = @file_get_contents($url, false, $context);
        if ($csv === false || trim($csv) === '') {
            throw new Exception("Gagal mengambil data dari $url");
        }

        return $csv;
    }

    private function buildSeasonTableFromFootballDataCsv($csv, $seasonStartYear) {
        $rows = $this->parseCsv($csv);
        if (count($rows) < 2) {
            throw new Exception("CSV season $seasonStartYear kosong atau tidak valid");
        }

        $header = array_map('trim', array_shift($rows));
        $requiredColumns = ['Date', 'HomeTeam', 'AwayTeam', 'FTHG', 'FTAG', 'FTR'];
        $missingColumns = array_diff($requiredColumns, $header);

        if (!empty($missingColumns)) {
            throw new Exception('Format CSV Football-Data tidak sesuai. Kolom yang kurang: ' . implode(', ', $missingColumns));
        }

        $stats = [];
        $completedMatches = 0;

        foreach ($rows as $rowNumber => $row) {
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }

            $data = array_combine($header, array_slice($row, 0, count($header)));
            if ($data === false) {
                throw new Exception('Gagal membaca CSV pada baris ' . ($rowNumber + 2));
            }

            $homeTeam = $this->normalizeTeamName($data['HomeTeam']);
            $awayTeam = $this->normalizeTeamName($data['AwayTeam']);
            $homeGoals = trim($data['FTHG']);
            $awayGoals = trim($data['FTAG']);
            $result = strtoupper(trim($data['FTR']));

            if ($homeTeam === '' || $awayTeam === '' || $homeGoals === '' || $awayGoals === '' || $result === '') {
                continue;
            }

            if (!is_numeric($homeGoals) || !is_numeric($awayGoals) || !in_array($result, ['H', 'D', 'A'], true)) {
                continue;
            }

            $homeGoals = (int) $homeGoals;
            $awayGoals = (int) $awayGoals;
            $this->ensureTeamStats($stats, $homeTeam);
            $this->ensureTeamStats($stats, $awayTeam);

            $stats[$homeTeam]['played']++;
            $stats[$awayTeam]['played']++;
            $stats[$homeTeam]['goals_for'] += $homeGoals;
            $stats[$homeTeam]['goals_against'] += $awayGoals;
            $stats[$awayTeam]['goals_for'] += $awayGoals;
            $stats[$awayTeam]['goals_against'] += $homeGoals;

            if ($result === 'H') {
                $stats[$homeTeam]['won']++;
                $stats[$awayTeam]['lost']++;
                $stats[$homeTeam]['points'] += 3;
            } elseif ($result === 'A') {
                $stats[$awayTeam]['won']++;
                $stats[$homeTeam]['lost']++;
                $stats[$awayTeam]['points'] += 3;
            } else {
                $stats[$homeTeam]['drawn']++;
                $stats[$awayTeam]['drawn']++;
                $stats[$homeTeam]['points']++;
                $stats[$awayTeam]['points']++;
            }

            $completedMatches++;
        }

        if (empty($stats)) {
            throw new Exception("Tidak ada data pertandingan valid untuk season $seasonStartYear/" . ($seasonStartYear + 1));
        }

        foreach ($stats as $teamName => &$teamStats) {
            $teamStats['team_name'] = $teamName;
            $teamStats['goal_difference'] = $teamStats['goals_for'] - $teamStats['goals_against'];
        }
        unset($teamStats);

        usort($stats, function($a, $b) {
            return [$b['points'], $b['goal_difference'], $b['goals_for'], $a['team_name']]
                <=> [$a['points'], $a['goal_difference'], $a['goals_for'], $b['team_name']];
        });

        $teamSeasons = [];
        foreach ($stats as $index => $teamStats) {
            $teamSeasons[] = [
                'year_start' => $seasonStartYear,
                'year_end' => $seasonStartYear + 1,
                'team_name' => $teamStats['team_name'],
                'position' => $index + 1,
                'played' => $teamStats['played'],
                'won' => $teamStats['won'],
                'drawn' => $teamStats['drawn'],
                'lost' => $teamStats['lost'],
                'goals_for' => $teamStats['goals_for'],
                'goals_against' => $teamStats['goals_against'],
                'goal_difference' => $teamStats['goal_difference'],
                'points' => $teamStats['points'],
                'is_champion' => $index === 0 ? 'Ya' : 'Tidak'
            ];
        }

        return [
            'completed_matches' => $completedMatches,
            'team_seasons' => $teamSeasons
        ];
    }

    private function parseCsv($csv) {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    private function ensureTeamStats(&$stats, $teamName) {
        if (isset($stats[$teamName])) {
            return;
        }

        $stats[$teamName] = [
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0
        ];
    }

    private function saveTeamSeasons($teamSeasons, $replaceExisting) {
        if (empty($teamSeasons)) {
            throw new Exception('Tidak ada data team season untuk disimpan');
        }

        if ($replaceExisting) {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("TRUNCATE TABLE team_season");
            $this->pdo->exec("TRUNCATE TABLE dataset");
            $this->pdo->exec("TRUNCATE TABLE prediction_result");
            $this->pdo->exec("TRUNCATE TABLE model_performance");
            $this->pdo->exec("TRUNCATE TABLE season");
            $this->pdo->exec("TRUNCATE TABLE team");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }

        $this->pdo->beginTransaction();

        try {
            $teamIdMap = $this->loadTeamIdMap();
            $seasonIdMap = $this->loadSeasonIdMap();

            foreach ($teamSeasons as $teamSeason) {
                $teamName = $teamSeason['team_name'];
                if (!isset($teamIdMap[$teamName])) {
                    $teamIdMap[$teamName] = $this->insertTeam($teamName);
                }

                $seasonKey = $teamSeason['year_start'] . '-' . $teamSeason['year_end'];
                if (!isset($seasonIdMap[$seasonKey])) {
                    $seasonIdMap[$seasonKey] = $this->insertSeason(
                        $teamSeason['year_start'],
                        $teamSeason['year_end'],
                        $this->countTeamsForSeason($teamSeasons, $teamSeason['year_start'])
                    );
                }

                $this->upsertTeamSeason($seasonIdMap[$seasonKey], $teamIdMap[$teamName], $teamSeason);

                if ($teamSeason['is_champion'] === 'Ya') {
                    $this->updateSeasonChampion($seasonIdMap[$seasonKey], $teamIdMap[$teamName]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function loadTeamIdMap() {
        $stmt = $this->pdo->query("SELECT team_id, name FROM team");
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['name']] = (int) $row['team_id'];
        }
        return $map;
    }

    private function loadSeasonIdMap() {
        $stmt = $this->pdo->query("SELECT season_id, year_start, year_end FROM season");
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['year_start'] . '-' . $row['year_end']] = (int) $row['season_id'];
        }
        return $map;
    }

    private function insertTeam($teamName) {
        $stmt = $this->pdo->prepare("
            INSERT INTO team (name, full_name, short_name)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$teamName, $teamName, $this->extractShortName($teamName)]);
        return (int) $this->pdo->lastInsertId();
    }

    private function insertSeason($yearStart, $yearEnd, $totalTeams) {
        $stmt = $this->pdo->prepare("
            INSERT INTO season (year_start, year_end, total_teams)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$yearStart, $yearEnd, $totalTeams]);
        return (int) $this->pdo->lastInsertId();
    }

    private function upsertTeamSeason($seasonId, $teamId, $teamSeason) {
        $stmt = $this->pdo->prepare("
            INSERT INTO team_season
            (season_id, team_id, position, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, is_champion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            position = VALUES(position),
            played = VALUES(played),
            won = VALUES(won),
            drawn = VALUES(drawn),
            lost = VALUES(lost),
            goals_for = VALUES(goals_for),
            goals_against = VALUES(goals_against),
            goal_difference = VALUES(goal_difference),
            points = VALUES(points),
            is_champion = VALUES(is_champion)
        ");

        $stmt->execute([
            $seasonId,
            $teamId,
            $teamSeason['position'],
            $teamSeason['played'],
            $teamSeason['won'],
            $teamSeason['drawn'],
            $teamSeason['lost'],
            $teamSeason['goals_for'],
            $teamSeason['goals_against'],
            $teamSeason['goal_difference'],
            $teamSeason['points'],
            $teamSeason['is_champion']
        ]);
    }

    private function updateSeasonChampion($seasonId, $teamId) {
        $stmt = $this->pdo->prepare("UPDATE season SET champion_team_id = ? WHERE season_id = ?");
        $stmt->execute([$teamId, $seasonId]);
    }

    private function countTeamsForSeason($teamSeasons, $yearStart) {
        $count = 0;
        foreach ($teamSeasons as $teamSeason) {
            if ((int) $teamSeason['year_start'] === (int) $yearStart) {
                $count++;
            }
        }
        return $count;
    }

    private function normalizeTeamName($name) {
        $name = trim($name);
        $mapping = [
            'Man United' => 'Manchester United',
            'Man Utd' => 'Manchester United',
            'Man City' => 'Manchester City',
            'Newcastle Utd' => 'Newcastle United',
            'Sheffield United' => 'Sheffield United',
            'Sheffield Utd' => 'Sheffield United',
            'Tottenham' => 'Tottenham Hotspur',
            'Wolves' => 'Wolverhampton Wanderers',
            'West Ham' => 'West Ham United',
            'Brighton' => 'Brighton & Hove Albion',
            'Bournemouth' => 'AFC Bournemouth',
            'Nott\'m Forest' => 'Nottingham Forest',
            'Nottm Forest' => 'Nottingham Forest',
            'QPR' => 'Queens Park Rangers',
            'West Brom' => 'West Bromwich Albion'
        ];

        return isset($mapping[$name]) ? $mapping[$name] : $name;
    }

    private function extractShortName($fullName) {
        $shortNames = [
            'Manchester United' => 'Man Utd',
            'Manchester City' => 'Man City',
            'Tottenham Hotspur' => 'Spurs',
            'West Bromwich Albion' => 'West Brom',
            'West Ham United' => 'West Ham',
            'Brighton & Hove Albion' => 'Brighton',
            'Wolverhampton Wanderers' => 'Wolves',
            'AFC Bournemouth' => 'Bournemouth',
            'Newcastle United' => 'Newcastle',
            'Nottingham Forest' => 'Forest',
            'Queens Park Rangers' => 'QPR'
        ];

        return isset($shortNames[$fullName]) ? $shortNames[$fullName] : $fullName;
    }
}
