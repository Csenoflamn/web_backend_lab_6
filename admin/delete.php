<?php
session_start();
require_once __DIR__ . '/../includes/application.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    deleteApplication($id);
}
header('Location: index.php');
exit;