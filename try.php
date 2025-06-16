<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analiz - NexusFlow2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #1f2c4c;
            color: #fff;
            min-height: 100vh;
        }
        .sidebar .components a {
            color: #ddd;
            padding: 10px 15px;
            display: block;
            text-decoration: none;
        }
        .sidebar .components a:hover {
            background: #3e4a6d;
            color: #fff;
        }
        .sidebar .active > a {
            background: #4c5c87;
            color: #fff;
        }
        #sidebarCollapse {
            background: #1f2c4c;
            color: white;
        }
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
                transition: all 0.3s;
            }
            #sidebar.active {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>NexusFlow2</h3>
            </div>
            <ul class="list-unstyled components">
                <li><a href="../../index.php"><i class="fas fa-chart-line"></i> Gösterge Paneli</a></li>
                <li class="active"><a href="analiz.php"><i class="fas fa-chart-bar"></i> Analiz</a></li>
                <li><a href="budget.php"><i class="fas fa-wallet"></i> Bütçe</a></li>
                <li><a href="gelirler.php"><i class="fas fa-money-bill-wave"></i> Gelirler</a></li>
                <li><a href="giderler.php"><i class="fas fa-shopping-cart"></i> Giderler</a></li>
                <li><a href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid p-4">
            <button class="btn mb-3 d-md-none" id="sidebarCollapse"><i class="fas fa-bars"></i></button>
            <h2>Analiz Sayfası</h2>
            <!-- Buraya analiz verileri ve görsel grafikler gelebilir -->
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>
