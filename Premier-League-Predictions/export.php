<?php
/**
 * Export Router
 * Premier League Predictions - Naive Bayes
 * 
 * Endpoint terpusat untuk mendownload laporan dalam format CSV atau PDF.
 */

require_once 'config/config.php';
require_once 'includes/report_exporter.php';

// Pastikan hanya admin yang bisa mendownload
requireAdminLogin();

$report = sanitize($_GET['report'] ?? '');
$format = sanitize($_GET['format'] ?? '');

if (empty($report) || empty($format)) {
    die("Parameter report dan format wajib diisi.");
}

if ($format !== 'csv' && $format !== 'pdf') {
    die("Format laporan tidak didukung (harus csv atau pdf).");
}

// Bersihkan semua buffer output untuk mencegah kerusakan file PDF/CSV
while (ob_get_level()) {
    ob_end_clean();
}

$pdo = getDBConnection();

switch ($report) {
    case 'standings':
        $seasonId = isset($_GET['season']) ? intval($_GET['season']) : 0;
        
        // Get season info
        $stmt = $pdo->prepare("
            SELECT s.*, t.name as champion_name 
            FROM season s 
            LEFT JOIN team t ON s.champion_team_id = t.team_id 
            WHERE s.season_id = ?
        ");
        $stmt->execute([$seasonId]);
        $seasonInfo = $stmt->fetch();
        
        if (!$seasonInfo) {
            die("Musim tidak ditemukan.");
        }
        
        // Get standings list
        $stmt = $pdo->prepare("
            SELECT ts.*, t.name as team_name 
            FROM team_season ts
            JOIN team t ON ts.team_id = t.team_id
            WHERE ts.season_id = ?
            ORDER BY ts.position ASC
        ");
        $stmt->execute([$seasonId]);
        $standings = $stmt->fetchAll();
        
        if ($format === 'csv') {
            ReportExporter::exportStandingsCsv($seasonInfo, $standings);
        } else {
            ReportExporter::exportStandingsPdf($seasonInfo, $standings);
        }
        break;

    case 'team_stats':
        $teamId = isset($_GET['team']) ? intval($_GET['team']) : 0;
        
        // Get team info
        $stmt = $pdo->prepare("SELECT * FROM team WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $teamInfo = $stmt->fetch();
        
        if (!$teamInfo) {
            die("Tim tidak ditemukan.");
        }
        
        // Get history stats
        $stmt = $pdo->prepare("
            SELECT ts.*, s.year_start, s.year_end
            FROM team_season ts
            JOIN season s ON ts.season_id = s.season_id
            WHERE ts.team_id = ?
            ORDER BY s.year_start DESC
        ");
        $stmt->execute([$teamId]);
        $stats = $stmt->fetchAll();
        
        if ($format === 'csv') {
            ReportExporter::exportTeamStatsCsv($teamInfo, $stats);
        } else {
            ReportExporter::exportTeamStatsPdf($teamInfo, $stats);
        }
        break;

    case 'evaluation':
        // Get latest model performance summary
        $stmt = $pdo->query("SELECT * FROM model_performance ORDER BY created_at DESC LIMIT 1");
        $perf = $stmt->fetch();
        
        if (!$perf) {
            die("Data evaluasi belum tersedia. Silakan lakukan evaluasi model di halaman Evaluasi Model terlebih dahulu.");
        }
        
        $evaluationResult = [
            'accuracy' => $perf['accuracy'],
            'precision' => $perf['precision'],
            'recall' => $perf['recall'],
            'f1_score' => $perf['f1_score'],
            'confusion_matrix' => [
                'true_positive' => $perf['true_positive'],
                'true_negative' => $perf['true_negative'],
                'false_positive' => $perf['false_positive'],
                'false_negative' => $perf['false_negative']
            ]
        ];
        
        // Get testing predictions detail from DB
        $stmt = $pdo->query("
            SELECT pr.*, t.name as team, s.year_start as season
            FROM prediction_result pr
            JOIN team t ON pr.team_id = t.team_id
            JOIN season s ON pr.season_id = s.season_id
            WHERE pr.actual_label IS NOT NULL
            ORDER BY s.year_start ASC, pr.prediction_id ASC
        ");
        $rows = $stmt->fetchAll();
        
        $predictions = [];
        foreach ($rows as $r) {
            $predictions[] = [
                'season' => $r['season'],
                'team' => $r['team'],
                'actual' => $r['actual_label'],
                'predicted' => $r['predicted_label'],
                'prob_juara' => $r['champion_probability'],
                'prob_not_juara' => $r['not_champion_probability']
            ];
        }
        $evaluationResult['predictions'] = $predictions;
        
        // Get all history
        $stmt = $pdo->query("SELECT * FROM model_performance ORDER BY created_at DESC LIMIT 10");
        $history = $stmt->fetchAll();
        
        if ($format === 'csv') {
            ReportExporter::exportEvaluationCsv($evaluationResult, $history);
        } else {
            ReportExporter::exportEvaluationPdf($evaluationResult, $history);
        }
        break;

    case 'predict_manual':
        // Retrieve manual prediction from session
        if (!isset($_SESSION['last_manual_predict'])) {
            die("Belum ada data prediksi manual yang disimpan dalam sesi. Lakukan prediksi terlebih dahulu.");
        }
        
        $data = $_SESSION['last_manual_predict'];
        
        if ($format === 'csv') {
            ReportExporter::exportPredictionCsv('manual', $data);
        } else {
            ReportExporter::exportPredictionPdf('manual', $data);
        }
        break;

    case 'predict_csv':
        // Retrieve CSV predictions list from session
        if (!isset($_SESSION['last_csv_predict'])) {
            die("Belum ada data prediksi CSV yang disimpan dalam sesi. Lakukan unggahan dan prediksi CSV terlebih dahulu.");
        }
        
        $data = $_SESSION['last_csv_predict'];
        
        if ($format === 'csv') {
            ReportExporter::exportPredictionCsv('csv', $data);
        } else {
            ReportExporter::exportPredictionPdf('csv', $data);
        }
        break;

    default:
        die("Jenis laporan tidak dikenali.");
}
