<?php
/**
 * Property Reporting and Recovery System (PRS)
 * Database Installation and Migration Script.
 *
 * Run this script once via browser (http://localhost/prs_system/install.php)
 * or via CLI (php install.php) to initialize the database and tables.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'prs_system';
$DB_USER = 'root';
$DB_PASS = '';

if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}

$message = '';
$error = '';
$details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || php_sapi_name() === 'cli') {
    $seed_samples = isset($_POST['seed_samples']) || (php_sapi_name() === 'cli' && in_array('--seed', $argv ?? []));

    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$DB_NAME}`");
        $details[] = "Database `{$DB_NAME}` created/verified successfully.";

        // Table: users
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                id_number VARCHAR(50) NOT NULL COMMENT "Matric number or Staff ID",
                email VARCHAR(100) NOT NULL UNIQUE,
                phone VARCHAR(20) NOT NULL,
                department VARCHAR(100) DEFAULT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM("user","admin") NOT NULL DEFAULT "user",
                status ENUM("active","suspended") NOT NULL DEFAULT "active",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_users_id_number (id_number),
                INDEX idx_users_role_status (role, status)
            ) ENGINE=InnoDB'
        );
        $details[] = "Table `users` verified.";

        // Table: lost_items
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS lost_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                location_lost VARCHAR(150) NOT NULL,
                date_lost DATE NOT NULL,
                contact_phone VARCHAR(20) NOT NULL,
                status ENUM("pending","matched","resolved") NOT NULL DEFAULT "pending",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lost_category (category),
                INDEX idx_lost_status (status),
                INDEX idx_lost_date (date_lost),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
        $details[] = "Table `lost_items` verified.";

        // Table: found_items
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS found_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                location_found VARCHAR(150) NOT NULL,
                date_found DATE NOT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                status ENUM("pending","matched","claimed") NOT NULL DEFAULT "pending",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_found_category (category),
                INDEX idx_found_status (status),
                INDEX idx_found_date (date_found),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
        $details[] = "Table `found_items` verified.";

        // Table: matches
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS matches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lost_item_id INT NOT NULL,
                found_item_id INT NOT NULL,
                matched_by INT NOT NULL COMMENT "admin user id who confirmed the match",
                status ENUM("pending_verification","confirmed","rejected","released") NOT NULL DEFAULT "pending_verification",
                match_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_matches_status (status),
                FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
                FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
                FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
        $details[] = "Table `matches` verified.";

        // Table: notifications
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message VARCHAR(255) NOT NULL,
                link_url VARCHAR(255) DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notif_user_read (user_id, is_read, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
        $details[] = "Table `notifications` verified.";

        // Table: claims
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
        $details[] = "Table `claims` verified.";

        // Table: vouchers
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
        $details[] = "Table `vouchers` verified.";

        // Table: audit_logs
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
        $details[] = "Table `audit_logs` verified.";

        // Ensure default admin account exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "admin" LIMIT 1');
        $stmt->execute();
        if ($stmt->fetch() === false) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, id_number, email, phone, department, password_hash, role, status)
                 VALUES (?, ?, ?, ?, ?, ?, "admin", "active")'
            );
            $insert->execute([
                'System Administrator',
                'ADMIN-001',
                'admin@polyibadan.edu.ng',
                '08000000000',
                'Security Unit',
                $hash,
            ]);
            $details[] = "Default Administrator account seeded (admin@polyibadan.edu.ng / admin123).";
        } else {
            $details[] = "Administrator account already present.";
        }

        // Optional sample data seeding
        if ($seed_samples) {
            $sampleUsers = [
                ['Zainab Adeyemi', 'STU001', 'zainab.adeyemi@polyibadan.edu.ng', '08012345678', 'Science'],
                ['Ayo Musa', 'STU002', 'ayo.musa@polyibadan.edu.ng', '08023456789', 'Engineering'],
                ['Tosin Bello', 'STU003', 'tosin.bello@polyibadan.edu.ng', '08034567890', 'Business'],
                ['Chiamaka Obi', 'STU004', 'chiamaka.obi@polyibadan.edu.ng', '08045678901', 'Arts'],
            ];

            $insertUser = $pdo->prepare('INSERT IGNORE INTO users (full_name, id_number, email, phone, department, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, "user")');
            foreach ($sampleUsers as $u) {
                $insertUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], password_hash('password123', PASSWORD_DEFAULT)]);
            }
            $details[] = "Sample users seeded.";
        }

        $message = "Installation and database migration completed successfully!";
    } catch (PDOException $e) {
        $error = "Database operation failed: " . $e->getMessage();
    }
}

if (php_sapi_name() === 'cli') {
    if ($error) {
        echo "ERROR: {$error}\n";
        exit(1);
    }
    echo "SUCCESS: {$message}\n";
    foreach ($details as $d) {
        echo " - {$d}\n";
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Setup | Property Reporting and Recovery System</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  .setup-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: linear-gradient(135deg, #0f2c4c 0%, #0a1e35 100%); }
  .setup-card { background: #fff; border-radius: 12px; max-width: 580px; width: 100%; padding: 32px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
  .setup-steps { background: #f8f9fb; border: 1px solid #e2e6ec; border-radius: 8px; padding: 14px 18px; margin: 18px 0; font-size: 13.5px; }
  .setup-steps li { margin-bottom: 6px; color: #1e8a5f; }
</style>
</head>
<body>
<div class="setup-wrap">
  <div class="setup-card">
    <div style="text-align:center; margin-bottom:20px;">
      <div class="crest" style="width:50px; height:50px; margin:0 auto 10px; font-size:18px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; color:var(--navy-dark);">PI</div>
      <h1 style="margin:0; font-size:22px; color:var(--navy-dark);">PRS Database Setup</h1>
      <p style="color:var(--muted); font-size:13.5px; margin-top:4px;">Security Unit &middot; The Polytechnic Ibadan</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success"><strong><?= htmlspecialchars($message) ?></strong></div>
      <ul class="setup-steps">
        <?php foreach ($details as $d): ?>
          <li><?= htmlspecialchars($d) ?></li>
        <?php endforeach; ?>
      </ul>
      <div style="display:flex; gap:10px; margin-top:20px;">
        <a href="login.php" class="btn btn-primary" style="flex:1; text-align:center;">Proceed to Login</a>
        <a href="index.php" class="btn btn-outline" style="flex:1; text-align:center;">Go to Home</a>
      </div>
    <?php else: ?>
      <p style="font-size:14px; color:var(--text); line-height:1.6;">
        This utility will initialize or verify the <code>prs_system</code> MySQL database structure, indexes, and administrator account.
      </p>

      <form method="post" style="margin-top:20px;">
        <div class="field" style="margin-bottom:18px;">
          <label style="display:flex; align-items:center; gap:8px; font-weight:normal; cursor:pointer;">
            <input type="checkbox" name="seed_samples" value="1" checked style="width:auto;">
            <span>Seed demo student accounts for testing</span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">Initialize Database</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
