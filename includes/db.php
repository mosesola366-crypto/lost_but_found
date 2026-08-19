<?php
/**
 * Database connection for the Property Reporting and Recovery System.
 * Uses PDO with prepared statements throughout the application.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'prs_system';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$DB_NAME}`");
    ensure_database_tables($pdo);
} catch (PDOException $e) {
    die('Database connection failed. Make sure MySQL is running and that you have imported sql/schema.sql. (' . $e->getMessage() . ')');
}

function ensure_database_tables(PDO $pdo)
{
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lost_item_id INT NOT NULL,
            found_item_id INT NOT NULL,
            matched_by INT NOT NULL COMMENT "admin user id who confirmed the match",
            status ENUM("pending_verification","confirmed","rejected","released") NOT NULL DEFAULT "pending_verification",
            match_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
            FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
            FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );
}

/**
 * Ensure a default administrator account always exists so the admin
 * can never be locked out of the system. The password is hashed here
 * using PHP's own password_hash(), so it is guaranteed to be valid.
 */
function ensure_default_admin(PDO $pdo)
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "admin" LIMIT 1');
    $stmt->execute();
    if ($stmt->fetch() === false) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, id_number, email, phone, department, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, "admin")'
        );
        $insert->execute([
            'System Administrator',
            'ADMIN-001',
            'admin@polyibadan.edu.ng',
            '08000000000',
            'Security Unit',
            $hash,
        ]);
    }
}

ensure_default_admin($pdo);
seed_sample_data($pdo);

function seed_sample_data(PDO $pdo)
{
    $sampleUsers = [
        ['Zainab Adeyemi', 'STU001', 'zainab.adeyemi@polyibadan.edu.ng', '08012345678', 'Science'],
        ['Ayo Musa', 'STU002', 'ayo.musa@polyibadan.edu.ng', '08023456789', 'Engineering'],
        ['Tosin Bello', 'STU003', 'tosin.bello@polyibadan.edu.ng', '08034567890', 'Business'],
        ['Chiamaka Obi', 'STU004', 'chiamaka.obi@polyibadan.edu.ng', '08045678901', 'Arts'],
    ];

    $insertUser = $pdo->prepare('INSERT IGNORE INTO users (full_name, id_number, email, phone, department, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, "user")');
    foreach ($sampleUsers as $user) {
        $insertUser->execute([
            $user[0], $user[1], $user[2], $user[3], $user[4], password_hash('password123', PASSWORD_DEFAULT)
        ]);
    }

    $emailsArray = array_map(function ($user) {
        return $user[2];
    }, $sampleUsers);
    $emails = implode("','", $emailsArray);
    $userIds = $pdo->query("SELECT id FROM users WHERE email IN ('{$emails}') ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($userIds)) {
        return;
    }

    $lostRows = (int)$pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
    $foundRows = (int)$pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();

    $lostSamples = [
        ['Black Leather Wallet', 'Bags', 'Contains student ID card, ATM card, and library card.', 'Main Library entrance', '2025-02-05', '08012345678', 'pending'],
        ['Samsung A14 Phone', 'Electronics', 'Black phone with a cracked screen and a red case.', 'Computer Lab 2', '2025-02-08', '08023456789', 'pending'],
        ['Blue Backpack', 'Bags', 'Large backpack with stickers from the last semester.', 'Cafeteria', '2025-02-10', '08034567890', 'matched'],
        ['Silver Wristwatch', 'Jewelry', 'Analog watch with a leather strap and a scratch at 3 o\'clock.', 'Hall A corridor', '2025-02-12', '08045678901', 'resolved'],
        ['Campus ID Card', 'Other', 'Polytechnic Ibadan student card with personal photo.', 'Lecture Theatre', '2025-02-14', '08012345678', 'pending'],
        ['Black Umbrella', 'Clothing', 'Foldable umbrella with orange handle.', 'Bus Stop', '2025-02-16', '08023456789', 'pending'],
        ['Green Textbook', 'Books', 'Mathematics textbook with notes on first pages.', 'Science Block', '2025-02-17', '08034567890', 'matched'],
        ['Wireless Headphones', 'Electronics', 'Black Bluetooth headphones found near the gym.', 'Sports Complex', '2025-02-18', '08045678901', 'pending'],
        ['Red Notebook', 'Books', 'Notes for Chemistry class with a broken spine.', 'Lecture Theatre', '2025-02-19', '08012345678', 'pending'],
        ['Gold Ring', 'Jewelry', 'Thin gold ring with a small engraved date.', 'Hostel C common room', '2025-02-20', '08023456789', 'pending'],
        ['White Jacket', 'Clothing', 'Campus jacket with a blue lining and logo.', 'Sports Complex', '2025-02-21', '08034567890', 'pending'],
        ['Set of Keys', 'Keys', 'Key ring with four keys and a red fob.', 'Admin Building foyer', '2025-02-22', '08045678901', 'pending'],
        ['Black Sunglasses', 'Clothing', 'Aviator sunglasses with slightly scratched lenses.', 'Bus Stop', '2025-02-23', '08012345678', 'pending'],
        ['Yellow Water Bottle', 'Other', 'Reusable bottle with a campus club sticker.', 'Cafeteria', '2025-02-24', '08023456789', 'pending'],
        ['Blue Calculator', 'Electronics', 'Scientific calculator with a missing battery cover.', 'Science Block lab', '2025-02-25', '08034567890', 'matched'],
        ['Leather Belt', 'Clothing', 'Brown belt with a silver buckle.', 'Main Library', '2025-02-26', '08045678901', 'pending'],
        ['USB Charger', 'Electronics', 'White charger with a short cable.', 'Computer Lab 2', '2025-02-27', '08012345678', 'pending'],
        ['Library Card', 'Other', 'Library access card with student photo.', 'Main Library', '2025-02-28', '08023456789', 'pending'],
        ['Blue Pen Case', 'Bags', 'Pencil case containing blue, black and red pens.', 'Lecture Theatre', '2025-03-01', '08034567890', 'pending'],
        ['Brown Wallet', 'Bags', 'Wallet containing campus ID and two receipts.', 'Hostel C', '2025-03-02', '08045678901', 'pending'],
    ];

    $foundSamples = [
        ['Black Leather Wallet - Found', 'Bags', 'Found under a table near the Main Library entrance.', 'Main Library', '2025-03-01', 'pending'],
        ['Samsung A14 Phone - Found', 'Electronics', 'Found beside a desk in Computer Lab 2.', 'Computer Lab 2', '2025-03-03', 'matched'],
        ['Blue Backpack - Found', 'Bags', 'Found hanging on a chair in the cafeteria.', 'Cafeteria', '2025-03-04', 'claimed'],
        ['Silver Wristwatch - Found', 'Jewelry', 'Found on the floor near Hall A.', 'Hall A corridor', '2025-03-06', 'pending'],
        ['Campus ID Card - Found', 'Other', 'Found on a seat in the Lecture Theatre.', 'Lecture Theatre', '2025-03-07', 'pending'],
        ['Black Umbrella - Found', 'Clothing', 'Found at the campus bus stop.', 'Bus Stop', '2025-03-08', 'pending'],
        ['Green Textbook - Found', 'Books', 'Found near the Science Block noticeboard.', 'Science Block', '2025-03-09', 'pending'],
        ['Wireless Headphones - Found', 'Electronics', 'Found on a bench in the Sports Complex.', 'Sports Complex', '2025-03-10', 'pending'],
        ['Red Notebook - Found', 'Books', 'Found on a table in the Lecture Theatre.', 'Lecture Theatre', '2025-03-11', 'pending'],
        ['Gold Ring - Found', 'Jewelry', 'Found in the Hostel C common room.', 'Hostel C', '2025-03-12', 'pending'],
        ['White Jacket - Found', 'Clothing', 'Found near the Sports Complex entrance.', 'Sports Complex', '2025-03-13', 'pending'],
        ['Set of Keys - Found', 'Keys', 'Found by the Admin Building reception desk.', 'Admin Building', '2025-03-14', 'pending'],
        ['Black Sunglasses - Found', 'Clothing', 'Found on the bus stop bench.', 'Bus Stop', '2025-03-15', 'pending'],
        ['Yellow Water Bottle - Found', 'Other', 'Found near the cafeteria trash bins.', 'Cafeteria', '2025-03-16', 'pending'],
        ['Blue Calculator - Found', 'Electronics', 'Found beside a lab desk in Science Block.', 'Science Block', '2025-03-17', 'pending'],
        ['Leather Belt - Found', 'Clothing', 'Found hanging on a chair in the Main Library.', 'Main Library', '2025-03-18', 'pending'],
        ['USB Charger - Found', 'Electronics', 'Found under a computer desk.', 'Computer Lab 2', '2025-03-19', 'pending'],
        ['Library Card - Found', 'Other', 'Found on the floor near the library checkout.', 'Main Library', '2025-03-20', 'pending'],
        ['Blue Pen Case - Found', 'Bags', 'Found on a lecture hall seat.', 'Lecture Theatre', '2025-03-21', 'pending'],
        ['Brown Wallet - Found', 'Bags', 'Found near the Hostel C gate.', 'Hostel C', '2025-03-22', 'pending'],
    ];

    if ($lostRows < count($lostSamples)) {
        $insertLost = $pdo->prepare('INSERT INTO lost_items (user_id, item_name, category, description, location_lost, date_lost, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        for ($i = $lostRows; $i < count($lostSamples); $i++) {
            $sample = $lostSamples[$i];
            $insertLost->execute([
                $userIds[$i % count($userIds)],
                $sample[0],
                $sample[1],
                $sample[2],
                $sample[3],
                $sample[4],
                $sample[5],
                $sample[6],
            ]);
        }
    }

    if ($foundRows < count($foundSamples)) {
        $insertFound = $pdo->prepare('INSERT INTO found_items (user_id, item_name, category, description, location_found, date_found, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        for ($i = $foundRows; $i < count($foundSamples); $i++) {
            $sample = $foundSamples[$i];
            $insertFound->execute([
                $userIds[$i % count($userIds)],
                $sample[0],
                $sample[1],
                $sample[2],
                $sample[3],
                $sample[4],
                $sample[5],
            ]);
        }
    }

    $matchCount = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
    if ($matchCount < 3) {
        $matchedLostIds = $pdo->query('SELECT id FROM lost_items WHERE status = "matched" ORDER BY id ASC LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
        $matchedFoundIds = $pdo->query('SELECT id FROM found_items WHERE status = "matched" ORDER BY id ASC LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
        $matchStmt = $pdo->prepare('INSERT INTO matches (lost_item_id, found_item_id, matched_by, status) VALUES (?, ?, ?, ?)');
        $matchStatuses = ['pending_verification', 'confirmed', 'released'];
        foreach ($matchStatuses as $index => $status) {
            if (!isset($matchedLostIds[$index]) || !isset($matchedFoundIds[$index])) {
                continue;
            }
            $matchStmt->execute([$matchedLostIds[$index], $matchedFoundIds[$index], 1, $status]);
            if ($status === 'released') {
                $pdo->prepare('UPDATE lost_items SET status = "resolved" WHERE id = ?')->execute([$matchedLostIds[$index]]);
                $pdo->prepare('UPDATE found_items SET status = "claimed" WHERE id = ?')->execute([$matchedFoundIds[$index]]);
            }
        }
    }

    $notificationCount = (int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    if ($notificationCount < 4) {
        $notificationStmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $messages = [
            'Your lost item report was updated. Check the system for details.',
            'A possible match was found for one of your reports.',
            'A recent found item report is now available for review.',
            'New notifications from the Security Unit are available.',
        ];
        foreach ($messages as $index => $message) {
            if (isset($userIds[$index])) {
                $notificationStmt->execute([$userIds[$index], $message]);
            }
        }
    }
}
