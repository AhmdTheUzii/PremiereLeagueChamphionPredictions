<?php
/**
 * Report Exporter Helper Class
 * Premier League Predictions - Naive Bayes
 * 
 * Class ini menangani ekspor data ke format CSV dan PDF.
 */

require_once __DIR__ . '/fpdf.php';

class EPL_PDF extends FPDF {
    protected $reportTitle;
    protected $subTitle;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $title = '', $subTitle = '') {
        parent::__construct($orientation, $unit, $size);
        $this->reportTitle = $title;
        $this->subTitle = $subTitle;
    }

    public function AddFont($family, $style = '', $file = '', $dir = '') {
        $family = strtolower($family);
        if ($family === 'arial') {
            $family = 'helvetica';
        }
        if (in_array($family, $this->CoreFonts, true)) {
            $style = strtoupper($style);
            if ($style === 'IB') {
                $style = 'BI';
            }
            if ($family === 'symbol' || $family === 'zapfdingbats') {
                $style = '';
            }
            $fontkey = $family . $style;
            if (isset($this->fonts[$fontkey])) {
                return;
            }
            $this->fonts[$fontkey] = $this->getCoreFontInfo($family, $style);
            return;
        }
        parent::AddFont($family, $style, $file, $dir);
    }

    private function getCoreFontInfo($family, $style) {
        $fonts = self::getCoreFontMetrics();
        $key = $family . $style;
        $info = $fonts[$key] ?? $fonts[$family . ''];
        $info['i'] = count($this->fonts) + 1;
        $info['subsetted'] = false;
        $converted = [];
        foreach ($info['cw'] as $c => $w) {
            $converted[chr($c)] = $w;
        }
        $info['cw'] = $converted;
        return $info;
    }

    private static function getCoreFontMetrics() {
        return [
            'helvetica' => ['fontkey' => 'helvetica', 'type' => 'Core', 'name' => 'Helvetica', 'up' => -100, 'ut' => 50, 'dw' => 556, 'cw' => self::helveticaWidths()],
            'helveticaB' => ['fontkey' => 'helveticaB', 'type' => 'Core', 'name' => 'Helvetica-Bold', 'up' => -100, 'ut' => 50, 'dw' => 556, 'cw' => self::helveticaBoldWidths()],
            'helveticaI' => ['fontkey' => 'helveticaI', 'type' => 'Core', 'name' => 'Helvetica-Oblique', 'up' => -100, 'ut' => 50, 'dw' => 556, 'cw' => self::helveticaWidths()],
            'helveticaBI' => ['fontkey' => 'helveticaBI', 'type' => 'Core', 'name' => 'Helvetica-BoldOblique', 'up' => -100, 'ut' => 50, 'dw' => 556, 'cw' => self::helveticaBoldWidths()],
            'times' => ['fontkey' => 'times', 'type' => 'Core', 'name' => 'Times-Roman', 'up' => -100, 'ut' => 50, 'dw' => 500, 'cw' => self::timesWidths()],
            'timesB' => ['fontkey' => 'timesB', 'type' => 'Core', 'name' => 'Times-Bold', 'up' => -100, 'ut' => 50, 'dw' => 500, 'cw' => self::timesBoldWidths()],
            'timesI' => ['fontkey' => 'timesI', 'type' => 'Core', 'name' => 'Times-Italic', 'up' => -100, 'ut' => 50, 'dw' => 500, 'cw' => self::timesItalicWidths()],
            'timesBI' => ['fontkey' => 'timesBI', 'type' => 'Core', 'name' => 'Times-BoldItalic', 'up' => -100, 'ut' => 50, 'dw' => 500, 'cw' => self::timesBoldItalicWidths()],
            'courier' => ['fontkey' => 'courier', 'type' => 'Core', 'name' => 'Courier', 'up' => -100, 'ut' => 50, 'dw' => 600, 'cw' => self::courierWidths()],
            'courierB' => ['fontkey' => 'courierB', 'type' => 'Core', 'name' => 'Courier-Bold', 'up' => -100, 'ut' => 50, 'dw' => 600, 'cw' => self::courierWidths()],
            'courierI' => ['fontkey' => 'courierI', 'type' => 'Core', 'name' => 'Courier-Oblique', 'up' => -100, 'ut' => 50, 'dw' => 600, 'cw' => self::courierWidths()],
            'courierBI' => ['fontkey' => 'courierBI', 'type' => 'Core', 'name' => 'Courier-BoldOblique', 'up' => -100, 'ut' => 50, 'dw' => 600, 'cw' => self::courierWidths()],
        ];
    }

    private static function helveticaWidths() {
        $w = array_fill(0, 256, 556);
        $map = [32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function helveticaBoldWidths() {
        $w = array_fill(0, 256, 556);
        $map = [32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function timesWidths() {
        $w = array_fill(0, 256, 500);
        $map = [32=>250,33=>333,34=>408,35=>500,36=>500,37=>833,38=>778,39=>180,40=>333,41=>333,42=>500,43=>564,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>278,59=>278,60=>564,61=>564,62=>564,63=>444,64=>921,65=>722,66=>667,67=>667,68=>722,69=>611,70=>556,71=>722,72=>722,73=>333,74=>389,75=>722,76=>611,77=>889,78=>722,79=>722,80=>556,81=>722,82=>667,83=>556,84=>611,85=>722,86=>722,87=>944,88=>722,89=>722,90=>611,91=>333,92=>278,93=>333,94=>469,95=>500,96=>333,97=>444,98=>500,99=>444,100=>500,101=>444,102=>333,103=>500,104=>500,105=>278,106=>278,107=>500,108=>278,109=>778,110=>500,111=>500,112=>500,113=>500,114=>333,115=>389,116=>278,117=>500,118=>500,119=>722,120=>500,121=>500,122=>444,123=>480,124=>200,125=>480,126=>541];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function timesBoldWidths() {
        $w = array_fill(0, 256, 500);
        $map = [32=>250,33=>333,34=>555,35=>500,36=>500,37=>1000,38=>833,39=>278,40=>333,41=>333,42=>500,43=>570,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>570,61=>570,62=>570,63=>500,64=>930,65=>722,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>778,73=>389,74=>500,75=>778,76=>667,77=>944,78=>722,79=>778,80=>611,81=>778,82=>722,83=>556,84=>667,85=>722,86=>722,87=>1000,88=>722,89=>722,90=>667,91=>333,92=>278,93=>333,94=>581,95=>500,96=>333,97=>500,98=>556,99=>444,100=>556,101=>444,102=>333,103=>500,104=>556,105=>278,106=>333,107=>556,108=>278,109=>833,110=>556,111=>500,112=>556,113=>556,114=>444,115=>389,116=>333,117=>556,118=>500,119=>722,120=>500,121=>500,122=>444,123=>394,124=>220,125=>394,126=>520];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function timesItalicWidths() {
        $w = array_fill(0, 256, 500);
        $map = [32=>250,33=>333,34=>420,35=>500,36=>500,37=>833,38=>778,39=>214,40=>333,41=>333,42=>500,43=>675,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>675,61=>675,62=>675,63=>500,64=>920,65=>611,66=>611,67=>667,68=>722,69=>611,70=>611,71=>722,72=>722,73=>333,74=>444,75=>667,76=>556,77=>833,78=>667,79=>722,80=>611,81=>722,82=>611,83=>500,84=>556,85=>722,86=>611,87=>833,88=>611,89=>556,90=>556,91=>389,92=>278,93=>389,94=>422,95=>500,96=>333,97=>500,98=>500,99=>444,100=>500,101=>444,102=>278,103=>500,104=>500,105=>278,106=>278,107=>444,108=>278,109=>722,110=>500,111=>500,112=>500,113=>500,114=>389,115=>389,116=>278,117=>500,118=>444,119=>667,120=>444,121=>444,122=>389,123=>400,124=>275,125=>400,126=>541];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function timesBoldItalicWidths() {
        $w = array_fill(0, 256, 500);
        $map = [32=>250,33=>389,34=>555,35=>500,36=>500,37=>833,38=>778,39=>278,40=>333,41=>333,42=>500,43=>570,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>570,61=>570,62=>570,63=>500,64=>832,65=>667,66=>667,67=>667,68=>722,69=>667,70=>667,71=>722,72=>778,73=>389,74=>500,75=>667,76=>611,77=>889,78=>722,79=>722,80=>611,81=>722,82=>667,83=>556,84=>611,85=>722,86=>667,87=>889,88=>667,89=>611,90=>611,91=>333,92=>278,93=>333,94=>570,95=>500,96=>333,97=>500,98=>500,99=>444,100=>500,101=>444,102=>333,103=>500,104=>556,105=>278,106=>278,107=>500,108=>278,109=>722,110=>556,111=>500,112=>500,113=>500,114=>389,115=>389,116=>278,117=>556,118=>444,119=>667,120=>500,121=>444,122=>389,123=>348,124=>220,125=>348,126=>570];
        foreach ($map as $k => $v) {
            $w[$k] = $v;
        }
        return $w;
    }

    private static function courierWidths() {
        return array_fill(0, 256, 600);
    }

    // Page header
    public function Header() {
        // Theme color: Dark Blue #1e3c72
        $this->SetFillColor(30, 60, 114);
        $this->Rect(0, 0, $this->GetPageWidth(), 32, 'F');

        // Text color: White
        $this->SetTextColor(255, 255, 255);
        
        // Brand Title
        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(15, 8);
        $this->Cell(0, 6, 'PREMIER LEAGUE PREDICTIONS', 0, 1, 'L');
        
        // Subtitle/Report Type
        $this->SetFont('Arial', '', 9);
        $this->SetX(15);
        $this->Cell(0, 4, strtoupper($this->reportTitle) . ($this->subTitle ? ' - ' . strtoupper($this->subTitle) : ''), 0, 1, 'L');

        // Date and Time
        $this->SetXY($this->GetPageWidth() - 75, 10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(60, 4, 'Tanggal Cetak: ' . date('d-m-Y H:i'), 0, 0, 'R');

        // Reset text color to standard black
        $this->SetTextColor(0, 0, 0);
        
        // Spacer after header band
        $this->SetY(40);
    }

    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        
        // Divider line
        $this->Line(15, $this->GetY(), $this->GetPageWidth() - 15, $this->GetY());
        
        // Page number and signature
        $this->SetY(-14);
        $this->Cell(0, 8, 'Halaman ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
        $this->SetX(15);
        $this->Cell(0, 8, 'Sistem Prediksi Naive Bayes EPL', 0, 0, 'L');
    }

    public function renderSignature() {
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $dayName = $days[date('w')];
    $dateStr = date('d-m-Y');
    $signatureText = "Jakarta, $dayName $dateStr";

    if ($this->GetY() + 55 > $this->h - $this->bMargin) {
        $this->AddPage();
    }

    $this->Ln(12);

    $pageW   = $this->GetPageWidth();
    $marginR = $this->rMargin;
    $colW    = 70; // lebar kolom tanda tangan
    $sigX    = $pageW - $marginR - $colW; // posisi X mulai kolom kanan

    // Tanggal
    $this->SetFont('Arial', '', 10);
    $this->SetTextColor(50, 50, 50);
    $this->SetXY($sigX, $this->GetY());
    $this->Cell($colW, 6, $signatureText, 0, 1, 'L');

    $this->Ln(2);

    // Label
    $this->SetFont('Arial', '', 10);
    $this->SetX($sigX);
    $this->Cell($colW, 6, 'Mengetahui,', 0, 1, 'L');

    // Ruang tanda tangan
    $this->Ln(16);
    $lineY = $this->GetY();

    $this->SetDrawColor(0, 0, 0);
    $this->SetLineWidth(0.4);
    $this->Line($sigX, $lineY, $sigX + 50, $lineY);

    $this->Ln(2);

    // Nama
    $this->SetFont('Arial', 'B', 11);
    $this->SetTextColor(0, 0, 0);
    $this->SetX($sigX);
    $this->Cell($colW, 6, 'Sadam Davi Awali', 0, 1, 'L');
}
}

class ReportExporter {
    
    /**
     * Start file streaming headers for CSV
     */
    private static function startCsvStream($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add Byte Order Mark (BOM) to fix Excel encoding issues
        echo "\xEF\xBB\xBF";
        return fopen('php://output', 'w');
    }

    // ==========================================
    // 1. LAPORAN KLASEMEN (STANDINGS)
    // ==========================================
    
    public static function exportStandingsCsv($seasonInfo, $standings) {
        $filename = 'klasemen_epl_' . $seasonInfo['year_start'] . '_' . $seasonInfo['year_end'] . '.csv';
        $output = self::startCsvStream($filename);
        
        // Write title
        fputcsv($output, ['Klasemen Premier League Musim ' . $seasonInfo['year_start'] . '/' . $seasonInfo['year_end']]);
        if ($seasonInfo['champion_name']) {
            fputcsv($output, ['Juara: ' . $seasonInfo['champion_name']]);
        }
        fputcsv($output, []); // empty row
        
        // Write headers
        fputcsv($output, ['Posisi', 'Tim', 'Main', 'Menang', 'Seri', 'Kalah', 'Gol Masuk', 'Gol Kemasukan', 'Selisih Gol', 'Poin', 'Juara']);
        
        // Write standings rows
        foreach ($standings as $row) {
            fputcsv($output, [
                $row['position'],
                $row['team_name'],
                $row['played'],
                $row['won'],
                $row['drawn'],
                $row['lost'],
                $row['goals_for'],
                $row['goals_against'],
                $row['goal_difference'],
                $row['points'],
                $row['is_champion']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public static function exportStandingsPdf($seasonInfo, $standings) {
        $title = 'Klasemen Akhir Musim';
        $subTitle = $seasonInfo['year_start'] . '/' . $seasonInfo['year_end'];
        
        $pdf = new EPL_PDF('P', 'mm', 'A4', $title, $subTitle);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        // Champion Info Banner
        if ($seasonInfo['champion_name']) {
            $pdf->SetFillColor(240, 244, 255);
            $pdf->SetDrawColor(30, 60, 114);
            $pdf->Rect(15, 40, 180, 18, 'DF');
            
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(30, 60, 114);
            $pdf->SetXY(20, 42);
            $pdf->Cell(170, 6, '🏆 JUARA MUSIM INI: ' . strtoupper($seasonInfo['champion_name']), 0, 1, 'L');
            
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetX(20);
            $pdf->Cell(170, 5, 'Selamat kepada ' . $seasonInfo['champion_name'] . ' atas gelar juara Premier League.', 0, 1, 'L');
            
            $pdf->SetY(64);
        } else {
            $pdf->SetY(40);
        }
        
        // Table Header
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(30, 60, 114);
        $pdf->SetTextColor(255, 255, 255);
        
        $cols = [
            'Pos' => 10,
            'Tim' => 60,
            'Main' => 15,
            'M' => 12,
            'S' => 12,
            'K' => 12,
            'GM' => 15,
            'GK' => 15,
            'SG' => 15,
            'Poin' => 14
        ];
        
        foreach ($cols as $name => $width) {
            $pdf->Cell($width, 8, $name, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Reset Text/Draw Color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 9);
        
        // Data Rows
        $fill = false;
        foreach ($standings as $row) {
            // Alternating backgrounds
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 247 : 255, $fill ? 250 : 255);
            
            // Highlights for specific zones
            if ($row['position'] == 1) {
                // Champion row
                $pdf->SetFillColor(212, 237, 218); // soft green
                $pdf->SetFont('Arial', 'B', 9);
            } elseif ($row['position'] <= 4) {
                // Champions League zone
                $pdf->SetFillColor(230, 245, 233);
                $pdf->SetFont('Arial', '', 9);
            } elseif ($row['position'] >= count($standings) - 2) {
                // Relegation zone
                $pdf->SetFillColor(248, 215, 218); // soft red
                $pdf->SetFont('Arial', '', 9);
            } else {
                $pdf->SetFont('Arial', '', 9);
            }
            
            $pdf->Cell($cols['Pos'], 7, $row['position'], 1, 0, 'C', true);
            $pdf->Cell($cols['Tim'], 7, ' ' . $row['team_name'] . ($row['is_champion'] === 'Ya' ? ' (C) 🏆' : ''), 1, 0, 'L', true);
            $pdf->Cell($cols['Main'], 7, $row['played'], 1, 0, 'C', true);
            $pdf->Cell($cols['M'], 7, $row['won'], 1, 0, 'C', true);
            $pdf->Cell($cols['S'], 7, $row['drawn'], 1, 0, 'C', true);
            $pdf->Cell($cols['K'], 7, $row['lost'], 1, 0, 'C', true);
            $pdf->Cell($cols['GM'], 7, $row['goals_for'], 1, 0, 'C', true);
            $pdf->Cell($cols['GK'], 7, $row['goals_against'], 1, 0, 'C', true);
            $pdf->Cell($cols['SG'], 7, $row['goal_difference'], 1, 0, 'C', true);
            
            // Bold points
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($cols['Poin'], 7, $row['points'], 1, 0, 'C', true);
            
            $pdf->Ln();
            $fill = !$fill;
        }
        
        // Legends
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, '* (C) Champion / Juara  |  Pos 1-4: Kualifikasi Liga Champions (Hijau)  |  Pos 18-20: Degradasi (Merah)', 0, 1, 'L');
        
        $pdf->renderSignature();
        $pdf->Output('I', $title . '_' . $subTitle . '.pdf');
        exit;
    }

    // ==========================================
    // 2. LAPORAN STATISTIK HISTORIS TIM
    // ==========================================
    
    public static function exportTeamStatsCsv($teamInfo, $stats) {
        $filename = 'statistik_historis_' . strtolower(str_replace(' ', '_', $teamInfo['name'])) . '.csv';
        $output = self::startCsvStream($filename);
        
        // Write profil
        fputcsv($output, ['Profil Tim: ' . $teamInfo['name']]);
        fputcsv($output, ['Kota Asal: ' . ($teamInfo['city'] ?: '-')]);
        fputcsv($output, ['Tahun Didirikan: ' . ($teamInfo['founded_year'] ?: '-')]);
        fputcsv($output, ['Stadion: ' . ($teamInfo['stadium'] ?: '-')]);
        fputcsv($output, []); // empty row
        
        // Write headers
        fputcsv($output, ['Musim', 'Posisi', 'Main', 'Menang', 'Seri', 'Kalah', 'Gol Masuk', 'Gol Kemasukan', 'Selisih Gol', 'Poin', 'Juara']);
        
        // Write stats rows
        foreach ($stats as $row) {
            fputcsv($output, [
                $row['year_start'] . '/' . $row['year_end'],
                $row['position'],
                $row['played'],
                $row['won'],
                $row['drawn'],
                $row['lost'],
                $row['goals_for'],
                $row['goals_against'],
                $row['goal_difference'],
                $row['points'],
                $row['is_champion']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public static function exportTeamStatsPdf($teamInfo, $stats) {
        $title = 'Profil & Statistik Historis Tim';
        $subTitle = $teamInfo['name'];
        
        $pdf = new EPL_PDF('P', 'mm', 'A4', $title, $subTitle);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        // PROFILE METRICS
        $totalSeasons = count($stats);
        $totalPlayed = array_sum(array_column($stats, 'played'));
        $totalWon = array_sum(array_column($stats, 'won'));
        $totalPoints = array_sum(array_column($stats, 'points'));
        $championships = count(array_filter($stats, function($stat) {
            return $stat['is_champion'] === 'Ya';
        }));
        $winRate = $totalPlayed > 0 ? ($totalWon / $totalPlayed) * 100 : 0;
        $avgPoints = $totalSeasons > 0 ? $totalPoints / $totalSeasons : 0;
        
        // Top profile cards
        $pdf->SetFillColor(240, 244, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(15, 38, 180, 26, 'DF');
        
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetXY(20, 40);
        $pdf->Cell(80, 5, 'INFORMASI KLUB', 0, 0, 'L');
        $pdf->Cell(100, 5, 'METRIK KUMULATIF PREMIER LEAGUE', 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetX(20);
        $pdf->Cell(80, 4, 'Kota Asal: ' . ($teamInfo['city'] ?: '-'), 0, 0, 'L');
        $pdf->Cell(50, 4, 'Total Musim: ' . $totalSeasons, 0, 0, 'L');
        $pdf->Cell(50, 4, 'Gelar Juara: ' . $championships . 'x 🏆', 0, 1, 'L');
        
        $pdf->SetX(20);
        $pdf->Cell(80, 4, 'Didirikan: ' . ($teamInfo['founded_year'] ?: '-'), 0, 0, 'L');
        $pdf->Cell(50, 4, 'Total Menang: ' . $totalWon . ' dari ' . $totalPlayed . ' match', 0, 0, 'L');
        $pdf->Cell(50, 4, 'Rasio Menang: ' . number_format($winRate, 1) . '%', 0, 1, 'L');
        
        $pdf->SetX(20);
        $pdf->Cell(80, 4, 'Stadion: ' . ($teamInfo['stadium'] ?: '-'), 0, 0, 'L');
        $pdf->Cell(50, 4, 'Total Poin: ' . $totalPoints, 0, 0, 'L');
        $pdf->Cell(50, 4, 'Rata-rata Poin/Musim: ' . number_format($avgPoints, 1), 0, 1, 'L');
        
        // Table Title
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetY(70);
        $pdf->SetX(15);
        $pdf->Cell(180, 6, 'RIWAYAT PER MUSIM (PREMIER LEAGUE)', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Table Header
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(30, 60, 114);
        $pdf->SetTextColor(255, 255, 255);
        
        $cols = [
            'Musim' => 25,
            'Posisi' => 15,
            'Main' => 15,
            'Menang' => 17,
            'Seri' => 15,
            'Kalah' => 15,
            'Gol +' => 17,
            'Gol -' => 17,
            'Sel Gol' => 17,
            'Poin' => 27
        ];
        
        foreach ($cols as $name => $width) {
            $pdf->Cell($width, 8, $name, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Reset Text/Draw Color
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 9);
        
        // Data Rows
        $fill = false;
        foreach ($stats as $row) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 247 : 255, $fill ? 250 : 255);
            
            // Soft gold color for champion season rows
            if ($row['is_champion'] === 'Ya') {
                $pdf->SetFillColor(254, 243, 199); // soft gold
                $pdf->SetFont('Arial', 'B', 9);
            } else {
                $pdf->SetFont('Arial', '', 9);
            }
            
            $pdf->Cell($cols['Musim'], 7, $row['year_start'] . '/' . $row['year_end'], 1, 0, 'C', true);
            $pdf->Cell($cols['Posisi'], 7, $row['position'] . ($row['is_champion'] === 'Ya' ? ' 🏆' : ''), 1, 0, 'C', true);
            $pdf->Cell($cols['Main'], 7, $row['played'], 1, 0, 'C', true);
            $pdf->Cell($cols['Menang'], 7, $row['won'], 1, 0, 'C', true);
            $pdf->Cell($cols['Seri'], 7, $row['drawn'], 1, 0, 'C', true);
            $pdf->Cell($cols['Kalah'], 7, $row['lost'], 1, 0, 'C', true);
            $pdf->Cell($cols['Gol +'], 7, $row['goals_for'], 1, 0, 'C', true);
            $pdf->Cell($cols['Gol -'], 7, $row['goals_against'], 1, 0, 'C', true);
            $pdf->Cell($cols['Sel Gol'], 7, $row['goal_difference'], 1, 0, 'C', true);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($cols['Poin'], 7, $row['points'], 1, 0, 'C', true);
            
            $pdf->Ln();
            $fill = !$fill;
        }
        
        $pdf->renderSignature();
        $pdf->Output('I', $title . '_' . str_replace(' ', '_', $teamInfo['name']) . '.pdf');
        exit;
    }

    // ==========================================
    // 3. LAPORAN EVALUASI & PERFORMA MODEL
    // ==========================================
    
    public static function exportEvaluationCsv($evaluationResult, $history) {
        $filename = 'evaluasi_model_naivebayes.csv';
        $output = self::startCsvStream($filename);
        
        // Write info
        fputcsv($output, ['Laporan Hasil Evaluasi Model Naive Bayes']);
        fputcsv($output, ['Tanggal Evaluasi: ' . date('d-m-Y H:i')]);
        fputcsv($output, []); // empty
        
        // Write metrics
        fputcsv($output, ['Metrik Evaluasi']);
        fputcsv($output, ['Akurasi', ($evaluationResult['accuracy'] * 100) . '%']);
        fputcsv($output, ['Presisi', ($evaluationResult['precision'] * 100) . '%']);
        fputcsv($output, ['Recall', ($evaluationResult['recall'] * 100) . '%']);
        fputcsv($output, ['F1-Score', ($evaluationResult['f1_score'] * 100) . '%']);
        fputcsv($output, []);
        
        // Write Confusion Matrix
        fputcsv($output, ['Confusion Matrix']);
        fputcsv($output, ['', 'Prediksi Juara', 'Prediksi Tidak Juara']);
        fputcsv($output, ['Aktual Juara', 'TP: ' . $evaluationResult['confusion_matrix']['true_positive'], 'FN: ' . $evaluationResult['confusion_matrix']['false_negative']]);
        fputcsv($output, ['Aktual Tidak Juara', 'FP: ' . $evaluationResult['confusion_matrix']['false_positive'], 'TN: ' . $evaluationResult['confusion_matrix']['true_negative']]);
        fputcsv($output, []);
        
        // Write Details header
        fputcsv($output, ['Detail Hasil Prediksi Testing']);
        fputcsv($output, ['Season', 'Tim', 'Aktual', 'Prediksi', 'Peluang Juara', 'Peluang Tidak Juara', 'Status']);
        foreach ($evaluationResult['predictions'] as $pred) {
            fputcsv($output, [
                $pred['season'] . '/' . ($pred['season'] + 1),
                $pred['team'],
                $pred['actual'],
                $pred['predicted'],
                ($pred['prob_juara'] * 100) . '%',
                ($pred['prob_not_juara'] * 100) . '%',
                $pred['actual'] === $pred['predicted'] ? 'Benar' : 'Salah'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public static function exportEvaluationPdf($evaluationResult, $history) {
        $title = 'Evaluasi & Performa Model';
        $subTitle = 'Gaussian Naive Bayes';
        
        $pdf = new EPL_PDF('P', 'mm', 'A4', $title, $subTitle);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        // METRICS CARDS
        $pdf->SetFillColor(240, 244, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(15, 38, 180, 25, 'DF');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetXY(20, 40);
        $pdf->Cell(45, 5, 'AKURASI', 0, 0, 'C');
        $pdf->Cell(45, 5, 'PRESISI', 0, 0, 'C');
        $pdf->Cell(45, 5, 'RECALL', 0, 0, 'C');
        $pdf->Cell(45, 5, 'F1-SCORE', 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetX(20);
        $pdf->Cell(45, 10, number_format($evaluationResult['accuracy'] * 100, 1) . '%', 0, 0, 'C');
        $pdf->Cell(45, 10, number_format($evaluationResult['precision'] * 100, 1) . '%', 0, 0, 'C');
        $pdf->Cell(45, 10, number_format($evaluationResult['recall'] * 100, 1) . '%', 0, 0, 'C');
        $pdf->Cell(45, 10, number_format($evaluationResult['f1_score'] * 100, 1) . '%', 0, 1, 'C');
        
        // CONFUSION MATRIX
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetY(70);
        $pdf->Cell(180, 6, 'CONFUSION MATRIX', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetX(15);
        $pdf->Cell(45, 8, '', 1, 0, 'C', true);
        $pdf->Cell(67, 8, 'Prediksi Juara', 1, 0, 'C', true);
        $pdf->Cell(68, 8, 'Prediksi Tidak Juara', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 8, 'Aktual Juara', 1, 0, 'C', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(67, 8, 'True Positive (TP): ' . $evaluationResult['confusion_matrix']['true_positive'], 1, 0, 'C');
        $pdf->Cell(68, 8, 'False Negative (FN): ' . $evaluationResult['confusion_matrix']['false_negative'], 1, 1, 'C');
        
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 8, 'Aktual Tidak Juara', 1, 0, 'C', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(67, 8, 'False Positive (FP): ' . $evaluationResult['confusion_matrix']['false_positive'], 1, 0, 'C');
        $pdf->Cell(68, 8, 'True Negative (TN): ' . $evaluationResult['confusion_matrix']['true_negative'], 1, 1, 'C');
        
        // TESTING DETAILS TABLE
        $pdf->SetTextColor(30, 60, 114);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Ln(8);
        $pdf->Cell(180, 6, 'DETAIL HASIL DATA UJI (TESTING DATA)', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Header
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(30, 60, 114);
        $pdf->SetTextColor(255, 255, 255);
        
        $cols = [
            'Season' => 20,
            'Nama Tim' => 55,
            'Aktual' => 25,
            'Prediksi' => 25,
            'P. Juara' => 23,
            'P. Tdk Juara' => 23,
            'Status' => 19
        ];
        
        $pdf->SetX(15);
        foreach ($cols as $name => $width) {
            $pdf->Cell($width, 8, $name, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Reset styles
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetFont('Arial', '', 8.5);
        
        $fill = false;
        foreach ($evaluationResult['predictions'] as $pred) {
            $isCorrect = $pred['actual'] === $pred['predicted'];
            
            $pdf->SetFillColor($isCorrect ? 245 : 253, $isCorrect ? 250 : 242, $isCorrect ? 245 : 242); // soft green background if correct, soft red if wrong
            
            $pdf->SetX(15);
            $pdf->Cell($cols['Season'], 6.5, $pred['season'] . '/' . ($pred['season'] + 1), 1, 0, 'C', true);
            $pdf->Cell($cols['Nama Tim'], 6.5, ' ' . $pred['team'], 1, 0, 'L', true);
            $pdf->Cell($cols['Aktual'], 6.5, $pred['actual'], 1, 0, 'C', true);
            $pdf->Cell($cols['Prediksi'], 6.5, $pred['predicted'], 1, 0, 'C', true);
            $pdf->Cell($cols['P. Juara'], 6.5, number_format($pred['prob_juara'] * 100, 1) . '%', 1, 0, 'C', true);
            $pdf->Cell($cols['P. Tdk Juara'], 6.5, number_format($pred['prob_not_juara'] * 100, 1) . '%', 1, 0, 'C', true);
            
            if ($isCorrect) {
                $pdf->SetTextColor(40, 167, 69);
                $pdf->SetFont('Arial', 'B', 8.5);
                $pdf->Cell($cols['Status'], 6.5, 'BENAR', 1, 0, 'C', true);
            } else {
                $pdf->SetTextColor(220, 53, 69);
                $pdf->SetFont('Arial', 'B', 8.5);
                $pdf->Cell($cols['Status'], 6.5, 'SALAH', 1, 0, 'C', true);
            }
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->Ln();
        }
        
        $pdf->renderSignature();
        $pdf->Output('I', 'Evaluasi_Model_Naive_Bayes.pdf');
        exit;
    }

    // ==========================================
    // 4. LAPORAN HASIL PREDIKSI (PREDICTION)
    // ==========================================
    
    public static function exportPredictionCsv($mode, $data) {
        $filename = 'hasil_prediksi_peluang_juara.csv';
        $output = self::startCsvStream($filename);
        
        fputcsv($output, ['Laporan Hasil Prediksi Peluang Juara EPL']);
        fputcsv($output, ['Mode Input: ' . ($mode === 'manual' ? 'Manual Form' : 'Unggah CSV')]);
        fputcsv($output, ['Tanggal Analisis: ' . date('d-m-Y H:i')]);
        fputcsv($output, []);
        
        if ($mode === 'manual') {
            fputcsv($output, ['Atribut Input', 'Nilai']);
            fputcsv($output, ['Kemenangan', $data['input']['won']]);
            fputcsv($output, ['Seri', $data['input']['drawn']]);
            fputcsv($output, ['Kekalahan', $data['input']['lost']]);
            fputcsv($output, ['Total Gol Dicetak', $data['input']['goals_for']]);
            fputcsv($output, ['Total Gol Kemasukan', $data['input']['goals_against']]);
            fputcsv($output, ['Selisih Gol', $data['input']['goal_diff']]);
            fputcsv($output, ['Total Poin', $data['input']['points']]);
            fputcsv($output, ['Rasio Kemenangan', $data['input']['win_rate']]);
            fputcsv($output, []);
            
            fputcsv($output, ['Prediksi Hasil Akhir', $data['result']['predicted_class']]);
            fputcsv($output, ['Peluang Juara', ($data['result']['probabilities']['Juara'] * 100) . '%']);
            fputcsv($output, ['Peluang Tidak Juara', ($data['result']['probabilities']['Tidak Juara'] * 100) . '%']);
        } else {
            fputcsv($output, ['Musim', 'Nama Tim', 'Posisi', 'Prediksi', 'Peluang Juara', 'Peluang Tidak Juara']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['season'],
                    $row['team_name'],
                    $row['position'],
                    $row['predicted_class'],
                    ($row['prob_juara'] * 100) . '%',
                    ($row['prob_not_juara'] * 100) . '%'
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    public static function exportPredictionPdf($mode, $data) {
        $title = 'Prediksi Peluang Juara';
        $subTitle = $mode === 'manual' ? 'Input Manual' : 'Analisis Batch (CSV)';
        
        $pdf = new EPL_PDF('P', 'mm', 'A4', $title, $subTitle);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        if ($mode === 'manual') {
            // Manual entry layout
            $pdf->SetTextColor(30, 60, 114);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(180, 6, 'STATISTIK DATA MASUKAN (INPUT DATA)', 0, 1, 'L');
            $pdf->Ln(2);
            
            // Render 2 columns for inputs
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 9.5);
            $pdf->SetFillColor(245, 247, 250);
            
            $inputs = [
                'Kemenangan' => $data['input']['won'] . ' pertandingan',
                'Total Gol Dicetak' => $data['input']['goals_for'] . ' gol',
                'Seri' => $data['input']['drawn'] . ' pertandingan',
                'Total Gol Kemasukan' => $data['input']['goals_against'] . ' gol',
                'Kekalahan' => $data['input']['lost'] . ' pertandingan',
                'Selisih Gol (GD)' => $data['input']['goal_diff'] . ' gol',
                'Total Poin Akhir' => $data['input']['points'] . ' poin',
                'Rasio Kemenangan' => number_format($data['input']['win_rate'] * 100, 1) . '%'
            ];
            
            $pdf->SetDrawColor(220, 220, 220);
            $fill = false;
            $xStart = 15;
            $yStart = $pdf->GetY();
            $index = 0;
            
            foreach ($inputs as $label => $val) {
                // Layout 2 columns side by side
                $col = $index % 2;
                $row = floor($index / 2);
                
                $x = $xStart + ($col * 90);
                $y = $yStart + ($row * 8);
                
                $pdf->SetXY($x, $y);
                $pdf->Cell(50, 8, ' ' . $label, 1, 0, 'L', true);
                $pdf->Cell(40, 8, ' ' . $val, 1, 0, 'C');
                
                $index++;
            }
            
            // PREDICTION RESULT CARD
            $pdf->SetTextColor(30, 60, 114);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetXY(15, $yStart + 36);
            $pdf->Cell(180, 6, 'HASIL INFERENSI KLASEMEN (PREDICTION RESULT)', 0, 1, 'L');
            $pdf->Ln(2);
            
            $predictedClass = $data['result']['predicted_class'];
            $probJuara = $data['result']['probabilities']['Juara'] * 100;
            $probNotJuara = $data['result']['probabilities']['Tidak Juara'] * 100;
            
            // Result box
            $pdf->SetDrawColor(30, 60, 114);
            $pdf->SetFillColor($predictedClass === 'Juara' ? 240 : 255, $predictedClass === 'Juara' ? 248 : 240, $predictedClass === 'Juara' ? 240 : 240); // green vs red tint
            $pdf->Rect(15, $pdf->GetY(), 180, 22, 'DF');
            
            $pdf->SetXY(20, $pdf->GetY() + 3);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(170, 5, 'KLASIFIKASI KELAS PREDIKSI:', 0, 1, 'L');
            
            $pdf->SetFont('Arial', 'B', 18);
            $pdf->SetTextColor($predictedClass === 'Juara' ? 40 : 220, $predictedClass === 'Juara' ? 167 : 53, $predictedClass === 'Juara' ? 69 : 69);
            $pdf->SetX(20);
            $pdf->Cell(170, 10, strtoupper($predictedClass) . ($predictedClass === 'Juara' ? ' 🏆' : ' ❌'), 0, 1, 'L');
            
            // Probability bars
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetY($pdf->GetY() + 8);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(180, 5, 'Peluang Menjadi Juara:', 0, 1, 'L');
            $pdf->Ln(1);
            
            // Draw empty bar
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Rect(15, $pdf->GetY(), 180, 7, 'F');
            // Draw filled bar
            $pdf->SetFillColor(30, 60, 114);
            $pdf->Rect(15, $pdf->GetY(), 180 * ($probJuara / 100), 7, 'F');
            
            $pdf->SetY($pdf->GetY() + 8);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(180, 5, number_format($probJuara, 2) . '% Peluang Juara', 0, 1, 'R');
            
            // Probability bar 2
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(180, 5, 'Peluang Tidak Menjadi Juara:', 0, 1, 'L');
            $pdf->Ln(1);
            
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Rect(15, $pdf->GetY(), 180, 7, 'F');
            $pdf->SetFillColor(220, 53, 69);
            $pdf->Rect(15, $pdf->GetY(), 180 * ($probNotJuara / 100), 7, 'F');
            
            $pdf->SetY($pdf->GetY() + 8);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(180, 5, number_format($probNotJuara, 2) . '% Peluang Tidak Juara', 0, 1, 'R');
            
        } else {
            // Batch CSV prediction layout
            $pdf->SetTextColor(30, 60, 114);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(180, 6, 'DAFTAR PREDIKSI PELUANG JUARA TIM', 0, 1, 'L');
            $pdf->Ln(2);
            
            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(30, 60, 114);
            $pdf->SetTextColor(255, 255, 255);
            
            $cols = [
                'Musim' => 20,
                'Nama Tim' => 60,
                'Posisi' => 20,
                'Hasil Prediksi' => 35,
                'Peluang Juara' => 23,
                'P. Tidak Juara' => 22
            ];
            
            foreach ($cols as $name => $width) {
                $pdf->Cell($width, 8, $name, 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Reset styles
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->SetFont('Arial', '', 9);
            
            $fill = false;
            foreach ($data as $row) {
                $pdf->SetFillColor($fill ? 245 : 255, $fill ? 247 : 255, $fill ? 250 : 255);
                
                if ($row['predicted_class'] === 'Juara') {
                    $pdf->SetFillColor(212, 237, 218); // soft green for champion prediction
                    $pdf->SetFont('Arial', 'B', 9);
                } else {
                    $pdf->SetFont('Arial', '', 9);
                }
                
                $pdf->Cell($cols['Musim'], 7, $row['season'], 1, 0, 'C', true);
                $pdf->Cell($cols['Nama Tim'], 7, ' ' . $row['team_name'], 1, 0, 'L', true);
                $pdf->Cell($cols['Posisi'], 7, $row['position'], 1, 0, 'C', true);
                $pdf->Cell($cols['Hasil Prediksi'], 7, $row['predicted_class'] . ($row['predicted_class'] === 'Juara' ? ' 🏆' : ''), 1, 0, 'C', true);
                $pdf->Cell($cols['Peluang Juara'], 7, number_format($row['prob_juara'] * 100, 1) . '%', 1, 0, 'C', true);
                $pdf->Cell($cols['P. Tidak Juara'], 7, number_format($row['prob_not_juara'] * 100, 1) . '%', 1, 0, 'C', true);
                
                $pdf->Ln();
                $fill = !$fill;
            }
        }
        
        $pdf->renderSignature();
        $pdf->Output('I', 'Hasil_Prediksi_Juara.pdf');
        exit;
    }
}
