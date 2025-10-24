<?php
// includes/navbar.php
// Her sayfada include edilecek navbar

$is_logged_in = !empty($_SESSION['user_id']);
$user_name = $is_logged_in ? ($_SESSION['full_name'] ?? 'Kullanıcı') : '';
$balance = $is_logged_in ? ($_SESSION['balance'] ?? 0) : 0;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-bus-front-fill"></i> MERİCBİLET
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="bi bi-house-fill"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my-tickets.php' ? 'active' : ''; ?>" href="my-tickets.php">
                            <i class="bi bi-ticket-perforated-fill"></i> Biletlerim
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-warning fw-bold">
                            <i class="bi bi-wallet2"></i> <?php echo number_format($balance, 2); ?> TL
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Çıkış
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-register" href="register.php">
                            <i class="bi bi-person-plus"></i> Kayıt Ol
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>