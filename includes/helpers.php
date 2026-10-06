<?php
/**
 * Global Helper Functions & Constants for Property Reporting and Recovery System (PRS).
 * Security Unit, The Polytechnic Ibadan.
 */

date_default_timezone_set('Africa/Lagos');

const PRS_CATEGORIES = [
    'Electronics',
    'Documents',
    'Jewelry',
    'Bags',
    'Clothing',
    'Keys',
    'Books',
    'Wallet/Purse',
    'Other'
];

const PRS_FACULTIES = [
    'Faculty of Engineering' => [
        'Civil Engineering',
        'Computer Engineering',
        'Electrical / Electronic Engineering',
        'Mechanical Engineering',
        'Mechatronics Engineering',
    ],
    'Faculty of Science' => [
        'Computer Science',
        'Science Laboratory Technology (SLT)',
        'Statistics',
        'Mathematics',
        'Physics with Electronics',
        'Chemistry / Biochemistry',
        'Biology / Microbiology',
    ],
    'Faculty of Business and Communication Studies (FBCS)' => [
        'Business Administration and Management',
        'Mass Communication',
        'Marketing',
        'Office Technology and Management (OTM)',
        'Public Administration',
        'Music Technology',
        'Library and Information Science',
    ],
    'Faculty of Financial Management Studies (FFMS)' => [
        'Accountancy',
        'Banking and Finance',
        'Insurance',
    ],
    'Faculty of Environmental Studies (FES)' => [
        'Architecture',
        'Building Technology',
        'Estate Management and Valuation',
        'Quantity Surveying',
        'Urban and Regional Planning',
        'Surveying and Geoinformatics',
        'Art and Design',
    ],
];

/**
 * Returns the parent faculty for a given department name.
 *
 * @param string $dept
 * @return string|null
 */
function get_faculty_for_department(string $dept): ?string
{
    foreach (PRS_FACULTIES as $faculty => $departments) {
        if (in_array($dept, $departments, true)) {
            return $faculty;
        }
    }
    return null;
}


/**
 * Normalise a Nigerian phone number to E.164 format (+234XXXXXXXXXX).
 * Accepts: 0803..., 234803..., +234803..., 8031234567
 * Returns: +2348031234567 on success, or null on invalid input.
 *
 * @param string $raw  Raw phone number string submitted by the user
 * @return string|null  E.164 string or null if invalid
 */
function normalise_ng_phone(string $raw): ?string
{
    $digits = preg_replace('/\D/', '', $raw);

    // Strip leading country code 234 or single leading 0
    if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
        $digits = substr($digits, 3);
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    // Must be exactly 10 digits starting with 7, 8, or 9
    if (strlen($digits) !== 10 || !preg_match('/^[789]/', $digits)) {
        return null;
    }

    return '+234' . $digits;
}

/**
 * Validate a raw Nigerian phone string.
 * Returns an error message string, or null when valid.
 *
 * @param string $raw
 * @return string|null
 */
function validate_ng_phone(string $raw): ?string
{
    if (trim($raw) === '') {
        return 'Phone number is required.';
    }
    $e164 = normalise_ng_phone($raw);
    if ($e164 === null) {
        return 'Enter a valid Nigerian number (e.g. 8031234567 after +234). Must be 10 digits starting with 7, 8, or 9.';
    }
    return null;
}

/**
 * Validate a full name string.
 * Allows only letters (including accented), spaces, hyphens, and apostrophes.
 * Returns an error message string, or null when valid.
 *
 * @param string $raw
 * @return string|null
 */
function validate_full_name(string $raw): ?string
{
    $name = trim($raw);
    if ($name === '') {
        return 'Full name is required.';
    }
    // Allow Unicode letters, spaces, hyphens, and apostrophes
    if (!preg_match('/^[\p{L}\s\'-]+$/u', $name)) {
        return 'Full name must contain only letters, spaces, hyphens, or apostrophes. Numbers and special characters are not allowed.';
    }
    if (strlen($name) < 3) {
        return 'Full name must be at least 3 characters.';
    }
    return null;
}

/**
 * Validate a Matriculation / Staff ID Number.
 * Must be exactly 13 digits (numbers only).
 * Returns an error message string, or null when valid.
 *
 * @param string $raw
 * @return string|null
 */
function validate_matric_number(string $raw): ?string
{
    $id = trim($raw);
    if ($id === '') {
        return 'Matric number is required.';
    }
    if (!preg_match('/^\d{13}$/', $id)) {
        return 'Matric number must be exactly 13 digits (numbers only, no letters or slashes).';
    }
    return null;
}

/**
 * Validate that an entered date is today or in the past (never future).
 *
 * @param string $rawDate   Date in YYYY-MM-DD format
 * @param string $fieldName Label for error messaging
 * @return string|null
 */
function validate_past_or_today_date(string $rawDate, string $fieldName = 'Date'): ?string
{
    $trimmed = trim($rawDate);
    if ($trimmed === '') {
        return "{$fieldName} is required.";
    }

    $today = date('Y-m-d');
    if ($trimmed > $today) {
        return "{$fieldName} cannot be in the future. Please select today or an earlier date.";
    }

    $parts = explode('-', $trimmed);
    if (count($parts) !== 3 || !checkdate((int)($parts[1] ?? 0), (int)($parts[2] ?? 0), (int)($parts[0] ?? 0))) {
        return "Please enter a valid date format.";
    }

    return null;
}


/**
 * Log an administrative or critical security action into the audit trail.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $action
 * @param string $entityType
 * @param int    $entityId
 * @param string|null $details
 * @return bool
 */
function log_audit_action(PDO $pdo, int $userId, string $action, string $entityType, int $entityId, ?string $details = null): bool
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip]);
    } catch (PDOException $e) {
        // Fallback: don't break main transaction if audit logging encounters a table variance
        error_log('Audit logging failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send an in-app notification to a user with an optional direct navigation link.
 *
 * @param PDO         $pdo
 * @param int         $userId
 * @param string      $message
 * @param string|null $linkUrl
 * @return bool
 */
function notify_user(PDO $pdo, int $userId, string $message, ?string $linkUrl = null): bool
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, message, link_url) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$userId, $message, $linkUrl]);
    } catch (PDOException $e) {
        error_log('Notification dispatch failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Format a status string as a styled HTML badge.
 *
 * @param string $status
 * @return string
 */
function render_status_badge(string $status): string
{
    $cleanStatus = strtolower(trim($status));
    $cssClass = match ($cleanStatus) {
        'pending', 'pending_verification' => 'badge-pending',
        'matched' => 'badge-matched',
        'resolved', 'confirmed', 'claimed', 'approved' => 'badge-resolved',
        'rejected', 'suspended' => 'badge-rejected',
        default => 'badge-pending',
    };

    $display = ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge ' . htmlspecialchars($cssClass) . '">' . htmlspecialchars($display) . '</span>';
}

/**
 * Compare two phone numbers flexibly by examining their canonical trailing digits.
 *
 * @param string $p1
 * @param string $p2
 * @return bool
 */
function phones_match(string $p1, string $p2): bool
{
    $d1 = preg_replace('/\D/', '', $p1);
    $d2 = preg_replace('/\D/', '', $p2);

    if ($d1 === '' || $d2 === '') {
        return false;
    }

    if ($d1 === $d2) {
        return true;
    }

    // Compare trailing 10 digits (e.g. 08012345678 vs +2348012345678)
    $last1 = substr($d1, -10);
    $last2 = substr($d2, -10);

    return strlen($last1) >= 8 && strlen($last2) >= 8 && $last1 === $last2;
}

/**
 * Lookup an existing user by Matric/Staff ID or create a new user record.
 * Enables identifier-based, passwordless, sessionless reporting for campus students and staff.
 *
 * @param PDO         $pdo
 * @param string      $idNumber    Matriculation Number or Staff ID
 * @param string      $phone       Contact Phone Number
 * @param string      $fullName    Reporter's full name
 * @param string|null $department Optional department / faculty
 * @return int User ID
 * @throws RuntimeException If user account is suspended
 */
function get_or_create_reporter(PDO $pdo, string $idNumber, string $phone, string $fullName, ?string $department = null): int
{
    $cleanId    = strtoupper(trim($idNumber));
    $cleanPhone = trim($phone);
    $cleanName  = trim($fullName);
    $cleanDept  = $department ? trim($department) : null;

    if ($cleanId === '') {
        throw new InvalidArgumentException('Matriculation / Staff ID is required.');
    }
    if ($cleanPhone === '') {
        throw new InvalidArgumentException('Phone number is required.');
    }
    if ($cleanName === '') {
        throw new InvalidArgumentException('Reporter full name is required.');
    }

    $stmt = $pdo->prepare('SELECT id, status, phone FROM users WHERE UPPER(id_number) = ? LIMIT 1');
    $stmt->execute([$cleanId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (($existing['status'] ?? 'active') === 'suspended') {
            throw new RuntimeException('This Matriculation / Staff ID has been suspended from reporting by the Security Unit.');
        }

        // Keep reporter's phone, name, and department up to date
        $update = $pdo->prepare('UPDATE users SET phone = ?, full_name = ?, department = COALESCE(?, department) WHERE id = ?');
        $update->execute([$cleanPhone, $cleanName, $cleanDept, (int)$existing['id']]);

        return (int)$existing['id'];
    }

    // Generate safe synthetic email for database unique constraint
    $slug = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($cleanId));
    if ($slug === '') {
        $slug = 'user_' . bin2hex(random_bytes(4));
    }
    $email = $slug . '@prs.polyibadan.local';

    // Verify synthetic email uniqueness in case of colliding IDs
    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $emailCheck->execute([$email]);
    if ($emailCheck->fetch()) {
        $email = $slug . '_' . bin2hex(random_bytes(3)) . '@prs.polyibadan.local';
    }

    $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, id_number, email, phone, department, password_hash, role, status)
         VALUES (?, ?, ?, ?, ?, ?, "user", "active")'
    );
    $insert->execute([$cleanName, $cleanId, $email, $cleanPhone, $cleanDept, $dummyHash]);

    return (int)$pdo->lastInsertId();
}

