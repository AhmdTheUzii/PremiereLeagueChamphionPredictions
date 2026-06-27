<?php
/**
 * Setup Database - Execute SQL Schema
 * Premier League Predictions - Naive Bayes
 * 
 * File ini akan:
 * 1. Membaca file epl_schema.sql
 * 2. Mengeksekusi SQL untuk membuat database dan tabel
 */

// Konfigurasi Database
$host = 'localhost';

// Path file SQL
$sqlFile = __DIR__ . '/epl_schema.sql';

try {
    // Koneksi ke MySQL (tanpa database spesifik)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== SETUP DATABASE PREMIER LEAGUE ===\n\n";
    
    // Cek apakah file SQL ada
    if (!file_exists($sqlFile)) {
        die("Error: File SQL tidak ditemukan di: $sqlFile\n");
    }
    
    // Drop database jika sudah ada
    echo "1. Drop database yang sudah ada (jika ada)...\n";
    $pdo->exec("DROP DATABASE IF EXISTS epl_naivebayes");
    echo "   Database lama berhasil dihapus\n\n";
    
    echo "2. Membaca file SQL schema...\n";
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        die("Error: Tidak dapat membaca file SQL\n");
    }
    
    echo "   File SQL berhasil dibaca\n\n";
    
    // Eksekusi SQL
    echo "3. Mengeksekusi SQL schema...\n";
    
    // Baca file SQL per baris
    $lines = file($sqlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $currentStatement = '';
    $executedCount = 0;
    $databaseCreated = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments dan empty lines
        if (empty($line) || 
            strpos($line, '--') === 0 || 
            strpos($line, '/*') === 0 ||
            strpos($line, '*/') === 0 ||
            strpos($line, 'SET') === 0 ||
            strpos($line, 'START TRANSACTION') === 0 ||
            strpos($line, 'COMMIT') === 0 ||
            strpos($line, '/*!40101') === 0) {
            continue;
        }
        
        // Skip USE statement
        if (stripos($line, 'USE ') === 0) {
            continue;
        }
        
        // Tambahkan line ke current statement
        $currentStatement .= $line . ' ';
        
        // Jika line berakhir dengan titik koma, eksekusi statement
        if (strpos($line, ';') !== false) {
            $currentStatement = trim($currentStatement);
            
            if (!empty($currentStatement)) {
                try {
                    // Jika statement CREATE DATABASE, eksekusi langsung
                    if (stripos($currentStatement, 'CREATE DATABASE') === 0) {
                        $pdo->exec($currentStatement);
                        $databaseCreated = true;
                        $executedCount++;
                        $currentStatement = '';
                        continue;
                    }
                    
                    // Setelah database dibuat, gunakan database tersebut
                    if ($databaseCreated) {
                        $pdo->exec("USE epl_naivebayes");
                    }
                    
                    $pdo->exec($currentStatement);
                    $executedCount++;
                } catch (PDOException $e) {
                    // Abaikan error jika database sudah ada
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        echo "   [SKIP] Database/tabel sudah ada\n";
                    } else {
                        echo "   [ERROR] " . $e->getMessage() . "\n";
                        echo "   Statement: " . substr($currentStatement, 0, 100) . "...\n";
                    }
                }
            }
            $currentStatement = '';
        }
    }
    
    echo "   Berhasil mengeksekusi $executedCount statement SQL\n\n";
    
    // Verifikasi database
    echo "3. Verifikasi database...\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE 'epl_naivebayes'");
    $dbExists = $stmt->rowCount() > 0;
    
    if ($dbExists) {
        echo "   ✓ Database 'epl_naivebayes' berhasil dibuat\n";
        
        // Cek tabel
        $pdo->exec("USE epl_naivebayes");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "   ✓ Tabel yang dibuat:\n";
        foreach ($tables as $table) {
            echo "     - $table\n";
        }
    } else {
        echo "   ✗ Database 'epl_naivebayes' tidak ditemukan\n";
    }
    
    echo "\n=== SETUP SELESAI ===\n\n";
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
