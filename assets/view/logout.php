<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Oturumu sonlandır
session_start();
session_unset();
session_destroy();

// Çerezleri temizle
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/'); // Çerezi sil
}

// Giriş sayfasına yönlendir
header("Location: login.php");
exit;
?>