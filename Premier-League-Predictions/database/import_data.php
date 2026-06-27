<?php
/**
 * Import Data dari CSV ke Database
 * Premier League Predictions - Naive Bayes
 * 
 * File ini akan:
 * 1. Membaca file CSV Premier League Table.csv
 * 2. Membersihkan dan normalisasi data
 * 3. Import ke tabel season, team, dan team_season
 */

// Konfigurasi Database
$host = 'localhost';
$dbname = 'epl_naivebayes';

// Path file CSV
$csvFile = __DIR__ . '/../Premier League Table.csv';

try {
    // Koneksi ke database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== IMPORT DATA PREMIER LEAGUE ===\n\n";
    
    // Cek apakah file CSV ada
    if (!file_exists($csvFile)) {
        die("Error: File CSV tidak ditemukan di: $csvFile\n");
    }
    
    echo "1. Membaca file CSV...\n";
    
    // Baca file CSV
    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        die("Error: Tidak dapat membuka file CSV\n");
    }
    
    // Baca header
    $header = fgetcsv($handle);
    $requiredColumns = ['season', 'team', 'position', 'played', 'won', 'drawn', 'lost', 'gf', 'ga', 'gd', 'points', 'Juara'];

    if ($header === false) {
        die("Error: Header CSV tidak ditemukan\n");
    }

    $header = array_map('trim', $header);
    $missingColumns = array_diff($requiredColumns, $header);

    if (!empty($missingColumns)) {
        die("Error: Format CSV tidak sesuai. Kolom yang kurang: " . implode(', ', $missingColumns) . "\n");
    }

    echo "   Header: " . implode(', ', $header) . "\n";
    
    // Array untuk menyimpan data unik
    $seasons = [];
    $teams = [];
    $teamSeasons = [];
    $seenSeasonTeams = [];
    
    // Baca data baris per baris
    $rowNumber = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
        $displayRowNumber = $rowNumber + 1;

        if (count($row) !== count($header)) {
            die("Error: Jumlah kolom tidak sesuai pada baris $displayRowNumber\n");
        }

        $csvData = array_combine($header, $row);
        if ($csvData === false) {
            die("Error: Gagal menggabungkan header dengan data pada baris $displayRowNumber\n");
        }

        foreach ($requiredColumns as $column) {
            if (!isset($csvData[$column]) || trim($csvData[$column]) === '') {
                die("Error: Data kolom $column kosong pada baris $displayRowNumber\n");
            }
        }

        $numericColumns = ['season', 'position', 'played', 'won', 'drawn', 'lost', 'gf', 'ga', 'gd', 'points'];
        foreach ($numericColumns as $column) {
            if (!is_numeric($csvData[$column])) {
                die("Error: Kolom $column harus numerik pada baris $displayRowNumber\n");
            }
        }
        
        // Mapping kolom CSV ke variabel
        $seasonYear = intval($csvData['season']);       // 1993, 1994, dst
        $teamName = trim($csvData['team']);             // Manchester Utd, Aston Villa, dst
        $position = intval($csvData['position']);       // 1-22
        $played = intval($csvData['played']);           // Jumlah pertandingan
        $won = intval($csvData['won']);                 // Kemenangan
        $drawn = intval($csvData['drawn']);             // Seri
        $lost = intval($csvData['lost']);               // Kekalahan
        $gf = intval($csvData['gf']);                   // Goals For
        $ga = intval($csvData['ga']);                   // Goals Against
        $gd = intval($csvData['gd']);                   // Goal Difference
        $points = intval($csvData['points']);           // Poin
        $isChampionRaw = trim($csvData['Juara']);       // Ya, Tidak, atau Juara

        if ($played !== ($won + $drawn + $lost)) {
            die("Error: played tidak sama dengan won + drawn + lost pada baris $displayRowNumber\n");
        }

        if ($gd !== ($gf - $ga)) {
            die("Error: gd tidak sama dengan gf - ga pada baris $displayRowNumber\n");
        }
        
        // Normalisasi nama tim
        $teamNameNormalized = normalizeTeamName($teamName);
        $duplicateKey = $seasonYear . '|' . strtolower($teamNameNormalized);

        if (isset($seenSeasonTeams[$duplicateKey])) {
            die("Error: Duplikasi data season-team pada baris $displayRowNumber ($seasonYear - $teamNameNormalized)\n");
        }

        $seenSeasonTeams[$duplicateKey] = true;
        
        // Normalisasi status juara
        $isChampion = normalizeChampionStatus($isChampionRaw);
        
        // Tentukan season (year_start dan year_end)
        $yearStart = $seasonYear;
        $yearEnd = $seasonYear + 1;
        
        // Simpan data season
        $seasonKey = "$yearStart-$yearEnd";
        if (!isset($seasons[$seasonKey])) {
            $seasons[$seasonKey] = [
                'year_start' => $yearStart,
                'year_end' => $yearEnd,
                'total_teams' => 0
            ];
        }
        
        // Simpan data team
        if (!isset($teams[$teamNameNormalized])) {
            $teams[$teamNameNormalized] = [
                'name' => $teamNameNormalized,
                'full_name' => $teamNameNormalized,
                'short_name' => extractShortName($teamNameNormalized)
            ];
        }
        
        // Simpan data team_season
        $teamSeasons[] = [
            'season_key' => $seasonKey,
            'team_name' => $teamNameNormalized,
            'position' => $position,
            'played' => $played,
            'won' => $won,
            'drawn' => $drawn,
            'lost' => $lost,
            'goals_for' => $gf,
            'goals_against' => $ga,
            'goal_difference' => $gd,
            'points' => $points,
            'is_champion' => $isChampion
        ];
        
        // Update total teams per season
        $seasons[$seasonKey]['total_teams']++;
    }
    
    fclose($handle);
    
    echo "   Selesai membaca $rowNumber baris data\n\n";
    
    // Truncate tabel sebelum import
    echo "2. Membersihkan tabel database...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE team_season");
    $pdo->exec("TRUNCATE TABLE dataset");
    $pdo->exec("TRUNCATE TABLE prediction_result");
    $pdo->exec("TRUNCATE TABLE model_performance");
    $pdo->exec("TRUNCATE TABLE team");
    $pdo->exec("TRUNCATE TABLE season");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "   Tabel berhasil dibersihkan\n\n";
    
    // Import data season
    echo "3. Import data season...\n";
    $seasonStmt = $pdo->prepare("INSERT INTO season (year_start, year_end, total_teams) VALUES (:year_start, :year_end, :total_teams)");
    $seasonIdMap = [];
    
    foreach ($seasons as $key => $season) {
        $seasonStmt->execute([
            ':year_start' => $season['year_start'],
            ':year_end' => $season['year_end'],
            ':total_teams' => $season['total_teams']
        ]);
        $seasonIdMap[$key] = $pdo->lastInsertId();
    }
    echo "   Berhasil import " . count($seasons) . " season\n\n";
    
    // Import data team
    echo "4. Import data team...\n";
    $teamStmt = $pdo->prepare("INSERT INTO team (name, full_name, short_name) VALUES (:name, :full_name, :short_name)");
    $teamIdMap = [];
    
    foreach ($teams as $name => $team) {
        $teamStmt->execute([
            ':name' => $team['name'],
            ':full_name' => $team['full_name'],
            ':short_name' => $team['short_name']
        ]);
        $teamIdMap[$name] = $pdo->lastInsertId();
    }
    echo "   Berhasil import " . count($teams) . " team\n\n";
    
    // Import data team_season
    echo "5. Import data team_season...\n";
    $teamSeasonStmt = $pdo->prepare("INSERT INTO team_season (season_id, team_id, position, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, is_champion) VALUES (:season_id, :team_id, :position, :played, :won, :drawn, :lost, :goals_for, :goals_against, :goal_difference, :points, :is_champion)");
    
    $championTeamIds = []; // Untuk update champion_team_id di tabel season
    
    foreach ($teamSeasons as $ts) {
        $seasonId = $seasonIdMap[$ts['season_key']];
        $teamId = $teamIdMap[$ts['team_name']];
        
        $teamSeasonStmt->execute([
            ':season_id' => $seasonId,
            ':team_id' => $teamId,
            ':position' => $ts['position'],
            ':played' => $ts['played'],
            ':won' => $ts['won'],
            ':drawn' => $ts['drawn'],
            ':lost' => $ts['lost'],
            ':goals_for' => $ts['goals_for'],
            ':goals_against' => $ts['goals_against'],
            ':goal_difference' => $ts['goal_difference'],
            ':points' => $ts['points'],
            ':is_champion' => $ts['is_champion']
        ]);
        
        // Simpan champion team untuk setiap season
        if ($ts['is_champion'] === 'Ya') {
            $championTeamIds[$ts['season_key']] = $teamId;
        }
    }
    echo "   Berhasil import " . count($teamSeasons) . " team_season\n\n";
    
    // Update champion_team_id di tabel season
    echo "6. Update champion team per season...\n";
    $updateChampionStmt = $pdo->prepare("UPDATE season SET champion_team_id = :team_id WHERE season_id = :season_id");
    
    foreach ($championTeamIds as $seasonKey => $teamId) {
        $seasonId = $seasonIdMap[$seasonKey];
        $updateChampionStmt->execute([
            ':team_id' => $teamId,
            ':season_id' => $seasonId
        ]);
    }
    echo "   Berhasil update " . count($championTeamIds) . " champion\n\n";
    
    echo "=== IMPORT SELESAI ===\n";
    echo "Total Season: " . count($seasons) . "\n";
    echo "Total Team: " . count($teams) . "\n";
    echo "Total Team Season: " . count($teamSeasons) . "\n\n";
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

/**
 * Normalisasi nama tim
 */
function normalizeTeamName($name) {
    $name = trim($name);
    
    // Mapping nama tim yang tidak konsisten
    $mapping = [
        'Manchester Utd' => 'Manchester United',
        'Newcastle Utd' => 'Newcastle United',
        'Sheffield Weds' => 'Sheffield Wednesday',
        'Tottenham' => 'Tottenham Hotspur',
        'Charlton Ath' => 'Charlton Athletic',
        'Ipswich Town' => 'Ipswich Town',
        'Norwich City' => 'Norwich City',
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
        'Luton Town' => 'Luton Town',
        'Brentford' => 'Brentford',
        'Bournemouth' => 'AFC Bournemouth',
        'Crystal Palace' => 'Crystal Palace',
        'Fulham' => 'Fulham',
        'Leicester City' => 'Leicester City',
        'Leeds United' => 'Leeds United',
        'Southampton' => 'Southampton',
        'Everton' => 'Everton',
        'Aston Villa' => 'Aston Villa',
        'West Ham' => 'West Ham United',
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
        'QPR' => 'Queens Park Rangers',
        'Sheffield Utd' => 'Sheffield United',
    ];
    
    return isset($mapping[$name]) ? $mapping[$name] : $name;
}

/**
 * Normalisasi status juara
 */
function normalizeChampionStatus($status) {
    $status = strtolower(trim($status));
    
    if ($status === 'ya' || $status === 'juara') {
        return 'Ya';
    }
    
    return 'Tidak';
}

/**
 * Ekstrak short name dari nama tim
 */
function extractShortName($fullName) {
    // Daftar short name umum
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
    ];
    
    return isset($shortNames[$fullName]) ? $shortNames[$fullName] : $fullName;
}
