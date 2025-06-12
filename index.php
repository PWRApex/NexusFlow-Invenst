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
        }
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            color: white;
            position: fixed;
            transition: all 0.3s;
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
            transition: all 0.3s;
        }
        .sidebar ul li a:hover {
            background-color: #495057;
        }
        .sidebar ul li.active a {
            background-color: #0d6efd;
        }
        .content {
            transition: all 0.3s;
        }
        .content.expanded {
            margin-left: 0;
        }
        .navbar {
            background-color: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: 600;
        }
        .budget-card {
            background-color: #0d6efd;
            color: white;
        }
        .income-card {
            background-color: #198754;
            color: white;
        }
        .expense-card {
            background-color: #dc3545;
            color: white;
        }
        .budget-amount {
            font-size: 2rem;
            font-weight: 700;
        }
        .list-group-item {
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 0;
            padding: 15px;
        }
        .list-group-item:last-child {
            border-bottom: none;
        }
        .income-item {
            border-left: 4px solid #198754;
        }
        .expense-item {
            border-left: 4px solid #dc3545;
        }
        .item-amount {
            font-weight: 600;
        }
        .income-amount {
            color: #198754;
        }
        .expense-amount {
            color: #dc3545;
        }
        .btn-toggle-sidebar {
            margin-right: 15px;
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
<body>
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>NexusFlow</h3>
            </div>
            <ul class="list-unstyled components">
                <li class="active">
                    <a href="index.php"><i class="fas fa-tachometer-alt me-2"></i> Gösterge Paneli</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-chart-bar me-2"></i> Analitik</a>
                </li>
                <li>
                    <a href="./assets/view/budget.php"><i class="fas fa-wallet me-2"></i> Bütçe</a>
                </li>
                <li>
                    <a href="./assets/view/singup.php"><i class="fas fa-user me-2"></i> Kullanıcı</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-cog me-2"></i> Ayarlar</a>
                </li>
                <li>
                    <a href="./assets/view/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Çıkış yap</a>
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
                
                <h2 class="mb-4">Gösterge Paneli</h2>
                
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
        });
    </script>
</body>
</html>