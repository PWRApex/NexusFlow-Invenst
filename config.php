<?php
// Veritabanı bağlantı bilgileri
$host = 'localhost'; // XAMPP için varsayılan host
$dbname = 'nexusflow_db'; // Veritabanı adı
$username = 'root'; // XAMPP için varsayılan kullanıcı adı
$password = ''; // XAMPP için varsayılan şifre (boş)

// Veritabanı bağlantısı
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // PDO hata modunu ayarla
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Türkçe karakter sorunu için
    $conn->exec("SET NAMES 'utf8'; SET CHARSET 'utf8'");
} catch(PDOException $e) {
    echo "Bağlantı hatası: " . $e->getMessage();
    die();
}

// Oturum başlat
session_start();

// Kullanıcı giriş kontrolü için fonksiyon
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: ./assets/view/login.php");
        exit;
    }
}

// Kullanıcı giriş yapmışsa ana sayfaya yönlendir
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: ../../index.php");
        exit;
    }
}

// XSS saldırılarına karşı güvenlik önlemi
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Hata ve başarı mesajları için fonksiyonlar
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

function getErrorMessage() {
    $message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
    unset($_SESSION['error_message']);
    return $message;
}

function getSuccessMessage() {
    $message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
    unset($_SESSION['success_message']);
    return $message;
}
?>