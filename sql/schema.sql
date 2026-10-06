-- Property Reporting and Recovery System (PRS)
-- Security Unit, The Polytechnic Ibadan
-- Enterprise Database Schema

CREATE DATABASE IF NOT EXISTS prs_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prs_system;

-- ============================================
-- Table: users
-- Stores students, staff, and campus security administrators
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(50) NOT NULL COMMENT 'Matric number or Staff ID',
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_id_number UNIQUE (id_number),
    INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

-- ============================================
-- Table: lost_items
-- Reports submitted by students/staff who lost property
-- ============================================
CREATE TABLE IF NOT EXISTS lost_items (
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
    INDEX idx_lost_search (category, status, date_lost),
    INDEX idx_lost_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: found_items
-- Reports submitted by students, staff, or security who recovered property
-- ============================================
CREATE TABLE IF NOT EXISTS found_items (
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
    INDEX idx_found_search (category, status, date_found),
    INDEX idx_found_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: matches
-- Links a lost report to a found report verified by Security Unit
-- ============================================
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT NOT NULL,
    found_item_id INT NOT NULL,
    matched_by INT NOT NULL COMMENT 'Security Officer / Admin user ID',
    status ENUM('pending_verification','confirmed','rejected','released') NOT NULL DEFAULT 'pending_verification',
    match_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_matches_lookup (lost_item_id, found_item_id, status),
    FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: claims
-- Direct claim submissions by users asserting ownership over found items
-- ============================================
CREATE TABLE IF NOT EXISTS claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    found_item_id INT NOT NULL,
    user_id INT NOT NULL,
    proof_details TEXT NOT NULL COMMENT 'Identifying marks, serial numbers, lock codes, contents',
    contact_phone VARCHAR(20) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_claims_status (status),
    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: vouchers
-- Official Property Handover records issued by Security Unit
-- ============================================
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_no VARCHAR(50) NOT NULL UNIQUE,
    match_id INT DEFAULT NULL,
    found_item_id INT NOT NULL,
    claimant_id INT NOT NULL,
    issued_by INT NOT NULL COMMENT 'Admin/Security Officer ID',
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
) ENGINE=InnoDB;

-- ============================================
-- Table: audit_logs
-- Immutable Security Unit audit trail for administrative accountability
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'e.g. SUSPEND_USER, CONFIRM_MATCH, RELEASE_ITEM',
    entity_type VARCHAR(50) NOT NULL COMMENT 'e.g. match, user, found_item',
    entity_id INT NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Table: notifications
-- In-app notifications with read/unread tracking
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
