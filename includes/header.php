<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://unpkg.com/hyperscript.org@0.9.12"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg main-navbar sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-white" href="/" style="font-size:1.5rem;">
                <?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-1" href="/products" hx-get="/products" hx-target="#main-content" hx-push-url="true">
                            <i class="bi bi-cpu"></i> <span>Hardware</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-1" href="/forum" hx-get="/forum" hx-target="#main-content" hx-push-url="true">
                            <i class="bi bi-chat-dots"></i> <span>Forum</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-1" href="/feeds" hx-get="/feeds" hx-target="#main-content" hx-push-url="true">
                            <i class="bi bi-rss"></i> <span>Tech Feeds</span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/admin" hx-get="/admin" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-shield-lock"></i> <span>Admin</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <form class="d-flex search-container me-3" action="/products" method="GET" role="search">
                    <input class="form-control me-2" type="search" name="q" placeholder="Zoek hardware..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button class="btn btn-primary" type="submit">Zoeken</button>
                </form>
                <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/dashboard" hx-get="/dashboard" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/messages" hx-get="/messages" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-envelope"></i> <span>Berichten</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/profile" hx-get="/profile" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-person"></i> <span>Profiel</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/auth/logout" hx-post="/auth/logout" hx-push-url="true">
                                <i class="bi bi-box-arrow-right"></i> <span>Uitloggen</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/auth/login" hx-get="/auth/login" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-box-arrow-in-right"></i> <span>Inloggen</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-1" href="/auth/register" hx-get="/auth/register" hx-target="#main-content" hx-push-url="true">
                                <i class="bi bi-person-plus"></i> <span>Registreren</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div id="main-content">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="container mt-3">
                <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?> 