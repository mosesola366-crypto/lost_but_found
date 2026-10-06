<?php
/**
 * Database connection for the Property Reporting and Recovery System (PRS).
 * Security Unit, The Polytechnic Ibadan.
 *
 * Uses PDO with prepared statements and strict error handling.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'prs_system';
$DB_USER = 'root';
$DB_PASS = '';

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Self-healing schema: ensure all required tables exist
    try {
        $claimsCheck = $pdo->query("SHOW TABLES LIKE 'claims'")->fetchColumn();
        if (!$claimsCheck) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS claims (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    found_item_id INT NOT NULL,
                    user_id INT NOT NULL,
                    proof_details TEXT NOT NULL COMMENT "Identifying marks, serial numbers, lock codes, contents",
                    contact_phone VARCHAR(20) NOT NULL,
                    status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
                    admin_notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_claims_status (status),
                    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
        }

        $vouchersCheck = $pdo->query("SHOW TABLES LIKE 'vouchers'")->fetchColumn();
        if (!$vouchersCheck) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS vouchers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    voucher_no VARCHAR(50) NOT NULL UNIQUE,
                    match_id INT DEFAULT NULL,
                    found_item_id INT NOT NULL,
                    claimant_id INT NOT NULL,
                    issued_by INT NOT NULL,
                    claimant_name VARCHAR(100) NOT NULL,
                    claimant_id_number VARCHAR(50) NOT NULL,
                    claimant_phone VARCHAR(20) NOT NULL,
                    claimant_department VARCHAR(100) DEFAULT NULL,
                    remarks TEXT DEFAULT NULL,
                    release_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
                    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
                    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
        }

        $auditCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetchColumn();
        if (!$auditCheck) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id INT NOT NULL,
                    details TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_audit_created (created_at),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
        }
    } catch (Exception $schemaEx) {
        // Suppress and continue - do not crash application on metadata check
        error_log('Schema self-healing check error: ' . $schemaEx->getMessage());
    }
} catch (PDOException $e) {
    // If database does not exist or connection fails, attempt fallback connection to guide the user
    try {
        $tempPdo = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS);
        $exists = $tempPdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$DB_NAME}'")->fetchColumn();
        if (!$exists) {
            $rootPath = (str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) ? '../' : '';
            die("
                <div style='font-family:sans-serif; max-width:550px; margin:60px auto; padding:24px; border:1px solid #e2e6ec; border-radius:10px; background:#fff;'>
                    <h2 style='color:#0f2c4c; margin-top:0;'>Database Not Initialized</h2>
                    <p style='color:#555;'>The database <code>{$DB_NAME}</code> has not been created yet.</p>
                    <p><a href='{$rootPath}install.php' style='display:inline-block; padding:10px 18px; background:#0f2c4c; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Run PRS Installer</a></p>
                </div>
            ");
        }
    } catch (Exception $inner) {
        // Continue to default error handler below
    }

    die('Database connection failed. Please ensure MySQL is running in XAMPP. Details: ' . htmlspecialchars($e->getMessage()));
}
