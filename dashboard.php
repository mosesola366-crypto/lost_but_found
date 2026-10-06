<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (is_admin()) {
    header('Location: admin/dashboard.php');
    exit;
}

if (is_logged_in() && !empty($_SESSION['id_number']) && !empty($_SESSION['phone'])) {
    header('Location: track.php?id_number=' . urlencode($_SESSION['id_number']) . '&phone=' . urlencode($_SESSION['phone']));
    exit;
}

header('Location: track.php');
exit;
