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
    $created_at = $user['created_at'];
    
    // Kullanıcının toplam bütçesini al
    $budgetStmt = $conn->prepare("SELECT * FROM budgets WHERE user_id = :user_id");
    $budgetStmt->bindParam(':user_id', $user_id);
    $budgetStmt->execute();
    
    if ($budgetStmt->rowCount() > 0) {
        $budget = $budgetStmt->fetch(PDO::FETCH_ASSOC);
        $budget_id = $budget['budget_id'];
        $total_budget = $budget['total_amount'];
    } else {
        $total_budget = 0;
    }
    
    // Toplam gelir ve giderleri hesapla
    $totalIncomeStmt = $conn->prepare("SELECT SUM(amount) as total FROM incomes WHERE user_id = :user_id");
    $totalIncomeStmt->bindParam(':user_id', $user_id);
    $totalIncomeStmt->execute();
    $totalIncome = $totalIncomeStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    $totalExpenseStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = :user_id");
    $totalExpenseStmt->bindParam(':user_id', $user_id);
    $totalExpenseStmt->execute();
    $totalExpense = $totalExpenseStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    // Toplam işlem sayısını hesapla
    $totalTransactionsStmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM incomes WHERE user_id = :user_id) +
        (SELECT COUNT(*) FROM expenses WHERE user_id = :user_id) as total");
    $totalTransactionsStmt->bindParam(':user_id', $user_id);
    $totalTransactionsStmt->execute();
    $totalTransactions = $totalTransactionsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    // Son 5 işlemi al (gelir ve gider karışık)
    $recentTransactionsStmt = $conn->prepare("SELECT 
        'income' as type, income_id as id, income_type as transaction_type, amount, description, created_at 
        FROM incomes WHERE user_id = :user_id 
        UNION ALL 
        SELECT 
        'expense' as type, expense_id as id, expense_type as transaction_type, amount, description, created_at 
        FROM expenses WHERE user_id = :user_id 
        ORDER BY created_at DESC LIMIT 5");
    $recentTransactionsStmt->bindParam(':user_id', $user_id);
    $recentTransactionsStmt->execute();
    $recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    setErrorMessage("Kullanıcı bilgileri alınırken bir hata oluştu: " . $e->getMessage());
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

// Tema tercihi
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Kullanıcı Profili</title>
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
        
        .profile-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 20px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .profile-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            flex: 1;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-item:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .transaction-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .transaction-icon.income {
            background-color: rgba(25, 135, 84, 0.1);
            color: var(--success-color);
        }
        
        .transaction-icon.expense {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .transaction-content {
            flex: 1;
        }
        
        .transaction-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .transaction-date {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }
        
        .transaction-amount {
            font-weight: 600;
        }
        
        .transaction-amount.income {
            color: var(--success-color);
        }
        
        .transaction-amount.expense {
            color: var(--danger-color);
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
            .profile-stats {
                flex-direction: column;
            }
            .stat-item {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                padding: 15px 0;
            }
            .stat-item:last-child {
                border-bottom: none;
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
                <li class="active">
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
                
                <div class="profile-header">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <div class="profile-avatar mx-auto">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="col-md-10">
                            <h2><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
                            <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($email); ?></p>
                            <p><i class="fas fa-calendar-alt me-2"></i> Üyelik: <?php echo date('d.m.Y', strtotime($created_at)); ?></p>
                            
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($total_budget, 2, ',', '.'); ?> ₺</div>
                                    <div class="stat-label">Toplam Bütçe</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($totalIncome, 2, ',', '.'); ?> ₺</div>
                                    <div class="stat-label">Toplam Gelir</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($totalExpense, 2, ',', '.'); ?> ₺</div>
                                    <div class="stat-label">Toplam Gider</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $totalTransactions; ?></div>
                                    <div class="stat-label">Toplam İşlem</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2"></i> Kullanıcı Bilgileri
                            </div>
                            <div class="card-body p-0">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Ad Soyad</div>
                                        <div class="info-value"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">E-posta</div>
                                        <div class="info-value"><?php echo htmlspecialchars($email); ?></div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Üyelik Tarihi</div>
                                        <div class="info-value"><?php echo date('d.m.Y', strtotime($created_at)); ?></div>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Toplam Bütçe</div>
                                        <div class="info-value"><?php echo number_format($total_budget, 2, ',', '.'); ?> ₺</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="./ayarlar.php" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i> Profili Düzenle
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-exchange-alt me-2"></i> Son İşlemler</span>
                                <a href="./budget.php" class="btn btn-sm btn-outline-primary">
                                    Tüm İşlemleri Gör
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentTransactions)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3"></i>
                                    <p>Henüz hiç işlem bulunmamaktadır.</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                    <div class="transaction-item">
                                        <div class="transaction-icon <?php echo $transaction['type']; ?>">
                                            <?php if ($transaction['type'] === 'income'): ?>
                                            <i class="fas <?php echo getIncomeIcon($transaction['transaction_type']); ?>"></i>
                                            <?php else: ?>
                                            <i class="fas <?php echo getExpenseIcon($transaction['transaction_type']); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction-content">
                                            <div class="transaction-title">
                                                <?php if ($transaction['type'] === 'income'): ?>
                                                <?php echo getIncomeTypeName($transaction['transaction_type']); ?>
                                                <?php else: ?>
                                                <?php echo getExpenseTypeName($transaction['transaction_type']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($transaction['description'])): ?>
                                                <small class="text-muted"> - <?php echo htmlspecialchars($transaction['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="transaction-date">
                                                <i class="far fa-clock me-1"></i> <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="transaction-amount <?php echo $transaction['type']; ?>">
                                            <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?><?php echo number_format($transaction['amount'], 2, ',', '.'); ?> ₺
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i> Bütçe Özeti
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h5 class="text-center mb-3">Gelir Dağılımı</h5>
                                        <div class="text-center">
                                            <canvas id="incomeChart" width="100%" height="200"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h5 class="text-center mb-3">Gider Dağılımı</h5>
                                        <div class="text-center">
                                            <canvas id="expenseChart" width="100%" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="./analiz.php" class="btn btn-outline-primary">
                                        <i class="fas fa-chart-bar me-2"></i> Detaylı Analiz
                                    </a>
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
            
            // Gelir dağılımı grafiği
            const incomeCtx = document.getElementById('incomeChart').getContext('2d');
            const incomeChart = new Chart(incomeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Maaş', 'Bonus', 'Yatırım', 'Diğer'],
                    datasets: [{
                        data: [
                            <?php 
                            // Gelir tiplerinin dağılımını hesapla
                            $incomeTypes = ['salary', 'bonus', 'investment', 'other'];
                            $incomeData = [];
                            
                            foreach ($incomeTypes as $type) {
                                try {
                                    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM incomes WHERE user_id = :user_id AND income_type = :type");
                                    $stmt->bindParam(':user_id', $user_id);
                                    $stmt->bindParam(':type', $type);
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $incomeData[] = $result['total'] ?: 0;
                                } catch(PDOException $e) {
                                    $incomeData[] = 0;
                                }
                            }
                            
                            echo implode(', ', $incomeData);
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(201, 203, 207, 0.8)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
            
            // Gider dağılımı grafiği
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            const expenseChart = new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Alışveriş', 'Faturalar', 'Kira', 'Yemek', 'Ulaşım', 'Diğer'],
                    datasets: [{
                        data: [
                            <?php 
                            // Gider tiplerinin dağılımını hesapla
                            $expenseTypes = ['shopping', 'bills', 'rent', 'food', 'transport', 'other'];
                            $expenseData = [];
                            
                            foreach ($expenseTypes as $type) {
                                try {
                                    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = :user_id AND expense_type = :type");
                                    $stmt->bindParam(':user_id', $user_id);
                                    $stmt->bindParam(':type', $type);
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $expenseData[] = $result['total'] ?: 0;
                                } catch(PDOException $e) {
                                    $expenseData[] = 0;
                                }
                            }
                            
                            echo implode(', ', $expenseData);
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(201, 203, 207, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>