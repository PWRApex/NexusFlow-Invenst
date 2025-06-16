<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once 'config.php';

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
    
    // Son 5 geliri al
    $incomeStmt = $conn->prepare("SELECT * FROM incomes WHERE user_id = :user_id AND budget_id = :budget_id ORDER BY created_at DESC LIMIT 5");
    $incomeStmt->bindParam(':user_id', $user_id);
    $incomeStmt->bindParam(':budget_id', $budget_id);
    $incomeStmt->execute();
    $incomes = $incomeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son 5 gideri al
    $expenseStmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = :user_id AND budget_id = :budget_id ORDER BY created_at DESC LIMIT 5");
    $expenseStmt->bindParam(':user_id', $user_id);
    $expenseStmt->bindParam(':budget_id', $budget_id);
    $expenseStmt->execute();
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam gelir ve giderleri hesapla
    $totalIncomeStmt = $conn->prepare("SELECT SUM(amount) as total FROM incomes WHERE user_id = :user_id AND budget_id = :budget_id");
    $totalIncomeStmt->bindParam(':user_id', $user_id);
    $totalIncomeStmt->bindParam(':budget_id', $budget_id);
    $totalIncomeStmt->execute();
    $totalIncome = $totalIncomeStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    $totalExpenseStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = :user_id AND budget_id = :budget_id");
    $totalExpenseStmt->bindParam(':user_id', $user_id);
    $totalExpenseStmt->bindParam(':budget_id', $budget_id);
    $totalExpenseStmt->execute();
    $totalExpense = $totalExpenseStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
} catch(PDOException $e) {
    setErrorMessage("Bütçe bilgileri alınırken bir hata oluştu: " . $e->getMessage());
}

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

// Hata ve başarı mesajlarını al
$errorMessage = getErrorMessage();
$successMessage = getSuccessMessage();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Gösterge Paneli</title>
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
        
        .navbar {
        background-color: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
        
        .btn-light {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-light:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
        }

        .card:hover {
            transform: translateY(-5px);
        }
        
        .summary-card {
            background: linear-gradient(45deg, #4b6cb7, #182848);
            color: white;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
    
    </style>
</head>
<body>
    <style>
        /* NEW 1.02 SIDEBAR */
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
    
    .modal-content {
        background-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
            border: none;
            color: white;
            transition: all 0.3s;
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
        background-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .modal-footer {
        background-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
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
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>NexusFlow2</h3>
            </div>
            <ul class="list-unstyled components">
                <li class="active">
                    <a href="index.php">
                        <i class="fas fa-chart-line"></i> Gösterge Paneli
                    </a>
                </li>
                <li>
                    <a href="./assets/view/analiz.php">
                        <i class="fas fa-chart-bar"></i> Analiz
                    </a>
                </li>
                <li>
                    <a href="./assets/view/budget.php">
                        <i class="fas fa-wallet"></i> Bütçe
                    </a>
                </li>
                <li>
                    <a href="./assets/view/logout.php">
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
            <!--
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-dark btn-toggle-sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-text ms-auto">
                        <i class="fas fa-user-circle me-2"></i> <?php/* echo htmlspecialchars($first_name . ' ' . $last_name);*/ ?>
                    </span>
                </div>
            </nav>
    -->
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
                
                <h2 class="mb-4">Gösterge Paneli</h2>
                
                <!-- Bütçe Kartları -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card budget-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Bütçe</h5>
                                <p class="budget-amount"><?php echo number_format($total_budget, 2, ',', '.'); ?> ₺</p>
                                <a href="./assets/view/budget.php" class="btn btn-light">
                                    <i class="fas fa-wallet me-2"></i> Bütçe Yönetimi
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card income-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Gelir</h5>
                                <p class="budget-amount"><?php echo number_format($totalIncome, 2, ',', '.'); ?> ₺</p>
                                <a href="./assets/view/budget.php" class="btn btn-light">
                                    <i class="fas fa-plus-circle me-2"></i> Gelir Ekle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card expense-card">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Gider</h5>
                                <p class="budget-amount"><?php echo number_format($totalExpense, 2, ',', '.'); ?> ₺</p>
                                <a href="./assets/view/budget.php" class="btn btn-light">
                                    <i class="fas fa-minus-circle me-2"></i> Gider Ekle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gelir/Gider Listesi -->
                <div class="row">
                    <!-- Son Gelirler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-plus-circle text-success me-2"></i> Son Gelirler</span>
                                <a href="./assets/view/budget.php" class="btn btn-sm btn-outline-success">
                                    Tümünü Gör
                                </a>
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
                                            <span class="item-amount income-amount">+<?php echo number_format($income['amount'], 2, ',', '.'); ?> ₺</span>
                                        </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Son Giderler -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-minus-circle text-danger me-2"></i> Son Giderler</span>
                                <a href="./assets/view/budget.php" class="btn btn-sm btn-outline-danger">
                                    Tümünü Gör
                                </a>
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
                                            <span class="item-amount expense-amount">-<?php echo number_format($expense['amount'], 2, ',', '.'); ?> ₺</span>
                                        </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
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