<?php
// Veritabanı bağlantısı ve yardımcı fonksiyonlar
require_once '../../config.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
redirectIfNotLoggedIn();

// Kullanıcı bilgilerini al
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Varsayılan tarih aralığı (son 30 gün)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Tarih filtresi kontrolü
if (isset($_GET['filter'])) {
    $filter = $_GET['filter'];
    
    switch ($filter) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'quarter':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $custom_start = $_GET['start_date'];
                $custom_end = $_GET['end_date'];
                
                if (strtotime($custom_start) && strtotime($custom_end)) {
                    $start_date = $custom_start;
                    $end_date = $custom_end;
                }
            }
            break;
    }
} else {
    $filter = 'month'; // Varsayılan filtre
}

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
        $budget_id = 0;
        $total_budget = 0;
    }
    
    // Seçilen tarih aralığındaki toplam gelir ve giderleri hesapla
    $totalIncomeStmt = $conn->prepare("SELECT SUM(amount) as total FROM incomes 
                                      WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date");
    $totalIncomeStmt->bindParam(':user_id', $user_id);
    $totalIncomeStmt->bindParam(':start_date', $start_date);
    $totalIncomeStmt->bindParam(':end_date', $end_date);
    $totalIncomeStmt->execute();
    $totalIncome = $totalIncomeStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    $totalExpenseStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses 
                                       WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date");
    $totalExpenseStmt->bindParam(':user_id', $user_id);
    $totalExpenseStmt->bindParam(':start_date', $start_date);
    $totalExpenseStmt->bindParam(':end_date', $end_date);
    $totalExpenseStmt->execute();
    $totalExpense = $totalExpenseStmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
    
    // Net tasarruf hesapla
    $netSavings = $totalIncome - $totalExpense;
    $savingsRate = $totalIncome > 0 ? ($netSavings / $totalIncome) * 100 : 0;
    
    // Gelir tiplerinin dağılımını hesapla
    $incomeByTypeStmt = $conn->prepare("SELECT income_type, SUM(amount) as total 
                                      FROM incomes 
                                      WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date 
                                      GROUP BY income_type");
    $incomeByTypeStmt->bindParam(':user_id', $user_id);
    $incomeByTypeStmt->bindParam(':start_date', $start_date);
    $incomeByTypeStmt->bindParam(':end_date', $end_date);
    $incomeByTypeStmt->execute();
    $incomeByType = $incomeByTypeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gider tiplerinin dağılımını hesapla
    $expenseByTypeStmt = $conn->prepare("SELECT expense_type, SUM(amount) as total 
                                       FROM expenses 
                                       WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date 
                                       GROUP BY expense_type");
    $expenseByTypeStmt->bindParam(':user_id', $user_id);
    $expenseByTypeStmt->bindParam(':start_date', $start_date);
    $expenseByTypeStmt->bindParam(':end_date', $end_date);
    $expenseByTypeStmt->execute();
    $expenseByType = $expenseByTypeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Günlük gelir ve gider verilerini al
    $dailyDataStmt = $conn->prepare("SELECT 
                                    DATE(created_at) as date,
                                    (SELECT SUM(amount) FROM incomes WHERE user_id = :user_id AND DATE(created_at) = date) as income,
                                    (SELECT SUM(amount) FROM expenses WHERE user_id = :user_id AND DATE(created_at) = date) as expense
                                    FROM (
                                        SELECT created_at FROM incomes WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date
                                        UNION
                                        SELECT created_at FROM expenses WHERE user_id = :user_id AND created_at BETWEEN :start_date AND :end_date
                                    ) as combined
                                    GROUP BY date
                                    ORDER BY date");
    $dailyDataStmt->bindParam(':user_id', $user_id);
    $dailyDataStmt->bindParam(':start_date', $start_date);
    $dailyDataStmt->bindParam(':end_date', $end_date);
    $dailyDataStmt->execute();
    $dailyData = $dailyDataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Aylık gelir ve gider verilerini al
    $monthlyDataStmt = $conn->prepare("SELECT 
                                      DATE_FORMAT(created_at, '%Y-%m') as month,
                                      (SELECT SUM(amount) FROM incomes WHERE user_id = :user_id AND DATE_FORMAT(created_at, '%Y-%m') = month) as income,
                                      (SELECT SUM(amount) FROM expenses WHERE user_id = :user_id AND DATE_FORMAT(created_at, '%Y-%m') = month) as expense
                                      FROM (
                                          SELECT created_at FROM incomes WHERE user_id = :user_id AND created_at BETWEEN DATE_SUB(:start_date, INTERVAL 12 MONTH) AND :end_date
                                          UNION
                                          SELECT created_at FROM expenses WHERE user_id = :user_id AND created_at BETWEEN DATE_SUB(:start_date, INTERVAL 12 MONTH) AND :end_date
                                      ) as combined
                                      GROUP BY month
                                      ORDER BY month");
    $monthlyDataStmt->bindParam(':user_id', $user_id);
    $monthlyDataStmt->bindParam(':start_date', $start_date);
    $monthlyDataStmt->bindParam(':end_date', $end_date);
    $monthlyDataStmt->execute();
    $monthlyData = $monthlyDataStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    setErrorMessage("Analiz verileri alınırken bir hata oluştu: " . $e->getMessage());
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

// Tema tercihi
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusFlow - Finansal Analiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>

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

        .content {
            transition: all 0.3s;
        }
        
        .content.expanded {
            margin-left: 0;
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
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 120px;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }
        
        .stat-card.income {
            background: linear-gradient(45deg, #4b6cb7, #182848);
            color: white;
        }
        
        .stat-card.expense {
            background: linear-gradient(45deg, #cb2d3e, #ef473a);
            color: white;
        }
        
        .stat-card.savings {
            background: linear-gradient(45deg, #134e5e, #71b280);
            color: white;
        }
        
        .stat-card.rate {
            background: linear-gradient(45deg,rgb(94, 95, 95),rgb(44, 48, 45));
            color: white;
        }
        
        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .stat-title {
            font-size: 1rem;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .filter-card {
            background-color: var(--card-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            border-radius: 20px;
            padding: 8px 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .date-picker {
            border-radius: 8px;
            padding: 8px 15px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg-color);
            color: var(--text-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 10px;
        }
        
        .legend-label {
            font-size: 0.9rem;
        }
        
        .legend-value {
            font-weight: 600;
            margin-left: auto;
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
                <li>
                    <a href="./../../index.php">
                        <i class="fas fa-chart-line"></i> Gösterge Paneli
                    </a>
                </li>
                <li class="active">
                    <a href="analiz.php">
                        <i class="fas fa-chart-bar"></i> Analiz
                    </a>
                </li>
                <li>
                    <a href="budget.php">
                        <i class="fas fa-wallet"></i> Bütçe
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                    </a>
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
                
                <h2 class="mb-4">Finansal Analiz</h2>
                
                <!-- Filtre Seçenekleri -->
                <div class="filter-card">
                    <form method="get" action="" class="row g-3 align-items-end">
                        <div class="col-md-auto">
                            <label class="form-label">Zaman Aralığı</label>
                            <div>
                                <a href="?filter=week" class="btn btn-sm <?php echo $filter === 'week' ? 'btn-primary' : 'btn-outline-primary'; ?> filter-btn">Son 7 Gün</a>
                                <a href="?filter=month" class="btn btn-sm <?php echo $filter === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?> filter-btn">Son 30 Gün</a>
                                <a href="?filter=quarter" class="btn btn-sm <?php echo $filter === 'quarter' ? 'btn-primary' : 'btn-outline-primary'; ?> filter-btn">Son 3 Ay</a>
                                <a href="?filter=year" class="btn btn-sm <?php echo $filter === 'year' ? 'btn-primary' : 'btn-outline-primary'; ?> filter-btn">Son 1 Yıl</a>
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <label class="form-label">Özel Tarih Aralığı</label>
                            <div class="d-flex">
                                <input type="text" id="start_date" name="start_date" class="form-control date-picker me-2" placeholder="Başlangıç" value="<?php echo $filter === 'custom' ? $start_date : ''; ?>">
                                <input type="text" id="end_date" name="end_date" class="form-control date-picker me-2" placeholder="Bitiş" value="<?php echo $filter === 'custom' ? $end_date : ''; ?>">
                                <input type="hidden" name="filter" value="custom">
                                <button type="submit" class="btn btn-primary">Uygula</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- İstatistik Kartları -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card income">
                            <div class="stat-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="stat-title">Toplam Gelir</div>
                            <div class="stat-value"><?php echo number_format($totalIncome, 2, ',', '.'); ?> ₺</div>
                            <div class="stat-subtitle"><?php echo date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card expense">
                            <div class="stat-icon">
                                <i class="fas fa-minus-circle"></i>
                            </div>
                            <div class="stat-title">Toplam Gider</div>
                            <div class="stat-value"><?php echo number_format($totalExpense, 2, ',', '.'); ?> ₺</div>
                            <div class="stat-subtitle"><?php echo date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card savings">
                            <div class="stat-icon">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                            </div>
                            <div class="stat-title">Net Tasarruf</div>
                            <div class="stat-value"><?php echo number_format($netSavings, 2, ',', '.'); ?> ₺</div>
                            <div class="stat-subtitle"><?php echo date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card rate">
                            <div class="stat-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-title">Tasarruf Oranı</div>
                            <div class="stat-value"><?php echo number_format($savingsRate, 2, ',', '.'); ?>%</div>
                            <div class="stat-subtitle">Gelir Yüzdesi Olarak</div>
                        </div>
                    </div>
                </div>
                
                <!-- Grafikler -->
                <div class="row">
                    <!-- Gelir/Gider Trendi -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-line me-2"></i> Gelir/Gider Trendi</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active" id="dailyTrendBtn">Günlük</button>
                                    <button type="button" class="btn btn-outline-primary" id="monthlyTrendBtn">Aylık</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="trendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tasarruf Trendi -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-piggy-bank me-2"></i> Tasarruf Trendi
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="savingsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Gelir Dağılımı -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-plus-circle text-success me-2"></i> Gelir Dağılımı
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-7">
                                        <div class="chart-container">
                                            <canvas id="incomeChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <h6 class="mb-3">Gelir Kaynakları</h6>
                                        <div class="legend-container">
                                            <?php 
                                            $incomeColors = [
                                                'salary' => '#36a2eb',
                                                'bonus' => '#4bc0c0',
                                                'investment' => '#9966ff',
                                                'other' => '#c9cbcf'
                                            ];
                                            
                                            $incomeTypes = ['salary', 'bonus', 'investment', 'other'];
                                            $incomeData = [];
                                            
                                            foreach ($incomeTypes as $type) {
                                                $amount = 0;
                                                foreach ($incomeByType as $item) {
                                                    if ($item['income_type'] === $type) {
                                                        $amount = $item['total'];
                                                        break;
                                                    }
                                                }
                                                $incomeData[$type] = $amount;
                                            }
                                            
                                            foreach ($incomeTypes as $type) {
                                                $percentage = $totalIncome > 0 ? ($incomeData[$type] / $totalIncome) * 100 : 0;
                                                echo '<div class="legend-item">';
                                                echo '<div class="legend-color" style="background-color: ' . $incomeColors[$type] . '"></div>';
                                                echo '<div class="legend-label">' . getIncomeTypeName($type) . '</div>';
                                                echo '<div class="legend-value">' . number_format($percentage, 1) . '%</div>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gider Dağılımı -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-minus-circle text-danger me-2"></i> Gider Dağılımı
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-7">
                                        <div class="chart-container">
                                            <canvas id="expenseChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <h6 class="mb-3">Gider Kategorileri</h6>
                                        <div class="legend-container">
                                            <?php 
                                            $expenseColors = [
                                                'shopping' => '#ff6384',
                                                'bills' => '#ff9f40',
                                                'rent' => '#ffcd56',
                                                'food' => '#4bc0c0',
                                                'transport' => '#36a2eb',
                                                'other' => '#c9cbcf'
                                            ];
                                            
                                            $expenseTypes = ['shopping', 'bills', 'rent', 'food', 'transport', 'other'];
                                            $expenseData = [];
                                            
                                            foreach ($expenseTypes as $type) {
                                                $amount = 0;
                                                foreach ($expenseByType as $item) {
                                                    if ($item['expense_type'] === $type) {
                                                        $amount = $item['total'];
                                                        break;
                                                    }
                                                }
                                                $expenseData[$type] = $amount;
                                            }
                                            
                                            foreach ($expenseTypes as $type) {
                                                $percentage = $totalExpense > 0 ? ($expenseData[$type] / $totalExpense) * 100 : 0;
                                                echo '<div class="legend-item">';
                                                echo '<div class="legend-color" style="background-color: ' . $expenseColors[$type] . '"></div>';
                                                echo '<div class="legend-label">' . getExpenseTypeName($type) . '</div>';
                                                echo '<div class="legend-value">' . number_format($percentage, 1) . '%</div>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bütçe Analizi -->
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-balance-scale me-2"></i> Bütçe Analizi ve Öneriler
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Finansal Durum Özeti</h5>
                                        <p>
                                            <?php if ($netSavings > 0): ?>
                                                <i class="fas fa-check-circle text-success me-2"></i> Seçilen dönemde <strong class="text-success"><?php echo number_format($netSavings, 2, ',', '.'); ?> ₺</strong> tasarruf sağladınız.
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-circle text-danger me-2"></i> Seçilen dönemde <strong class="text-danger"><?php echo number_format(abs($netSavings), 2, ',', '.'); ?> ₺</strong> açık verdiniz.
                                            <?php endif; ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-percentage me-2"></i> Tasarruf oranınız: <strong><?php echo number_format($savingsRate, 2, ',', '.'); ?>%</strong>
                                            <?php if ($savingsRate < 20 && $totalIncome > 0): ?>
                                                <span class="text-warning"> (İdeal oran: %20-%30)</span>
                                            <?php elseif ($savingsRate >= 20 && $totalIncome > 0): ?>
                                                <span class="text-success"> (Harika! İdeal aralıktasınız)</span>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <?php if ($totalExpense > 0): ?>
                                            <h6 class="mt-4 mb-3">En Yüksek Gider Kategorileri:</h6>
                                            <?php 
                                            // Gider kategorilerini sırala
                                            $sortedExpenses = $expenseData;
                                            arsort($sortedExpenses);
                                            $topExpenses = array_slice($sortedExpenses, 0, 3, true);
                                            
                                            foreach ($topExpenses as $type => $amount) {
                                                if ($amount > 0) {
                                                    $percentage = ($amount / $totalExpense) * 100;
                                                    echo '<div class="mb-2">';
                                                    echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                                                    echo '<span>' . getExpenseTypeName($type) . '</span>';
                                                    echo '<span>' . number_format($amount, 2, ',', '.') . ' ₺ (' . number_format($percentage, 1) . '%)</span>';
                                                    echo '</div>';
                                                    echo '<div class="progress" style="height: 8px;">';
                                                    echo '<div class="progress-bar" role="progressbar" style="width: ' . $percentage . '%; background-color: ' . $expenseColors[$type] . '" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100"></div>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Finansal Öneriler</h5>
                                        <ul class="list-group">
                                            <?php if ($savingsRate < 20 && $totalIncome > 0): ?>
                                                <li class="list-group-item">
                                                    <i class="fas fa-lightbulb text-warning me-2"></i> Tasarruf oranınız düşük. Giderlerinizi azaltmayı veya gelirinizi artırmayı düşünebilirsiniz.
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($netSavings < 0): ?>
                                                <li class="list-group-item">
                                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i> Giderleriniz gelirinizden fazla. Acil bir bütçe planı yapmanız önerilir.
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // En yüksek gider kategorisine göre öneri
                                            if (!empty($topExpenses)) {
                                                $topExpenseType = array_key_first($topExpenses);
                                                $topExpenseAmount = $topExpenses[$topExpenseType];
                                                $topExpensePercentage = ($topExpenseAmount / $totalExpense) * 100;
                                                
                                                if ($topExpensePercentage > 40) {
                                                    echo '<li class="list-group-item">';
                                                    echo '<i class="fas fa-chart-pie text-primary me-2"></i> ' . getExpenseTypeName($topExpenseType) . ' harcamalarınız toplam giderlerinizin %' . number_format($topExpensePercentage, 1) . '\'ini oluşturuyor. Bu kategoriyi gözden geçirmeniz faydalı olabilir.';
                                                    echo '</li>';
                                                }
                                            }
                                            ?>
                                            
                                            <li class="list-group-item">
                                                <i class="fas fa-piggy-bank text-success me-2"></i> Düzenli tasarruf için gelirinizin en az %20'sini kenara ayırmayı hedefleyin.
                                            </li>
                                            
                                            <li class="list-group-item">
                                                <i class="fas fa-calendar-alt text-info me-2"></i> Aylık sabit giderlerinizi (kira, faturalar) gelirinizin %50'sinden fazla olmamasına dikkat edin.
                                            </li>
                                            
                                            <li class="list-group-item">
                                                <i class="fas fa-chart-line text-primary me-2"></i> Uzun vadeli finansal hedefler belirleyin ve düzenli olarak ilerlemenizi takip edin.
                                            </li>
                                        </ul>
                                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('content').classList.toggle('expanded');
            });
            
            // Tarih seçici
            flatpickr(".date-picker", {
                dateFormat: "Y-m-d",
                locale: "tr",
                allowInput: true
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
                            $incomeTypes = ['salary', 'bonus', 'investment', 'other'];
                            $incomeValues = [];
                            
                            foreach ($incomeTypes as $type) {
                                $amount = 0;
                                foreach ($incomeByType as $item) {
                                    if ($item['income_type'] === $type) {
                                        $amount = $item['total'];
                                        break;
                                    }
                                }
                                $incomeValues[] = $amount;
                            }
                            
                            echo implode(', ', $incomeValues);
                            ?>
                        ],
                        backgroundColor: [
                            '#36a2eb',
                            '#4bc0c0',
                            '#9966ff',
                            '#c9cbcf'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    cutout: '70%'
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
                            $expenseTypes = ['shopping', 'bills', 'rent', 'food', 'transport', 'other'];
                            $expenseValues = [];
                            
                            foreach ($expenseTypes as $type) {
                                $amount = 0;
                                foreach ($expenseByType as $item) {
                                    if ($item['expense_type'] === $type) {
                                        $amount = $item['total'];
                                        break;
                                    }
                                }
                                $expenseValues[] = $amount;
                            }
                            
                            echo implode(', ', $expenseValues);
                            ?>
                        ],
                        backgroundColor: [
                            '#ff6384',
                            '#ff9f40',
                            '#ffcd56',
                            '#4bc0c0',
                            '#36a2eb',
                            '#c9cbcf'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Günlük trend grafiği
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            let trendChart;
            
            function createDailyTrendChart() {
                if (trendChart) {
                    trendChart.destroy();
                }
                
                trendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: [
                            <?php 
                            $dates = [];
                            foreach ($dailyData as $data) {
                                $dates[] = "'" . date('d.m.Y', strtotime($data['date'])) . "'";
                            }
                            echo implode(', ', $dates);
                            ?>
                        ],
                        datasets: [
                            {
                                label: 'Gelir',
                                data: [
                                    <?php 
                                    $incomes = [];
                                    foreach ($dailyData as $data) {
                                        $incomes[] = $data['income'] ?: 0;
                                    }
                                    echo implode(', ', $incomes);
                                    ?>
                                ],
                                borderColor: '#198754',
                                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Gider',
                                data: [
                                    <?php 
                                    $expenses = [];
                                    foreach ($dailyData as $data) {
                                        $expenses[] = $data['expense'] ?: 0;
                                    }
                                    echo implode(', ', $expenses);
                                    ?>
                                ],
                                borderColor: '#dc3545',
                                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                                fill: true,
                                tension: 0.4
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
                                        return value.toLocaleString('tr-TR') + ' ₺';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            function createMonthlyTrendChart() {
                if (trendChart) {
                    trendChart.destroy();
                }
                
                trendChart = new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php 
                            $months = [];
                            foreach ($monthlyData as $data) {
                                $date = \DateTime::createFromFormat('Y-m', $data['month']);
                                $months[] = "'" . $date->format('M Y') . "'";
                            }
                            echo implode(', ', $months);
                            ?>
                        ],
                        datasets: [
                            {
                                label: 'Gelir',
                                data: [
                                    <?php 
                                    $incomes = [];
                                    foreach ($monthlyData as $data) {
                                        $incomes[] = $data['income'] ?: 0;
                                    }
                                    echo implode(', ', $incomes);
                                    ?>
                                ],
                                backgroundColor: 'rgba(25, 135, 84, 0.7)'
                            },
                            {
                                label: 'Gider',
                                data: [
                                    <?php 
                                    $expenses = [];
                                    foreach ($monthlyData as $data) {
                                        $expenses[] = $data['expense'] ?: 0;
                                    }
                                    echo implode(', ', $expenses);
                                    ?>
                                ],
                                backgroundColor: 'rgba(220, 53, 69, 0.7)'
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
                                        return value.toLocaleString('tr-TR') + ' ₺';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Tasarruf grafiği
            const savingsCtx = document.getElementById('savingsChart').getContext('2d');
            const savingsChart = new Chart(savingsCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        $months = [];
                        foreach ($monthlyData as $data) {
                            $date = \DateTime::createFromFormat('Y-m', $data['month']);
                            $months[] = "'" . $date->format('M Y') . "'";
                        }
                        echo implode(', ', $months);
                        ?>
                    ],
                    datasets: [{
                        label: 'Net Tasarruf',
                        data: [
                            <?php 
                            $savings = [];
                            foreach ($monthlyData as $data) {
                                $income = $data['income'] ?: 0;
                                $expense = $data['expense'] ?: 0;
                                $savings[] = $income - $expense;
                            }
                            echo implode(', ', $savings);
                            ?>
                        ],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('tr-TR') + ' ₺';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Trend grafiği butonları
            document.getElementById('dailyTrendBtn').addEventListener('click', function() {
                document.getElementById('dailyTrendBtn').classList.add('active');
                document.getElementById('monthlyTrendBtn').classList.remove('active');
                createDailyTrendChart();
            });
            
            document.getElementById('monthlyTrendBtn').addEventListener('click', function() {
                document.getElementById('dailyTrendBtn').classList.remove('active');
                document.getElementById('monthlyTrendBtn').classList.add('active');
                createMonthlyTrendChart();
            });
            
            // Başlangıçta günlük trend grafiğini göster
            createDailyTrendChart();
        });
    </script>
</body>
</html>