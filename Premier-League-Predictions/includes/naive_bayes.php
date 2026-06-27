<?php
/**
 * Naïve Bayes Classifier Implementation
 * Premier League Predictions
 * 
 * Implementasi algoritma Naïve Bayes untuk prediksi juara EPL
 */

class NaiveBayesClassifier {
    private $pdo;
    private $probabilities = [];
    private $classProbabilities = [];
    private $trainingSeasonStart = null;
    private $trainingSeasonEnd = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Train model menggunakan data dari database
     */
    public function train($trainingSeasonStart, $trainingSeasonEnd) {
        $this->probabilities = [];
        $this->classProbabilities = [];
        $this->trainingSeasonStart = $trainingSeasonStart;
        $this->trainingSeasonEnd = $trainingSeasonEnd;

        // Ambil data training dari tabel dataset
        $stmt = $this->pdo->prepare("
            SELECT d.*, s.year_start 
            FROM dataset d
            JOIN season s ON d.season_id = s.season_id
            WHERE s.year_start BETWEEN ? AND ?
            AND d.split_type = 'Training'
        ");
        $stmt->execute([$trainingSeasonStart, $trainingSeasonEnd]);
        $trainingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($trainingData)) {
            throw new Exception("Tidak ada data training ditemukan untuk periode $trainingSeasonStart-$trainingSeasonEnd");
        }
        
        // Hitung probabilitas prior untuk setiap kelas (Juara/Tidak Juara)
        $totalSamples = count($trainingData);
        $classCounts = ['Juara' => 0, 'Tidak Juara' => 0];
        
        foreach ($trainingData as $row) {
            $classCounts[$row['label']]++;
        }

        if ($classCounts['Juara'] === 0 || $classCounts['Tidak Juara'] === 0) {
            throw new Exception("Data training harus memiliki kedua kelas: Juara dan Tidak Juara");
        }
        
        $this->classProbabilities = [
            'Juara' => $classCounts['Juara'] / $totalSamples,
            'Tidak Juara' => $classCounts['Tidak Juara'] / $totalSamples
        ];
        
        // Hitung probabilitas kondisional untuk setiap atribut
        $attributes = ['won', 'drawn', 'lost', 'goals_for', 'goals_against', 'goal_diff', 'points', 'win_rate'];
        
        foreach ($attributes as $attr) {
            $this->probabilities[$attr] = [
                'Juara' => $this->calculateAttributeProbability($trainingData, $attr, 'Juara'),
                'Tidak Juara' => $this->calculateAttributeProbability($trainingData, $attr, 'Tidak Juara')
            ];
        }
        
        return [
            'training_samples' => $totalSamples,
            'class_distribution' => $classCounts,
            'class_probabilities' => $this->classProbabilities
        ];
    }
    
    /**
     * Hitung probabilitas atribut menggunakan Gaussian Naive Bayes
     */
    private function calculateAttributeProbability($data, $attribute, $class) {
        // Filter data berdasarkan kelas
        $classData = array_filter($data, function($row) use ($class) {
            return $row['label'] === $class;
        });
        
        $values = array_column($classData, $attribute);
        $values = array_map('floatval', $values);
        
        if (count($values) === 0) {
            return ['mean' => 0, 'std' => 1];
        }
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        $std = sqrt($variance);
        
        // Handle std = 0
        if ($std == 0) {
            $std = 1;
        }
        
        return [
            'mean' => $mean,
            'std' => $std
        ];
    }
    
    /**
     * Hitung probabilitas Gaussian
     */
    private function gaussianProbability($x, $mean, $std) {
        $exponent = exp(-pow($x - $mean, 2) / (2 * pow($std, 2)));
        return (1 / (sqrt(2 * M_PI) * $std)) * $exponent;
    }
    
    /**
     * Prediksi kelas untuk data baru
     */
    public function predict($data) {
        if (empty($this->classProbabilities) || empty($this->probabilities)) {
            throw new Exception("Model belum dilatih. Jalankan train() sebelum predict().");
        }

        $scores = [
            'Juara' => log($this->classProbabilities['Juara']),
            'Tidak Juara' => log($this->classProbabilities['Tidak Juara'])
        ];
        
        $attributes = ['won', 'drawn', 'lost', 'goals_for', 'goals_against', 'goal_diff', 'points', 'win_rate'];
        
        foreach ($attributes as $attr) {
            if (!isset($data[$attr])) {
                continue;
            }
            
            foreach (['Juara', 'Tidak Juara'] as $class) {
                $params = $this->probabilities[$attr][$class];
                $prob = $this->gaussianProbability($data[$attr], $params['mean'], $params['std']);
                $scores[$class] += log($prob + 1e-10); // Add small value to avoid log(0)
            }
        }
        
        // Convert log scores back to probabilities
        $maxScore = max($scores);
        $expScores = array_map(function($score) use ($maxScore) {
            return exp($score - $maxScore);
        }, $scores);
        
        $sumExpScores = array_sum($expScores);
        $probabilities = array_map(function($expScore) use ($sumExpScores) {
            return $expScore / $sumExpScores;
        }, $expScores);
        
        // Determine predicted class
        $predictedClass = $probabilities['Juara'] > $probabilities['Tidak Juara'] ? 'Juara' : 'Tidak Juara';
        
        return [
            'predicted_class' => $predictedClass,
            'probabilities' => $probabilities,
            'raw_scores' => $scores
        ];
    }
    
    /**
     * Evaluasi model menggunakan data testing
     */
    public function evaluate($testingSeasonStart, $testingSeasonEnd = null) {
        if ($testingSeasonEnd === null) {
            $testingSeasonEnd = $testingSeasonStart;
        }

        // Ambil data testing
        $stmt = $this->pdo->prepare("
            SELECT d.*, t.name as team_name, s.year_start
            FROM dataset d
            JOIN team t ON d.team_id = t.team_id
            JOIN season s ON d.season_id = s.season_id
            WHERE s.year_start BETWEEN ? AND ?
            AND d.split_type = 'Testing'
            ORDER BY s.year_start ASC, d.dataset_id ASC
        ");
        $stmt->execute([$testingSeasonStart, $testingSeasonEnd]);
        $testingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($testingData)) {
            throw new Exception("Tidak ada data testing ditemukan untuk periode $testingSeasonStart-$testingSeasonEnd");
        }
        
        $predictions = [];
        $truePositive = 0;
        $trueNegative = 0;
        $falsePositive = 0;
        $falseNegative = 0;
        
        foreach ($testingData as $row) {
            $data = [
                'won' => $row['won'],
                'drawn' => $row['drawn'],
                'lost' => $row['lost'],
                'goals_for' => $row['goals_for'],
                'goals_against' => $row['goals_against'],
                'goal_diff' => $row['goal_diff'],
                'points' => $row['points'],
                'win_rate' => $row['win_rate']
            ];
            
            $result = $this->predict($data);
            $actual = $row['label'];
            $predicted = $result['predicted_class'];
            
            // Simpan hasil prediksi ke database
            $this->savePrediction($row['season_id'], $row['team_id'], $result, $actual);
            
            // Hitung confusion matrix
            if ($actual === 'Juara' && $predicted === 'Juara') {
                $truePositive++;
            } elseif ($actual === 'Tidak Juara' && $predicted === 'Tidak Juara') {
                $trueNegative++;
            } elseif ($actual === 'Tidak Juara' && $predicted === 'Juara') {
                $falsePositive++;
            } elseif ($actual === 'Juara' && $predicted === 'Tidak Juara') {
                $falseNegative++;
            }
            
            $predictions[] = [
                'season' => $row['year_start'],
                'team' => $row['team_name'],
                'actual' => $actual,
                'predicted' => $predicted,
                'prob_juara' => $result['probabilities']['Juara'],
                'prob_not_juara' => $result['probabilities']['Tidak Juara']
            ];
        }
        
        // Hitung metrik evaluasi
        $totalTestingData = count($testingData);
        $accuracy = $totalTestingData > 0 ? ($truePositive + $trueNegative) / $totalTestingData : 0;
        $precisionDenominator = $truePositive + $falsePositive;
        $recallDenominator = $truePositive + $falseNegative;
        $precision = $precisionDenominator > 0 ? $truePositive / $precisionDenominator : 0;
        $recall = $recallDenominator > 0 ? $truePositive / $recallDenominator : 0;
        $f1Score = ($precision + $recall) > 0 ? 2 * (($precision * $recall) / ($precision + $recall)) : 0;
        
        // Simpan performa model
        $this->saveModelPerformance($testingSeasonStart, $testingSeasonEnd, $accuracy, $precision, $recall, $f1Score,
                                     $truePositive, $trueNegative, $falsePositive, $falseNegative);
        
        return [
            'testing_season_start' => $testingSeasonStart,
            'testing_season_end' => $testingSeasonEnd,
            'testing_samples' => $totalTestingData,
            'accuracy' => $accuracy,
            'precision' => $precision,
            'recall' => $recall,
            'f1_score' => $f1Score,
            'confusion_matrix' => [
                'true_positive' => $truePositive,
                'true_negative' => $trueNegative,
                'false_positive' => $falsePositive,
                'false_negative' => $falseNegative
            ],
            'predictions' => $predictions
        ];
    }
    
    /**
     * Simpan hasil prediksi ke database
     */
    private function savePrediction($seasonId, $teamId, $result, $actual) {
        try {
            $this->pdo->beginTransaction();

            $deleteStmt = $this->pdo->prepare("
                DELETE FROM prediction_result
                WHERE season_id = ? AND team_id = ?
            ");
            $deleteStmt->execute([$seasonId, $teamId]);

            $stmt = $this->pdo->prepare("
                INSERT INTO prediction_result 
                (season_id, team_id, champion_probability, not_champion_probability, predicted_label, actual_label, is_correct)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $isCorrect = ($result['predicted_class'] === $actual) ? 1 : 0;
            
            $stmt->execute([
                $seasonId,
                $teamId,
                $result['probabilities']['Juara'],
                $result['probabilities']['Tidak Juara'],
                $result['predicted_class'],
                $actual,
                $isCorrect
            ]);

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Save prediction error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Simpan performa model ke database
     */
    private function saveModelPerformance($testingSeasonStart, $testingSeasonEnd, $accuracy, $precision, $recall, $f1Score,
                                          $tp, $tn, $fp, $fn) {
        $stmt = $this->pdo->prepare("
            INSERT INTO model_performance 
            (`model_name`, `training_season_start`, `training_season_end`, `testing_season`, `accuracy`, `precision`, `recall`, `f1_score`, `true_positive`, `true_negative`, `false_positive`, `false_negative`)
            VALUES ('Naive Bayes', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $trainingStart = $this->trainingSeasonStart;
        $trainingEnd = $this->trainingSeasonEnd;
        $testingSeasonForHistory = $testingSeasonEnd;
        
        $stmt->execute([
            $trainingStart,
            $trainingEnd,
            $testingSeasonForHistory,
            $accuracy,
            $precision,
            $recall,
            $f1Score,
            $tp,
            $tn,
            $fp,
            $fn
        ]);
    }
    
    /**
     * Siapkan dataset dari team_season untuk training/testing
     */
    public function prepareDataset($splitRatio = 0.8) {
        if ($splitRatio <= 0 || $splitRatio >= 1) {
            throw new Exception("Split ratio harus berada di antara 0 dan 1");
        }

        // Hapus dataset lama
        $this->pdo->exec("TRUNCATE TABLE dataset");
        
        // Ambil semua data team_season
        $stmt = $this->pdo->query("
            SELECT ts.*, s.year_start
            FROM team_season ts
            JOIN season s ON ts.season_id = s.season_id
            ORDER BY s.year_start ASC, ts.position ASC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            throw new Exception("Data team_season masih kosong. Jalankan import data terlebih dahulu.");
        }
        
        // Group by season
        $seasons = [];
        foreach ($data as $row) {
            $seasons[$row['year_start']][] = $row;
        }
        
        // Split data: 80% training, 20% testing
        $seasonKeys = array_keys($seasons);
        $splitIndex = (int) floor(count($seasonKeys) * $splitRatio);

        if ($splitIndex < 1 || $splitIndex >= count($seasonKeys)) {
            throw new Exception("Jumlah season tidak cukup untuk pembagian training dan testing");
        }
        
        $trainingSeasons = array_slice($seasonKeys, 0, $splitIndex);
        $testingSeasons = array_slice($seasonKeys, $splitIndex);
        
        $insertStmt = $this->pdo->prepare("
            INSERT INTO dataset (season_id, team_id, won, drawn, lost, goals_for, goals_against, goal_diff, points, win_rate, label, split_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($seasons as $year => $teams) {
            $splitType = in_array($year, $trainingSeasons, true) ? 'Training' : 'Testing';
            
            foreach ($teams as $team) {
                $label = ($team['is_champion'] === 'Ya') ? 'Juara' : 'Tidak Juara';
                $winRate = $team['played'] > 0 ? $team['won'] / $team['played'] : 0;
                
                $insertStmt->execute([
                    $team['season_id'],
                    $team['team_id'],
                    $team['won'],
                    $team['drawn'],
                    $team['lost'],
                    $team['goals_for'],
                    $team['goals_against'],
                    $team['goal_difference'],
                    $team['points'],
                    $winRate,
                    $label,
                    $splitType
                ]);
            }
        }
        
        return [
            'total_seasons' => count($seasons),
            'training_seasons' => count($trainingSeasons),
            'testing_seasons' => count($testingSeasons),
            'training_season_start' => min($trainingSeasons),
            'training_season_end' => max($trainingSeasons),
            'testing_season_start' => min($testingSeasons),
            'testing_season_end' => max($testingSeasons),
            'training_season_list' => $trainingSeasons,
            'testing_season_list' => $testingSeasons,
            'total_records' => count($data)
        ];
    }
}
