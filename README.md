# NexusFlow Bütçe Yönetim Sistemi

## Proje Hakkında

NexusFlow, kişisel bütçe yönetimi için geliştirilmiş bir web uygulamasıdır. Kullanıcılar gelir ve giderlerini takip edebilir, bütçelerini yönetebilirler.

## Kurulum Adımları

### 1. XAMPP Kurulumu

Eğer bilgisayarınızda XAMPP kurulu değilse, [XAMPP'ın resmi sitesinden](https://www.apachefriends.org/index.html) indirip kurabilirsiniz.

### 2. Projeyi XAMPP'a Taşıma

1. Bu projeyi `C:\xampp\htdocs\nexusflow` klasörüne kopyalayın.
2. Eğer farklı bir klasör adı kullanmak isterseniz, bağlantıları buna göre güncellemeniz gerekebilir.

### 3. MySQL Veritabanı Kurulumu

1. XAMPP Kontrol Paneli'ni açın ve Apache ile MySQL servislerini başlatın.
2. Tarayıcınızda `http://localhost/phpmyadmin` adresine gidin.
3. Sol menüden "Yeni" seçeneğine tıklayarak `nexusflow_db` adında yeni bir veritabanı oluşturun.
4. Oluşturduğunuz veritabanını seçin ve "İçe Aktar" sekmesine tıklayın.
5. "Dosya Seç" butonuna tıklayarak projedeki `database.sql` dosyasını seçin ve "Git" butonuna tıklayın.

### 4. Veritabanı Bağlantı Ayarları

1. Projedeki `config.php` dosyasını açın.
2. Veritabanı bağlantı bilgilerini kontrol edin ve gerekirse düzenleyin:
   ```php
   $host = 'localhost'; // XAMPP için varsayılan host
   $dbname = 'nexusflow_db'; // Veritabanı adı
   $username = 'root'; // XAMPP için varsayılan kullanıcı adı
   $password = ''; // XAMPP için varsayılan şifre (boş)
   ```

### 5. Uygulamayı Çalıştırma

1. Tarayıcınızda `http://localhost/nexusflow` adresine gidin.
2. Karşınıza giriş ekranı gelecektir.
3. Veritabanında örnek bir kullanıcı oluşturulmuştur:
   - E-posta: `test@example.com`
   - Şifre: `password`
4. Bu bilgilerle giriş yapabilir veya yeni bir hesap oluşturabilirsiniz.

## Özellikler

- Kullanıcı kaydı ve girişi
- Bütçe yönetimi
- Gelir ve gider takibi
- Gelir ve gider kategorileri
- Gösterge paneli ile genel bakış

## Teknik Detaylar

- PHP 7.4+ ile geliştirilmiştir
- MySQL veritabanı kullanılmaktadır
- Bootstrap 5 ile responsive tasarım
- Font Awesome ikonları
- PDO ile güvenli veritabanı bağlantısı

## Güvenlik Önlemleri

- Şifreler PHP'nin `password_hash()` fonksiyonu ile hashlenmektedir
- XSS saldırılarına karşı veri temizleme
- SQL enjeksiyonlarına karşı prepared statements
- Oturum güvenliği

## Sorun Giderme

- Eğer "Bağlantı hatası" alıyorsanız, veritabanı bağlantı bilgilerinizi kontrol edin.
- Sayfa yüklenme sorunları yaşıyorsanız, XAMPP'ta Apache ve MySQL servislerinin çalıştığından emin olun.
- Dosya yolu hatası alıyorsanız, projeyi doğru klasöre kopyaladığınızdan emin olun.

## İletişim

Sorularınız veya önerileriniz için lütfen iletişime geçin.



<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
redirectIfNotLoggedIn();

// Kullanıcı bilgilerini al
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Bütçe bilgilerini al
try {
    // Kullanıcının bütçesini al
    $budgetStmt = $conn->prepare("SELECT * FROM budgets WHERE user_id = :user_id");
    $budgetStmt->bindParam(':user_id', $user_id);
    $budgetStmt->execute();
    
    if ($budgetStmt->rowCount() > 0) {
        $budget = $budgetStmt->fetch(PDO::FETCH_ASSOC);
        $budget_id = $budget['budget_id'];
        $total_budget = $budget['total_amount'];
    } else {
        // Kullanıcı için yeni bir bütçe oluştur
        $createBudgetStmt = $conn->prepare("INSERT INTO budgets (user_id, total_amount) VALUES (:user_id, 0)");
        $createBudgetStmt->bindParam(':user_id', $user_id);
        $createBudgetStmt->execute();
        
        $budget_id = $conn->lastInsertId();
        $total_budget = 0;
    }
    
    // Gelirleri al
    $incomeStmt = $conn->prepare("SELECT * FROM incomes WHERE user_id = :user_id AND budget_id = :budget_id ORDER BY created_at DESC");
    $incomeStmt->bindParam(':user_id', $user_id);
    $incomeStmt->bindParam(':budget_id', $budget_id);
    $incomeStmt->execute();
    $incomes = $incomeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Giderleri al
    $expenseStmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = :user_id AND budget_id = :budget_id ORDER BY created_at DESC");
    $expenseStmt->bindParam(':user_id', $user_id);
    $expenseStmt->bindParam(':budget_id', $budget_id);
    $expenseStmt->execute();
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam gelir ve giderleri hesapla
    $totalIncome = 0;
    foreach ($incomes as $income) {
        $totalIncome += $income['amount'];
    }
    
    $totalExpense = 0;
    foreach ($expenses as $expense) {
        $totalExpense += $expense['amount'];
    }
    
    // Gelir ve gider tiplerini gruplandır
} catch(PDOException $e) {
    setErrorMessage("Bütçe bilgileri alınırken bir hata oluştu: " . $e->getMessage());
}

// Bütçe güncelleme işlemi
if (isset($_POST['update_budget'])) {
    $new_budget = floatval($_POST['new_budget']);
    
    try {
        $updateStmt = $conn->prepare("UPDATE budgets SET total_amount = :total_amount WHERE budget_id = :budget_id AND user_id = :user_id");
        $updateStmt->bindParam(':total_amount', $new_budget);
        $updateStmt->bindParam(':budget_id', $budget_id);
        $updateStmt->bindParam(':user_id', $user_id);
        $updateStmt->execute();
        
        setSuccessMessage("Bütçe başarıyla güncellendi.");
        header("Location: budget.php");
        exit;
    } catch(PDOException $e) {
        setErrorMessage("Bütçe güncellenirken bir hata oluştu: " . $e->getMessage());
    }
}

// Gelir ekleme işlemi
if (isset($_POST['add_income'])) {
    $income_type = sanitize($_POST['income_type']);
    $income_amount = floatval($_POST['income_amount']);
    $description = isset($_POST['income_description']) ? sanitize($_POST['income_description']) : null;
    
    if ($income_amount <= 0) {
        setErrorMessage("Gelir tutarı sıfırdan büyük olmalıdır.");
    } else {
        try {
            // Geliri veritabanına ekle
            $stmt = $conn->prepare("INSERT INTO incomes (user_id, budget_id, income_type, amount, description) VALUES (:user_id, :budget_id, :income_type, :amount, :description)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':budget_id', $budget_id);
            $stmt->bindParam(':income_type', $income_type);
            $stmt->bindParam(':amount', $income_amount);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            // Toplam bütçeyi güncelle
            $new_total = $total_budget + $income_amount;
            $updateStmt = $conn->prepare("UPDATE budgets SET total_amount = :total_amount WHERE budget_id = :budget_id");
            $updateStmt->bindParam(':total_amount', $new_total);
            $updateStmt->bindParam(':budget_id', $budget_id);
            $updateStmt->execute();
            
            setSuccessMessage("Gelir başarıyla eklendi.");
            header("Location: budget.php");
            exit;
        } catch(PDOException $e) {
            setErrorMessage("Gelir eklenirken bir hata oluştu: " . $e->getMessage());
        }
    }
}

// Gider ekleme işlemi
if (isset($_POST['add_expense'])) {
    $expense_type = sanitize($_POST['expense_type']);
    $expense_amount = floatval($_POST['expense_amount']);
    $description = isset($_POST['expense_description']) ? sanitize($_POST['expense_description']) : null;
    
    if ($expense_amount <= 0) {
        setErrorMessage("Gider tutarı sıfırdan büyük olmalıdır.");
    } else {
        try {
            // Gideri veritabanına ekle
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, budget_id, expense_type, amount, description) VALUES (:user_id, :budget_id, :expense_type, :amount, :description)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':budget_id', $budget_id);
            $stmt->bindParam(':expense_type', $expense_type);
            $stmt->bindParam(':amount', $expense_amount);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            // Toplam bütçeyi güncelle
            $new_total = $total_budget - $expense_amount;
            $updateStmt = $conn->prepare("UPDATE budgets SET total_amount = :total_amount WHERE budget_id = :budget_id");
            $updateStmt->bindParam(':total_amount', $new_total);
            $updateStmt->bindParam(':budget_id', $budget_id);
            $updateStmt->execute();
            
            setSuccessMessage("Gider başarıyla eklendi.");
            header("Location: budget.php");
            exit;
        } catch(PDOException $e) {
            setErrorMessage("Gider eklenirken bir hata oluştu: " . $e->getMessage());
        }
    }
}

// Gelir silme işlemi
if (isset($_POST['delete_income'])) {
    $income_id = intval($_POST['income_id']);
    $income_amount = floatval($_POST['income_amount']);
    
    try {
        // Geliri veritabanından sil
        $stmt = $conn->prepare("DELETE FROM incomes WHERE income_id = :income_id AND user_id = :user_id");
        $stmt->bindParam(':income_id', $income_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Toplam bütçeyi güncelle
        $new_total = $total_budget - $income_amount;
        $updateStmt = $conn->prepare("UPDATE budgets SET total_amount = :total_amount WHERE budget_id = :budget_id");
        $updateStmt->bindParam(':total_amount', $new_total);
        $updateStmt->bindParam(':budget_id', $budget_id);
        $updateStmt->execute();
        
        setSuccessMessage("Gelir başarıyla silindi.");
        header("Location: budget.php");
        exit;
    } catch(PDOException $e) {
        setErrorMessage("Gelir silinirken bir hata oluştu: " . $e->getMessage());
    }
}

// Gider silme işlemi
if (isset($_POST['delete_expense'])) {
    $expense_id = intval($_POST['expense_id']);
    $expense_amount = floatval($_POST['expense_amount']);
    
    try {
        // Gideri veritabanından sil
        $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_id = :expense_id AND user_id = :user_id");
        $stmt->bindParam(':expense_id', $expense_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Toplam bütçeyi güncelle
        $new_total = $total_budget + $expense_amount;
        $updateStmt = $conn->prepare("UPDATE budgets SET total_amount = :total_amount WHERE budget_id = :budget_id");
        $updateStmt->bindParam(':total_amount', $new_total);
        $updateStmt->bindParam(':budget_id', $budget_id);
        $updateStmt->execute();
        
        setSuccessMessage("Gider başarıyla silindi.");
        header("Location: budget.php");
        exit;
    } catch(PDOException $e) {
        setErrorMessage("Gider silinirken bir hata oluştu: " . $e->getMessage());
    }
}

// Hata ve başarı mesajlarını al
$errorMessage = getErrorMessage();
$successMessage = getSuccessMessage();

// Gelir ve gider tiplerine göre ikon belirleme fonksiyonları
function getIncomeIcon($type) {
    switch ($type) {
        case 'salary':
            return 'fa-money-bill-wave';
        case 'bonus':
            return 'fa-gift';
        case 'investment':
            return 'fa-chart-line';
        case 'other':
        default:
            return 'fa-plus-circle';
    }
}

function getExpenseIcon($type) {
    switch ($type) {
        case 'shopping':
            return 'fa-shopping-cart';
        case 'bills':
            return 'fa-file-invoice-dollar';
        case 'rent':
            return 'fa-home';
        case 'food':
            return 'fa-utensils';
        case 'transport':
            return 'fa-car';
        case 'other':
        default:
            return 'fa-minus-circle';
    }
}

// Gelir ve gider tiplerinin Türkçe karşılıkları
function getIncomeTypeName($type) {
    switch ($type) {
        case 'salary':
            return 'Maaş';
        case 'bonus':
            return 'Bonus';
        case 'investment':
            return 'Yatırım';
        case 'other':
        default:
            return 'Diğer';
    }
}

function getExpenseTypeName($type) {
    switch ($type) {
        case 'shopping':
            return 'Alışveriş';
        case 'bills':
            return 'Faturalar';
        case 'rent':
            return 'Kira';
        case 'food':
            return 'Yemek';
        case 'transport':
            return 'Ulaşım';
        case 'other':
        default:
            return 'Diğer';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Bütçe Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding: 20px 0;
            background: linear-gradient(180deg, #2c3e50, #3498db);
            color: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .list-unstyled.components {
            padding: 20px 0;
        }
        
        .list-unstyled.components li {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .list-unstyled.components li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .list-unstyled.components li a:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .list-unstyled.components li.active a {
            background: rgba(255,255,255,0.2);
            font-weight: bold;
        }
        
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
                position: fixed;
                top: 0;
                height: 100vh;
                overflow-y: auto;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .content {
                margin-left: 0;
            }
            
            .content.active {
                margin-left: 250px;
            }
            
            .btn-toggle-sidebar {
                display: block;
            }
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .budget-card {
            background: linear-gradient(45deg, #4b6cb7, #182848);
            color: white;
        }
        
        .income-card {
            background: linear-gradient(45deg, #134e5e, #71b280);
            color: white;
        }
        
        .expense-card {
            background: linear-gradient(45deg, #cb2d3e, #ef473a);
            color: white;
        }
        
        .budget-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 15px 0;
        }
        
        .btn-light {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-light:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    :root {
        --primary-color: #0d6efd;
        --success-color: #198754;
        --danger-color: #dc3545;
        --dark-color: #343a40;
        --light-color: #f8f9fa;
        --border-radius: 10px;
        --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        --transition: all 0.3s ease;
    }
    
    body {
        background-color: var(--light-color);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .sidebar {
        width: 250px;
        height: 100vh;
        background-color: var(--dark-color);
        color: white;
        position: fixed;
        transition: var(--transition);
        z-index: 1000;
    }
    
    .sidebar.collapsed {
        margin-left: -250px;
    }
    
    .sidebar-header {
        padding: 20px;
        background-color: #212529;
    }
    
    .sidebar ul li a {
        padding: 15px;
        display: block;
        color: white;
        text-decoration: none;
        transition: var(--transition);
        border-left: 3px solid transparent;
    }
    
    .sidebar ul li a:hover {
        background-color: #495057;
        border-left: 3px solid var(--primary-color);
    }
    
    .sidebar ul li.active a {
        background-color: var(--primary-color);
        border-left: 3px solid white;
    }
    
    .content {
        margin-left: 250px;
        transition: var(--transition);
    }
    
    .content.expanded {
        margin-left: 0;
    }
    
    .navbar {
        background-color: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .card {
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 20px;
        border: none;
        overflow: hidden;
    }
    
    .card-header {
        background-color: var(--light-color);
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        font-weight: 600;
        padding: 15px 20px;
    }
    
    .budget-card {
        background-color: var(--primary-color);
        color: white;
        transition: var(--transition);
    }
    
    .budget-card:hover {
        transform: translateY(-5px);
    }
    
    .income-card {
        background-color: var(--success-color);
        color: white;
        transition: var(--transition);
    }
    
    .income-card:hover {
        transform: translateY(-5px);
    }
    
    .expense-card {
        background-color: var(--danger-color);
        color: white;
        transition: var(--transition);
    }
    
    .expense-card:hover {
        transform: translateY(-5px);
    }
    
    .budget-amount {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 15px 0;
    }
    
    .list-group-item {
        border: none;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        margin-bottom: 0;
        padding: 15px 20px;
        transition: var(--transition);
    }
    
    .list-group-item:hover {
        background-color: rgba(0, 0, 0, 0.03);
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    .income-item {
        border-left: 4px solid var(--success-color);
    }
    
    .expense-item {
        border-left: 4px solid var(--danger-color);
    }
    
    .item-amount {
        font-weight: 600;
    }
    
    .income-amount {
        color: var(--success-color);
    }
    
    .expense-amount {
        color: var(--danger-color);
    }
    
    .btn-toggle-sidebar {
        margin-right: 15px;
    }
    
    .modal-header {
        background-color: var(--light-color);
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .modal-footer {
        background-color: var(--light-color);
        border-top: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .btn {
        border-radius: 5px;
        padding: 8px 16px;
        font-weight: 500;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    
    .btn-success {
        background-color: var(--success-color);
        border-color: var(--success-color);
    }
    
    .btn-danger {
        background-color: var(--danger-color);
        border-color: var(--danger-color);
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
    
    .transaction-date {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .btn-action {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .filter-section {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: var(--box-shadow);
    }
    
    .summary-card {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--box-shadow);
        text-align: center;
    }
    
    .summary-title {
        font-size: 1rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    
    .summary-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0;
    }
    
    .positive-value {
        color: var(--success-color);
    }
    
    .negative-value {
        color: var(--danger-color);
    }
    
    .neutral-value {
        color: var(--primary-color);
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
        
        .budget-amount {
            font-size: 2rem;
        }
    }
</style>
</head>
<body>
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>NexusFlow</h3>
            </div>
            <ul class="list-unstyled components">
                <li>
                    <a href="../../index.php">
                        <i class="fas fa-chart-line"></i> Gösterge Paneli
                    </a>
                </li>
                <li>
                    <a href="analiz.php">
                        <i class="fas fa-chart-bar"></i> Analiz
                    </a>
                </li>
                <li class="active">
                    <a href="budget.php">
                        <i class="fas fa-wallet"></i> Bütçe
                    </a>
                </li>
                <li>
                    <a href="gelirler.php">
                        <i class="fas fa-money-bill-wave"></i> Gelirler
                    </a>
                </li>
                <li>
                    <a href="giderler.php">
                        <i class="fas fa-shopping-cart"></i> Giderler
                    </a>
                </li>
                <li>
                    <a href="cikis.php">
                        <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content" class="content w-100">
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-dark btn-toggle-sidebar">
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
                
                <h2 class="mb-4">Bütçe Yönetimi</h2>
                
                <!-- Bütçe Kartları -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card budget-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Bütçe</h5>
                                <p class="budget-amount"><?php echo number_format($total_budget, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#updateBudgetModal">
                                    <i class="fas fa-edit me-2"></i> Bütçe Güncelle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card income-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Gelir</h5>
                                <p class="budget-amount"><?php echo number_format($totalIncome, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                                    <i class="fas fa-plus-circle me-2"></i> Gelir Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card expense-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Gider</h5>
                                <p class="budget-amount"><?php echo number_format($totalExpense, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    <i class="fas fa-minus-circle me-2"></i> Gider Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Özet Kartları -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Aylık Tasarruf</h6>
                                <h3 class="card-title mb-4"><?php echo number_format($totalIncome - $totalExpense, 2, ',', '.'); ?> ₺</h3>
                                <div class="d-flex align-items-center">
                                    <span class="text-success me-2">
                                        <i class="fas fa-arrow-up"></i>
                                    </span>
                                    <span>%<?php echo $totalExpense > 0 ? number_format(($totalIncome - $totalExpense) / $totalExpense * 100, 1) : 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Bütçe Kullanımı</h6>
                                <h3 class="card-title mb-4"><?php echo $total_budget > 0 ? number_format($totalExpense / $total_budget * 100, 1) : 0; ?>%</h3>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $total_budget > 0 ? min($totalExpense / $total_budget * 100, 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">En Yüksek Gider</h6>
                                <?php
                                $maxExpenseStmt = $conn->prepare("SELECT expense_type, SUM(amount) as total FROM expenses WHERE user_id = :user_id GROUP BY expense_type ORDER BY total DESC LIMIT 1");
                                $maxExpenseStmt->bindParam(':user_id', $user_id);
                                $maxExpenseStmt->execute();
                                $maxExpense = $maxExpenseStmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <h3 class="card-title mb-4"><?php echo $maxExpense ? getExpenseTypeName($maxExpense['expense_type']) : 'Yok'; ?></h3>
                                <div class="d-flex align-items-center">
                                    <i class="fas <?php echo $maxExpense ? getExpenseIcon($maxExpense['expense_type']) : 'fa-info-circle'; ?> me-2"></i>
                                    <span><?php echo $maxExpense ? number_format($maxExpense['total'], 2, ',', '.') . ' ₺' : '0 ₺'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">En Yüksek Gelir</h6>
                                <?php
                                $maxIncomeStmt = $conn->prepare("SELECT income_type, SUM(amount) as total FROM incomes WHERE user_id = :user_id GROUP BY income_type ORDER BY total DESC LIMIT 1");
                                $maxIncomeStmt->bindParam(':user_id', $user_id);
                                $maxIncomeStmt->execute();
                                $maxIncome = $maxIncomeStmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <h3 class="card-title mb-4"><?php echo $maxIncome ? getIncomeTypeName($maxIncome['income_type']) : 'Yok'; ?></h3>
                                <div class="d-flex align-items-center">
                                    <i class="fas <?php echo $maxIncome ? getIncomeIcon($maxIncome['income_type']) : 'fa-info-circle'; ?> me-2"></i>
                                    <span><?php echo $maxIncome ? number_format($maxIncome['total'], 2, ',', '.') . ' ₺' : '0 ₺'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Gelir/Gider Dağılımı</h5>
                                <div class="chart-container">
                                    <canvas id="incomeExpenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Gider Kategorileri</h5>
                                <div class="chart-container">
                                    <canvas id="expenseTypesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gelir/Gider Listesi -->
                <div class="row">
                    <!-- Gelirler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-plus-circle text-success me-2"></i> Gelirler</span>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                                    <i class="fas fa-plus me-1"></i> Gelir Ekle
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($incomes)): ?>
                                    <li class="list-group-item text-center text-muted">Henüz gelir kaydı bulunmamaktadır.</li>
                                    <?php else: ?>
                                        <?php foreach ($incomes as $income): ?>
                                        <li class="list-group-item income-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas <?php echo getIncomeIcon($income['income_type']); ?> me-2"></i>
                                                <span><?php echo getIncomeTypeName($income['income_type']); ?></span>
                                                <?php if (!empty($income['description'])): ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($income['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="item-amount income-amount me-3">+<?php echo number_format($income['amount'], 2, ',', '.'); ?> ₺</span>
                                                <form method="post" class="delete-form" onsubmit="return confirm('Bu geliri silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="income_id" value="<?php echo $income['income_id']; ?>">
                                                    <input type="hidden" name="income_amount" value="<?php echo $income['amount']; ?>">
                                                    <button type="submit" name="delete_income" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Giderler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-minus-circle text-danger me-2"></i> Giderler</span>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    <i class="fas fa-plus me-1"></i> Gider Ekle
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($expenses)): ?>
                                    <li class="list-group-item text-center text-muted">Henüz gider kaydı bulunmamaktadır.</li>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <li class="list-group-item expense-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas <?php echo getExpenseIcon($expense['expense_type']); ?> me-2"></i>
                                                <span><?php echo getExpenseTypeName($expense['expense_type']); ?></span>
                                                <?php if (!empty($expense['description'])): ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($expense['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="item-amount expense-amount me-3">-<?php echo number_format($expense['amount'], 2, ',', '.'); ?> ₺</span>
                                                <form method="post" class="delete-form" onsubmit="return confirm('Bu gideri silmek istediğinizden emin misiniz?');">
                                                    <input type="hidden" name="expense_id" value="<?php echo $expense['expense_id']; ?>">
                                                    <input type="hidden" name="expense_amount" value="<?php echo $expense['amount']; ?>">
                                                    <button type="submit" name="delete_expense" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bütçe Güncelleme Modal -->
    <div class="modal fade" id="budgetModal" tabindex="-1" aria-labelledby="budgetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="budgetModalLabel"><i class="fas fa-wallet me-2"></i> Bütçe Güncelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new_budget" class="form-label">Yeni Bütçe Tutarı</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" id="new_budget" name="new_budget" value="<?php echo $total_budget; ?>" required>
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="update_budget" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Gelir Ekleme Modal -->
    <div class="modal fade" id="incomeModal" tabindex="-1" aria-labelledby="incomeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="incomeModalLabel"><i class="fas fa-plus-circle text-success me-2"></i> Yeni Gelir Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="income_type" class="form-label">Gelir Tipi</label>
                            <select class="form-select" id="income_type" name="income_type" required>
                                <option value="salary">Maaş</option>
                                <option value="bonus">Bonus</option>
                                <option value="investment">Yatırım</option>
                                <option value="other">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="income_amount" class="form-label">Tutar</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="income_amount" name="income_amount" required>
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="income_description" class="form-label">Açıklama (İsteğe bağlı)</label>
                            <textarea class="form-control" id="income_description" name="income_description" rows="2" placeholder="Gelir hakkında kısa bir açıklama yazabilirsiniz"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_income" class="btn btn-success">
                            <i class="fas fa-plus-circle me-2"></i> Gelir Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Gider Ekleme Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="expenseModalLabel"><i class="fas fa-minus-circle text-danger me-2"></i> Yeni Gider Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="expense_type" class="form-label">Gider Tipi</label>
                            <select class="form-select" id="expense_type" name="expense_type" required>
                                <option value="shopping">Alışveriş</option>
                                <option value="bills">Faturalar</option>
                                <option value="rent">Kira</option>
                                <option value="food">Yemek</option>
                                <option value="transport">Ulaşım</option>
                                <option value="other">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="expense_amount" class="form-label">Tutar</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="expense_amount" name="expense_amount" required>
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="expense_description" class="form-label">Açıklama (İsteğe bağlı)</label>
                            <textarea class="form-control" id="expense_description" name="expense_description" rows="2" placeholder="Gider hakkında kısa bir açıklama yazabilirsiniz"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_expense" class="btn btn-danger">
                            <i class="fas fa-minus-circle me-2"></i> Gider Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('content').classList.toggle('expanded');
            });

            // Gelir/Gider Grafiği
            const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
            new Chart(incomeExpenseCtx, {
                type: 'bar',
                data: {
                    labels: ['Son 30 Gün'],
                    datasets: [
                        {
                            label: 'Gelir',
                            data: [<?php echo $totalIncome; ?>],
                            backgroundColor: '#198754',
                            borderColor: '#198754',
                            borderWidth: 1
                        },
                        {
                            label: 'Gider',
                            data: [<?php echo $totalExpense; ?>],
                            backgroundColor: '#dc3545',
                            borderColor: '#dc3545',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' });
                                }
                            }
                        }
                    }
                }
            });

            // Gider Kategorileri Grafiği
            <?php
            $expenseTypesStmt = $conn->prepare("SELECT expense_type, SUM(amount) as total FROM expenses WHERE user_id = :user_id GROUP BY expense_type");
            $expenseTypesStmt->bindParam(':user_id', $user_id);
            $expenseTypesStmt->execute();
            $expenseTypes = $expenseTypesStmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            $backgroundColor = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];

            foreach ($expenseTypes as $index => $type) {
                $labels[] = getExpenseTypeName($type['expense_type']);
                $data[] = $type['total'];
            }
            ?>

            const expenseTypesCtx = document.getElementById('expenseTypesChart').getContext('2d');
            new Chart(expenseTypesCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: <?php echo json_encode($backgroundColor); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>


php mailer ekle doğrolamak içins