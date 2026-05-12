<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /erp/login.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $id = (int)$_SESSION['user_id'];
    $r = $db->query("SELECT id, name, email, role FROM users WHERE id = $id");
    return $r->fetch_assoc();
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function sanitize($val) {
    return htmlspecialchars(strip_tags(trim($val)));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateCode($prefix, $table, $field) {
    $db = getDB();
    $r = $db->query("SELECT COUNT(*) as cnt FROM $table");
    $row = $r->fetch_assoc();
    $num = str_pad($row['cnt'] + 1, 5, '0', STR_PAD_LEFT);
    return $prefix . date('Y') . $num;
}
?>
