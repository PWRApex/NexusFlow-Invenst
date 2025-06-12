<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
redirectIfLoggedIn();

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // E-posta ve şifre kontrolü
    if (empty($email) || empty($password)) {
        setErrorMessage("E-posta ve şifre alanları zorunludur.");
    } else {
        try {
            // Kullanıcıyı veritabanında ara
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Şifre doğrulama (password_verify PHP'nin şifre doğrulama fonksiyonudur)
                if (password_verify($password, $user['password'])) {
                    // Oturum bilgilerini ayarla
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Beni hatırla seçeneği işlemi
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        
                        // Token'ı veritabanına kaydet
                        $updateStmt = $conn->prepare("UPDATE users SET remember_token = :token WHERE user_id = :user_id");
                        $updateStmt->bindParam(':token', $token);
                        $updateStmt->bindParam(':user_id', $user['user_id']);
                        $updateStmt->execute();
                        
                        // Cookie ayarla (30 gün)
                        setcookie('remember_token', $token, time() + (86400 * 30), "/");
                    }
                    
                    // Ana sayfaya yönlendir
                    header("Location: ../../index.php");
                    exit;
                } else {
                    setErrorMessage("Geçersiz e-posta veya şifre.");
                }
            } else {
                setErrorMessage("Geçersiz e-posta veya şifre.");
            }
        } catch(PDOException $e) {
            setErrorMessage("Giriş sırasında bir hata oluştu: " . $e->getMessage());
        }
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
    <title>NexusFlow - Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
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
        .login-image {
            background-color: #0d6efd;
            color: white;
            border-radius: 10px 0 0 10px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-form {
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
    <div class="container login-container">
        <div class="row">
            <div class="col-md-6 d-none d-md-block">
                <div class="login-image h-100">
                    <h2 class="mb-4">NexusFlow Bütçe Yönetimi</h2>
                    <p class="lead">Finansal özgürlüğünüzü yönetmek için en iyi araç. Gelir ve giderlerinizi takip edin, bütçenizi planlayın ve finansal hedeflerinize ulaşın.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card login-form">
                    <div class="card-body">
                        <a href="../../index.php" class="logo">NexusFlow</a>
                        <h3 class="card-title mb-4">Giriş Yap</h3>
                        
                        <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger">
                            <?php echo $errorMessage; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Beni hatırla</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                            <div class="mt-3 text-center">
                                <p>Hesabınız yok mu? <a href="singup.php">Kayıt Ol</a></p>
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