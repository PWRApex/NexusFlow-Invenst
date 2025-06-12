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
                    <a href="../../index.php"><i class="fas fa-tachometer-alt me-2"></i> Gösterge Paneli</a>
                </li>
                <li>
                    <a href="./analiz.php"><i class="fas fa-chart-bar me-2"></i> Analitik</a>
                </li>
                <li class="active">
                    <a href="./budget.php"><i class="fas fa-wallet me-2"></i> Bütçe</a>
                </li>
                <li>
                    <a href="./kullanıcı.php"><i class="fas fa-user me-2"></i> Kullanıcı</a>
                </li>
                <li>
                    <a href="./ayarlar.php"><i class="fas fa-cog me-2"></i> Ayarlar</a>
                </li>
                <li>
                    <a href="./logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış yap</a>
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
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-wallet me-2"></i> Bütçe Yönetimi</h2>
                    <div>
                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#incomeModal">
                            <i class="fas fa-plus-circle me-2"></i> Gelir Ekle
                        </button>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#expenseModal">
                            <i class="fas fa-minus-circle me-2"></i> Gider Ekle
                        </button>
                    </div>
                </div>
                
                <!-- Özet Kartları -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card budget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title"><i class="fas fa-wallet me-2"></i> Toplam Bütçe</h5>
                                <p class="budget-amount"><?php echo number_format($total_budget, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#budgetModal">
                                    <i class="fas fa-edit me-2"></i> Bütçe Güncelle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card income-card">
                            <div class="card-body text-center">
                                <h5 class="card-title"><i class="fas fa-plus-circle me-2"></i> Toplam Gelir</h5>
                                <p class="budget-amount"><?php echo number_format($totalIncome, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#incomeModal">
                                    <i class="fas fa-plus me-2"></i> Gelir Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card expense-card">
                            <div class="card-body text-center">
                                <h5 class="card-title"><i class="fas fa-minus-circle me-2"></i> Toplam Gider</h5>
                                <p class="budget-amount"><?php echo number_format($totalExpense, 2, ',', '.'); ?> ₺</p>
                                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#expenseModal">
                                    <i class="fas fa-minus me-2"></i> Gider Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grafikler -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i> Gelir Dağılımı
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="incomeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i> Gider Dağılımı
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="expenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Gelirler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-plus-circle text-success me-2"></i> Gelirler</span>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#incomeModal">
                                    <i class="fas fa-plus"></i> Yeni Gelir Ekle
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($incomes)): ?>
                                    <li class="list-group-item text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i> Henüz gelir kaydı bulunmamaktadır.
                                    </li>
                                    <?php else: ?>
                                        <?php foreach ($incomes as $income): ?>
                                        <li class="list-group-item income-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas <?php echo getIncomeIcon($income['income_type']); ?> me-2"></i>
                                                    <span class="fw-bold"><?php echo getIncomeTypeName($income['income_type']); ?></span>
                                                </div>
                                                <?php if (!empty($income['description'])): ?>
                                                <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($income['description']); ?></small>
                                                <?php endif; ?>
                                                <small class="transaction-date d-block mt-1">
                                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('d.m.Y H:i', strtotime($income['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="item-amount income-amount d-block">+<?php echo number_format($income['amount'], 2, ',', '.'); ?> ₺</span>
                                                <div class="action-buttons mt-2">
                                                    <form method="post" action="" onsubmit="return confirm('Bu geliri silmek istediğinizden emin misiniz?');">
                                                        <input type="hidden" name="income_id" value="<?php echo $income['income_id']; ?>">
                                                        <input type="hidden" name="income_amount" value="<?php echo $income['amount']; ?>">
                                                        <button type="submit" name="delete_income" class="btn btn-sm btn-outline-danger btn-action">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#expenseModal">
                                    <i class="fas fa-plus"></i> Yeni Gider Ekle
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($expenses)): ?>
                                    <li class="list-group-item text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i> Henüz gider kaydı bulunmamaktadır.
                                    </li>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <li class="list-group-item expense-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas <?php echo getExpenseIcon($expense['expense_type']); ?> me-2"></i>
                                                    <span class="fw-bold"><?php echo getExpenseTypeName($expense['expense_type']); ?></span>
                                                </div>
                                                <?php if (!empty($expense['description'])): ?>
                                                <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($expense['description']); ?></small>
                                                <?php endif; ?>
                                                <small class="transaction-date d-block mt-1">
                                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('d.m.Y H:i', strtotime($expense['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="item-amount expense-amount d-block">-<?php echo number_format($expense['amount'], 2, ',', '.'); ?> ₺</span>
                                                <div class="action-buttons mt-2">
                                                    <form method="post" action="" onsubmit="return confirm('Bu gideri silmek istediğinizden emin misiniz?');">
                                                        <input type="hidden" name="expense_id" value="<?php echo $expense['expense_id']; ?>">
                                                        <input type="hidden" name="expense_amount" value="<?php echo $expense['amount']; ?>">
                                                        <button type="submit" name="delete_expense" class="btn btn-sm btn-outline-danger btn-action">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('content').classList.toggle('expanded');
            });
            
            // Gelir grafiği
            const incomeCtx = document.getElementById('incomeChart').getContext('2d');
            const incomeData = {
                labels: [
                    <?php 
                    foreach ($incomeByType as $type => $amount) {
                        echo "'" . getIncomeTypeName($type) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Gelir Dağılımı',
                    data: [
                        <?php 
                        foreach ($incomeByType as $amount) {
                            echo $amount . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#20c997',
                        '#17a2b8',
                        '#6610f2'
                    ],
                    borderWidth: 1
                }]
            };
            
            const incomeChart = new Chart(incomeCtx, {
                type: 'doughnut',
                data: incomeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Gider grafiği
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            const expenseData = {
                labels: [
                    <?php 
                    foreach ($expenseByType as $type => $amount) {
                        echo "'" . getExpenseTypeName($type) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Gider Dağılımı',
                    data: [
                        <?php 
                        foreach ($expenseByType as $amount) {
                            echo $amount . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#ffc107',
                        '#e83e8c',
                        '#6f42c1',
                        '#6c757d'
                    ],
                    borderWidth: 1
                }]
            };
            
            const expenseChart = new Chart(expenseCtx, {
                type: 'doughnut',
                data: expenseData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>