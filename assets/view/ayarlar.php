<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
redirectIfNotLoggedIn();

// Kullanıcı bilgilerini al
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Kullanıcı detaylarını veritabanından al
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $email = $user['email'];
} catch(PDOException $e) {
    setErrorMessage("Kullanıcı bilgileri alınırken bir hata oluştu: " . $e->getMessage());
}

// Profil güncelleme işlemi
if (isset($_POST['update_profile'])) {
    $new_first_name = sanitize($_POST['first_name']);
    $new_last_name = sanitize($_POST['last_name']);
    $new_email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Temel doğrulama
    if (empty($new_first_name) || empty($new_last_name) || empty($new_email)) {
        setErrorMessage("Ad, soyad ve e-posta alanları boş bırakılamaz.");
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        setErrorMessage("Geçerli bir e-posta adresi giriniz.");
    } else {
        try {
            // E-posta kontrolü (mevcut kullanıcı hariç)
            $checkEmailStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
            $checkEmailStmt->bindParam(':email', $new_email);
            $checkEmailStmt->bindParam(':user_id', $user_id);
            $checkEmailStmt->execute();
            
            if ($checkEmailStmt->fetchColumn() > 0) {
                setErrorMessage("Bu e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.");
            } else {
                // Şifre değişikliği yapılacak mı kontrolü
                if (!empty($current_password)) {
                    // Mevcut şifre doğrulama
                    $checkPasswordStmt = $conn->prepare("SELECT password FROM users WHERE user_id = :user_id");
                    $checkPasswordStmt->bindParam(':user_id', $user_id);
                    $checkPasswordStmt->execute();
                    $hashedPassword = $checkPasswordStmt->fetchColumn();
                    
                    if (!password_verify($current_password, $hashedPassword)) {
                        setErrorMessage("Mevcut şifreniz hatalı.");
                    } elseif (empty($new_password) || empty($confirm_password)) {
                        setErrorMessage("Yeni şifre ve şifre tekrarı alanları boş bırakılamaz.");
                    } elseif ($new_password !== $confirm_password) {
                        setErrorMessage("Yeni şifre ve şifre tekrarı eşleşmiyor.");
                    } elseif (strlen($new_password) < 6) {
                        setErrorMessage("Şifre en az 6 karakter uzunluğunda olmalıdır.");
                    } else {
                        // Şifre değişikliği ile profil güncelleme
                        $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $updateStmt = $conn->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, password = :password WHERE user_id = :user_id");
                        $updateStmt->bindParam(':first_name', $new_first_name);
                        $updateStmt->bindParam(':last_name', $new_last_name);
                        $updateStmt->bindParam(':email', $new_email);
                        $updateStmt->bindParam(':password', $hashedNewPassword);
                        $updateStmt->bindParam(':user_id', $user_id);
                        $updateStmt->execute();
                        
                        // Session bilgilerini güncelle
                        $_SESSION['first_name'] = $new_first_name;
                        $_SESSION['last_name'] = $new_last_name;
                        
                        setSuccessMessage("Profil bilgileriniz ve şifreniz başarıyla güncellendi.");
                        header("Location: ayarlar.php");
                        exit;
                    }
                } else {
                    // Sadece profil bilgilerini güncelleme (şifre değişikliği olmadan)
                    $updateStmt = $conn->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE user_id = :user_id");
                    $updateStmt->bindParam(':first_name', $new_first_name);
                    $updateStmt->bindParam(':last_name', $new_last_name);
                    $updateStmt->bindParam(':email', $new_email);
                    $updateStmt->bindParam(':user_id', $user_id);
                    $updateStmt->execute();
                    
                    // Session bilgilerini güncelle
                    $_SESSION['first_name'] = $new_first_name;
                    $_SESSION['last_name'] = $new_last_name;
                    
                    setSuccessMessage("Profil bilgileriniz başarıyla güncellendi.");
                    header("Location: ayarlar.php");
                    exit;
                }
            }
        } catch(PDOException $e) {
            setErrorMessage("Profil güncellenirken bir hata oluştu: " . $e->getMessage());
        }
    }
}

// Tema ayarları
if (isset($_POST['update_theme'])) {
    $theme = sanitize($_POST['theme']);
    
    // Tema tercihini session'a kaydet
    $_SESSION['theme'] = $theme;
    
    setSuccessMessage("Tema tercihiniz kaydedildi.");
    header("Location: ayarlar.php");
    exit;
}

// Bildirim ayarları
if (isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    
    try {
        // Kullanıcı ayarları tablosu olsaydı buraya kaydedilirdi
        // Şimdilik session'a kaydedelim
        $_SESSION['email_notifications'] = $email_notifications;
        
        setSuccessMessage("Bildirim ayarlarınız güncellendi.");
        header("Location: ayarlar.php");
        exit;
    } catch(PDOException $e) {
        setErrorMessage("Bildirim ayarları güncellenirken bir hata oluştu: " . $e->getMessage());
    }
}

// Hata ve başarı mesajlarını al
$errorMessage = getErrorMessage();
$successMessage = getSuccessMessage();

// Tema tercihi
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

// Bildirim ayarları
$email_notifications = isset($_SESSION['email_notifications']) ? $_SESSION['email_notifications'] : 1;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Ayarlar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --background-color: #f8f9fa;
            --card-bg-color: #ffffff;
            --text-color: #212529;
            --border-color: rgba(0, 0, 0, 0.125);
        }
        
        body.dark-theme {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --background-color: #121212;
            --card-bg-color: #1e1e1e;
            --text-color: #f8f9fa;
            --border-color: rgba(255, 255, 255, 0.125);
        }
        
        body {
            background-color: var(--background-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .sidebar {
            height: 100vh;
            background-color: var(--card-bg-color);
            color: var(--text-color);
            position: fixed;
            transition: all 0.3s;
            z-index: 1000;
            border-right: 1px solid var(--border-color);
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: var(--primary-color);
            color: white;
        }
        
        .sidebar ul li a {
            padding: 15px;
            display: block;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar ul li a:hover {
            background-color: rgba(13, 110, 253, 0.1);
        }
        
        .sidebar ul li.active a {
            background-color: var(--primary-color);
            color: white;
        }
        
        .content {
            transition: all 0.3s;
        }
        
        .content.expanded {
            margin-left: 0;
        }
        
        .navbar {
            background-color: var(--card-bg-color);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-bottom: 1px solid var(--border-color);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
        }
        
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn-toggle-sidebar {
            margin-right: 15px;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg-color);
            color: var(--text-color);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
        }
        
        .nav-tabs .nav-link {
            margin-bottom: -1px;
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            color: var(--text-color);
        }
        
        .nav-tabs .nav-link:hover {
            border-color: var(--border-color) var(--border-color) var(--border-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: var(--card-bg-color);
            border-color: var(--border-color) var(--border-color) var(--card-bg-color);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .content {
                margin-left: 0;
            }
            .sidebar-header h3 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-theme' : ''; ?>">
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>NexusFlow</h3>
            </div>
            <ul class="list-unstyled components">
                <li>
                    <a href="../../index.php"><i class="fas fa-tachometer-alt me-2"></i> Gösterge Paneli</a>
                </li>
                <li>
                    <a href="./analiz.php"><i class="fas fa-chart-bar me-2"></i> Analitik</a>
                </li>
                <li>
                    <a href="./budget.php"><i class="fas fa-wallet me-2"></i> Bütçe</a>
                </li>
                <li>
                    <a href="./kullanıcı.php"><i class="fas fa-user me-2"></i> Kullanıcı</a>
                </li>
                <li class="active">
                    <a href="./ayarlar.php"><i class="fas fa-cog me-2"></i> Ayarlar</a>
                </li>
                <li>
                    <a href="./logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış yap</a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content" class="content w-100">
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-primary btn-toggle-sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-text ms-auto">
                        <i class="fas fa-user-circle me-2"></i> <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>
                    </span>
                </div>
            </nav>

            <div class="container-fluid py-4">
                <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?php echo $errorMessage; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $successMessage; ?>
                </div>
                <?php endif; ?>
                
                <h2 class="mb-4">Ayarlar</h2>
                
                <div class="card">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                                    <i class="fas fa-user me-2"></i> Profil
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab" aria-controls="appearance" aria-selected="false">
                                    <i class="fas fa-palette me-2"></i> Görünüm
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false">
                                    <i class="fas fa-bell me-2"></i> Bildirimler
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content p-4" id="settingsTabsContent">
                            <!-- Profil Ayarları -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <h4 class="mb-4">Profil Bilgileri</h4>
                                <form method="post" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">Ad</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Soyad</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">E-posta Adresi</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    </div>
                                    <hr class="my-4">
                                    <h5 class="mb-3">Şifre Değiştir</h5>
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mevcut Şifre</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                        <small class="text-muted">Şifrenizi değiştirmek istemiyorsanız bu alanları boş bırakın.</small>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">Yeni Şifre</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Değişiklikleri Kaydet
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Görünüm Ayarları -->
                            <div class="tab-pane fade" id="appearance" role="tabpanel" aria-labelledby="appearance-tab">
                                <h4 class="mb-4">Görünüm Ayarları</h4>
                                <form method="post" action="">
                                    <div class="mb-4">
                                        <label class="form-label">Tema</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="theme" id="theme_light" value="light" <?php echo $theme === 'light' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_light">
                                                    <i class="fas fa-sun me-2"></i> Açık Tema
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="theme" id="theme_dark" value="dark" <?php echo $theme === 'dark' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_dark">
                                                    <i class="fas fa-moon me-2"></i> Koyu Tema
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_theme" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Değişiklikleri Kaydet
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Bildirim Ayarları -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                                <h4 class="mb-4">Bildirim Ayarları</h4>
                                <form method="post" action="">
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">E-posta Bildirimleri</label>
                                        </div>
                                        <small class="text-muted">Bütçe güncellemeleri, önemli hatırlatmalar ve sistem bildirimleri için e-posta almak istiyorsanız bu seçeneği etkinleştirin.</small>
                                    </div>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_notifications" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Değişiklikleri Kaydet
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('content').classList.toggle('expanded');
            });
            
            // Tema değişikliğini anında uygula
            const themeRadios = document.querySelectorAll('input[name="theme"]');
            themeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'dark') {
                        document.body.classList.add('dark-theme');
                    } else {
                        document.body.classList.remove('dark-theme');
                    }
                });
            });
        });
    </script>
</body>
</html>