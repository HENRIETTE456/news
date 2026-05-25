<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isDoctor() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'doctor';
}
?>