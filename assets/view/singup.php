<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
redirectIfLoggedIn();

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $accept_terms = isset($_POST['accept_terms']) ? true : false;
    
    // Form doğrulama
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "Ad alanı zorunludur.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Soyad alanı zorunludur.";
    }
    
    if (empty($email)) {
        $errors[] = "E-posta alanı zorunludur.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi giriniz.";
    }
    
    if (empty($password)) {
        $errors[] = "Şifre alanı zorunludur.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Şifre en az 6 karakter olmalıdır.";
    }
    
    if (!$accept_terms) {
        $errors[] = "Kişisel verilerin işlenmesini kabul etmelisiniz.";
    }
    
    // Hata yoksa kayıt işlemine devam et
    if (empty($errors)) {
        try {
            // E-posta adresi daha önce kullanılmış mı kontrol et
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                setErrorMessage("Bu e-posta adresi zaten kullanılıyor.");
            } else {
                // Şifreyi hashle
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Kullanıcıyı veritabanına ekle
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (:first_name, :last_name, :email, :password)");
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->execute();
                
                // Yeni kullanıcı ID'sini al
                $user_id = $conn->lastInsertId();
                
                // Yeni kullanıcı için boş bir bütçe oluştur
                $budgetStmt = $conn->prepare("INSERT INTO budgets (user_id, total_amount) VALUES (:user_id, 0)");
                $budgetStmt->bindParam(':user_id', $user_id);
                $budgetStmt->execute();
                
                // Başarı mesajı ayarla ve giriş sayfasına yönlendir
                setSuccessMessage("Kayıt başarıyla tamamlandı. Şimdi giriş yapabilirsiniz.");
                header("Location: login.php");
                exit;
            }
        } catch(PDOException $e) {
            setErrorMessage("Kayıt sırasında bir hata oluştu: " . $e->getMessage());
        }
    } else {
        // Hataları birleştir ve hata mesajı olarak ayarla
        setErrorMessage(implode("<br>", $errors));
    }
}

// Hata mesajını al
$errorMessage = getErrorMessage();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Kayıt Ol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .signup-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 0;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-title {
            font-weight: 700;
            color: #343a40;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .form-control {
            padding: 12px;
            border-radius: 5px;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .signup-image {
            background-color: #0d6efd;
            color: white;
            border-radius: 10px 0 0 10px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .signup-form {
            padding: 40px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #0d6efd;
            text-decoration: none;
        }
        .home-link {
            display: inline-block;
            margin-top: 15px;
            color: #0d6efd;
            text-decoration: none;
        }
        .home-link:hover {
            text-decoration: underline;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container signup-container">
        <div class="row">
            <div class="col-md-6 d-none d-md-block">
                <div class="signup-image h-100">
                    <h2 class="mb-4">NexusFlow'a Hoş Geldiniz</h2>
                    <p class="lead">Finansal özgürlüğünüzü yönetmek için en iyi araç. Gelir ve giderlerinizi takip edin, bütçenizi planlayın ve finansal hedeflerinize ulaşın.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card signup-form">
                    <div class="card-body">
                        <a href="../../index.php" class="logo">NexusFlow</a>
                        <h3 class="card-title mb-4">Kayıt Ol</h3>
                        
                        <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger">
                            <?php echo $errorMessage; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Ad</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Soyad</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Şifreniz en az 6 karakter olmalıdır.</small>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="accept_terms" name="accept_terms" required>
                                <label class="form-check-label" for="accept_terms">Kişisel verilerimin işlenmesini kabul ediyorum</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
                            <div class="mt-3 text-center">
                                <p>Zaten hesabınız var mı? <a href="login.php">Giriş Yap</a></p>
                            </div>
                        </form>
                        <a href="../../index.php" class="home-link"><i class="fas fa-home me-1"></i> Ana Sayfaya Dön</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>