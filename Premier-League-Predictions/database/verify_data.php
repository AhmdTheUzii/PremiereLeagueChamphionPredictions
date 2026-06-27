<?php
/**
 * Verify Database Data
 * Premier League Predictions - Naive Bayes
 * 
 * File ini akan:
 * 1. Memverifikasi data yang sudah diimport
 * 2. Menampilkan statistik data
 * 3. Menampilkan sample data
 */

// Konfigurasi Database
$host = 'localhost';
$dbname = 'epl_naivebayes';

try {
    // Koneksi ke database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFIKASI DATABASE PREMIER LEAGUE ===\n\n";
    
    // 1. Cek jumlah data per tabel
    echo "1. Jumlah Data per Tabel:\n";
    echo "   ----------------------------------------\n";
    
    $tables = ['season', 'team', 'team_season', 'dataset', 'prediction_result', 'model_performance'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   $table: $count records\n";
    }
    
    echo "\n";
    
    // 2. Sample data season
    echo "2. Sample Data Season (5 terakhir):\n";
    echo "   ----------------------------------------\n";
    $stmt = $pdo->query("SELECT s.season_id, s.year_start, s.year_end, s.total_teams, t.name as champion 
                         FROM season s 
                         LEFT JOIN team t ON s.champion_team_id = t.team_id 
                         ORDER BY s.year_start DESC LIMIT 5");
    
    echo "   ID\tStart\tEnd\tTeams\tChampion\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   {$row['season_id']}\t{$row['year_start']}\t{$row['year_end']}\t{$row['total_teams']}\t{$row['champion']}\n";
    }
    
    echo "\n";
    
    // 3. Sample data team
    echo "3. Sample Data Team (10 teratas):\n";
    echo "   ----------------------------------------\n";
    $stmt = $pdo->query("SELECT team_id, name, short_name, city FROM team ORDER BY name ASC LIMIT 10");
    
    echo "   ID\tName\t\tShort\tCity\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   {$row['team_id']}\t{$row['name']}\t{$row['short_name']}\t{$row['city']}\n";
    }
    
    echo "\n";
    
    // 4. Sample data team_season
    echo "4. Sample Data Team Season (Season 2024):\n";
    echo "   ----------------------------------------\n";
    $stmt = $pdo->query("SELECT ts.id, t.name as team, ts.position, ts.played, ts.won, ts.drawn, ts.lost, 
                         ts.goals_for, ts.goals_against, ts.goal_difference, ts.points, ts.is_champion
                         FROM team_season ts
                         JOIN team t ON ts.team_id = t.team_id
                         JOIN season s ON ts.season_id = s.season_id
                         WHERE s.year_start = 2024
                         ORDER BY ts.position ASC");
    
    echo "   Pos\tTeam\t\t\tP\tW\tD\tL\tGF\tGA\tGD\tPts\tChamp\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $teamName = substr($row['team'], 0, 15);
        echo "   {$row['position']}\t{$teamName}\t\t{$row['played']}\t{$row['won']}\t{$row['drawn']}\t{$row['lost']}\t{$row['goals_for']}\t{$row['goals_against']}\t{$row['goal_difference']}\t{$row['points']}\t{$row['is_champion']}\n";
    }
    
    echo "\n";
    
    // 5. Statistik champion
    echo "5. Statistik Juara Terbanyak:\n";
    echo "   ----------------------------------------\n";
    $stmt = $pdo->query("SELECT t.name as team, COUNT(s.season_id) as champion_count
                         FROM season s
                         JOIN team t ON s.champion_team_id = t.team_id
                         GROUP BY t.name
                         ORDER BY champion_count DESC
                         LIMIT 10");
    
    echo "   Team\t\t\tChampion Count\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $teamName = substr($row['team'], 0, 15);
        echo "   {$teamName}\t\t{$row['champion_count']}\n";
    }
    
    echo "\n";
    
    // 6. Validasi integritas data
    echo "6. Validasi Integritas Data:\n";
    echo "   ----------------------------------------\n";
    
    // Cek apakah ada team_season tanpa season
    $stmt = $pdo->query("SELECT COUNT(*) FROM team_season WHERE season_id NOT IN (SELECT season_id FROM season)");
    $invalidSeason = $stmt->fetchColumn();
    echo "   Team season tanpa season valid: " . ($invalidSeason == 0 ? "✓ OK" : "✗ ERROR: $invalidSeason") . "\n";
    
    // Cek apakah ada team_season tanpa team
    $stmt = $pdo->query("SELECT COUNT(*) FROM team_season WHERE team_id NOT IN (SELECT team_id FROM team)");
    $invalidTeam = $stmt->fetchColumn();
    echo "   Team season tanpa team valid: " . ($invalidTeam == 0 ? "✓ OK" : "✗ ERROR: $invalidTeam") . "\n";
    
    // Cek apakah played = won + drawn + lost
    $stmt = $pdo->query("SELECT COUNT(*) FROM team_season WHERE played != won + drawn + lost");
    $invalidPlayed = $stmt->fetchColumn();
    echo "   Validasi played = won + drawn + lost: " . ($invalidPlayed == 0 ? "✓ OK" : "✗ ERROR: $invalidPlayed") . "\n";
    
    // Cek apakah gd = gf - ga
    $stmt = $pdo->query("SELECT COUNT(*) FROM team_season WHERE goal_difference != goals_for - goals_against");
    $invalidGD = $stmt->fetchColumn();
    echo "   Validasi gd = gf - ga: " . ($invalidGD == 0 ? "✓ OK" : "✗ ERROR: $invalidGD") . "\n";
    
    // Cek apakah ada champion per season
    $stmt = $pdo->query("SELECT COUNT(DISTINCT season_id) FROM team_season WHERE is_champion = 'Ya'");
    $championCount = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM season");
    $seasonCount = $stmt->fetchColumn();
    echo "   Champion per season: " . ($championCount == $seasonCount ? "✓ OK ($championCount/$seasonCount)" : "✗ ERROR ($championCount/$seasonCount)") . "\n";
    
    echo "\n";
    
    echo "=== VERIFIKASI SELESAI ===\n";
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
