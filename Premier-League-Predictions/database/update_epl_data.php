<?php
/**
 * Update EPL dataset from Football-Data CSV files.
 *
 * Examples:
 * php database/update_epl_data.php --from=2025 --to=2025 --replace
 * php database/update_epl_data.php --from=2025 --to=2026 --append
 * php database/update_epl_data.php --file=C:\data\E0.csv --season=2025 --replace
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/epl_importer.php';

function printUsage() {
    echo "Usage:\n";
    echo "  php database/update_epl_data.php --from=2025 --to=2025 --replace\n";
    echo "  php database/update_epl_data.php --from=2025 --to=2026 --append\n";
    echo "  php database/update_epl_data.php --file=E0.csv --season=2025 --replace\n";
}

$options = getopt('', ['from:', 'to:', 'season:', 'file:', 'replace', 'append', 'help']);

if (isset($options['help'])) {
    printUsage();
    exit(0);
}

$replaceExisting = isset($options['append']) ? false : true;

try {
    $pdo = getDBConnection();
    $importer = new EplDataImporter($pdo);

    echo "=== UPDATE DATASET EPL ===\n\n";

    if (isset($options['file'])) {
        if (!isset($options['season']) || !is_numeric($options['season'])) {
            throw new Exception('Parameter --season wajib diisi saat memakai --file');
        }

        $result = $importer->importFootballDataFile(
            $options['file'],
            (int) $options['season'],
            $replaceExisting
        );
    } else {
        if (!isset($options['from']) || !is_numeric($options['from'])) {
            throw new Exception('Parameter --from wajib diisi');
        }

        $from = (int) $options['from'];
        $to = isset($options['to']) && is_numeric($options['to']) ? (int) $options['to'] : $from;

        $result = $importer->importFootballDataRange($from, $to, $replaceExisting);
    }

    foreach ($result['sources'] as $source) {
        echo "Season: {$source['season']}\n";
        echo "Source: {$source['source']}\n";
        echo "Completed matches: {$source['completed_matches']}\n";
        echo "Teams: {$source['teams']}\n\n";
    }

    echo "Total seasons imported: {$result['total_seasons']}\n";
    echo "Total team-season rows: {$result['total_team_seasons']}\n";
    echo "\n=== UPDATE SELESAI ===\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
    printUsage();
    exit(1);
}
