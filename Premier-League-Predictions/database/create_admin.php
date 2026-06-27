<?php
/**
 * Create Default Admin User
 * Premier League Predictions - Naive Bayes
 */

// Konfigurasi Database
$host = 'localhost';
$dbname = 'epl_naivebayes';

try {
    // Koneksi ke database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CREATE DEFAULT ADMIN USER ===\n\n";
    
    // Cek apakah admin sudah ada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ?");
    $stmt->execute(['admin']);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "Admin user 'admin' already exists. Skipping creation.\n";
        
        // Tampilkan info admin yang ada
        $stmt = $pdo->prepare("SELECT admin_id, username, full_name, email, created_at FROM admin WHERE username = ?");
        $stmt->execute(['admin']);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nExisting admin info:\n";
        echo "Username: {$admin['username']}\n";
        echo "Full Name: {$admin['full_name']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Created: {$admin['created_at']}\n";
        
    } else {
        // Insert admin baru
        $adminUsername = 'admin';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT); // Default password
        $fullName = 'Administrator';
        $email = 'admin@epl-predictions.com';
        
        $stmt = $pdo->prepare("INSERT INTO admin (username, password, full_name, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$adminUsername, $adminPassword, $fullName, $email]);
        
        echo "✓ Admin user created successfully!\n";
        echo "\nLogin credentials:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
        echo "\n⚠ Please change the password after first login!\n";
    }
    
    echo "\n=== DONE ===\n";
    
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
