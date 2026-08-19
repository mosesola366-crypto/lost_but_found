-- Property Reporting and Recovery System
-- Security Unit, The Polytechnic Ibadan
-- Database Schema

CREATE DATABASE IF NOT EXISTS prs_system;
USE prs_system;

-- ============================================
-- Table: users
-- Stores both regular users (students/staff) and administrators
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(50) NOT NULL COMMENT 'Matric number or Staff ID',
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Table: lost_items
-- Reports submitted by users who lost an item
-- ============================================
CREATE TABLE lost_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    location_lost VARCHAR(150) NOT NULL,
    date_lost DATE NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    status ENUM('pending','matched','resolved') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- Table: found_items
-- Reports submitted by users/security who found an item
-- ============================================
CREATE TABLE found_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    location_found VARCHAR(150) NOT NULL,
    date_found DATE NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','matched','claimed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- Table: matches
-- Links a lost item report to a found item report once verified by admin
-- ============================================
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT NOT NULL,
    found_item_id INT NOT NULL,
    matched_by INT NOT NULL COMMENT 'admin user id who confirmed the match',
    status ENUM('pending_verification','confirmed','rejected','released') NOT NULL DEFAULT 'pending_verification',
    match_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- Table: notifications
-- In-app notifications shown to users on their dashboard
-- ============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default administrator account is created automatically the first time
-- the application runs (see includes/db.php), using PHP's own
-- password_hash() function. This guarantees the stored hash is always
-- valid for the PHP version running on your machine.
-- Default login after first run:
--   Email:    admin@polyibadan.edu.ng
--   Password: admin123

-- ============================================
-- Sample data for easier testing and initial install
-- ============================================

INSERT INTO users (full_name, id_number, email, phone, department, password_hash, role)
VALUES
    ('Zainab Adeyemi', 'STU001', 'zainab.adeyemi@polyibadan.edu.ng', '08012345678', 'Science', '$2y$10$zts5V1WdhwFq6WvFxwWEqOeASal4Y7QruCU7DzqQrlxg8blKgTf4y', 'user'),
    ('Ayo Musa', 'STU002', 'ayo.musa@polyibadan.edu.ng', '08023456789', 'Engineering', '$2y$10$zts5V1WdhwFq6WvFxwWEqOeASal4Y7QruCU7DzqQrlxg8blKgTf4y', 'user'),
    ('Tosin Bello', 'STU003', 'tosin.bello@polyibadan.edu.ng', '08034567890', 'Business', '$2y$10$zts5V1WdhwFq6WvFxwWEqOeASal4Y7QruCU7DzqQrlxg8blKgTf4y', 'user'),
    ('Chiamaka Obi', 'STU004', 'chiamaka.obi@polyibadan.edu.ng', '08045678901', 'Arts', '$2y$10$zts5V1WdhwFq6WvFxwWEqOeASal4Y7QruCU7DzqQrlxg8blKgTf4y', 'user');

INSERT INTO lost_items (user_id, item_name, category, description, location_lost, date_lost, contact_phone, status)
VALUES
    (1, 'Black Leather Wallet', 'Bags', 'Contains student ID card, ATM card, and library card.', 'Main Library entrance', '2025-02-05', '08012345678', 'pending'),
    (2, 'Samsung A14 Phone', 'Electronics', 'Black phone with a cracked screen and a red case.', 'Computer Lab 2', '2025-02-08', '08023456789', 'pending'),
    (3, 'Blue Backpack', 'Bags', 'Large backpack with stickers from the last semester.', 'Cafeteria', '2025-02-10', '08034567890', 'matched'),
    (4, 'Silver Wristwatch', 'Jewelry', 'Analog watch with a leather strap and a scratch at 3 o\'clock.', 'Hall A corridor', '2025-02-12', '08045678901', 'resolved'),
    (1, 'Campus ID Card', 'Other', 'Polytechnic Ibadan student card with personal photo.', 'Lecture Theatre', '2025-02-14', '08012345678', 'pending'),
    (2, 'Black Umbrella', 'Clothing', 'Foldable umbrella with orange handle.', 'Bus Stop', '2025-02-16', '08023456789', 'pending'),
    (3, 'Green Textbook', 'Books', 'Mathematics textbook with notes on first pages.', 'Science Block', '2025-02-17', '08034567890', 'matched'),
    (4, 'Wireless Headphones', 'Electronics', 'Black Bluetooth headphones found near the gym.', 'Sports Complex', '2025-02-18', '08045678901', 'pending');

INSERT INTO found_items (user_id, item_name, category, description, location_found, date_found, status)
VALUES
    (1, 'Black Leather Wallet - Found', 'Bags', 'Found under a table near the Main Library entrance.', 'Main Library', '2025-03-01', 'pending'),
    (2, 'Samsung A14 Phone - Found', 'Electronics', 'Found beside a desk in Computer Lab 2.', 'Computer Lab 2', '2025-03-03', 'matched'),
    (3, 'Blue Backpack - Found', 'Bags', 'Found hanging on a chair in the cafeteria.', 'Cafeteria', '2025-03-04', 'claimed'),
    (4, 'Silver Wristwatch - Found', 'Jewelry', 'Found on the floor near Hall A.', 'Hall A corridor', '2025-03-06', 'pending'),
    (1, 'Campus ID Card - Found', 'Other', 'Found on a seat in the Lecture Theatre.', 'Lecture Theatre', '2025-03-07', 'pending'),
    (2, 'Black Umbrella - Found', 'Clothing', 'Found at the campus bus stop.', 'Bus Stop', '2025-03-08', 'pending'),
    (3, 'Green Textbook - Found', 'Books', 'Found near the Science Block noticeboard.', 'Science Block', '2025-03-09', 'pending'),
    (4, 'Wireless Headphones - Found', 'Electronics', 'Found on a bench in the Sports Complex.', 'Sports Complex', '2025-03-10', 'pending');

INSERT INTO matches (lost_item_id, found_item_id, matched_by, status)
VALUES
    (1, 1, 1, 'pending_verification'),
    (2, 2, 1, 'confirmed'),
    (3, 3, 1, 'released');

INSERT INTO notifications (user_id, message)
VALUES
    (1, 'Your lost item report was updated. Check the system for details.'),
    (2, 'A possible match was found for one of your reports.'),
    (3, 'A recent found item report is now available for review.'),
    (4, 'New notifications from the Security Unit are available.');
