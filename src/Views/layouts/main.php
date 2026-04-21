<?php
use App\Core\View;
use App\Core\Session;
use App\Core\Config;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Cloudmarkplaats') ?> - Cloudmarkplaats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://unpkg.com/hyperscript.org@0.9.12"></script>
    <meta name="csrf-token" content="<?= View::e(Session::get('_csrf_token', '')) ?>">
    <meta name="wc-project-id" content="<?= View::e(Config::get('WALLETCONNECT_PROJECT_ID', '')) ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Cloudmarkplaats</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/product">Producten</a></li>
                <li class="nav-item"><a class="nav-link" href="/forum">Forum</a></li>
                <?php if (Session::isAdmin()): ?>
                <li class="nav-item"><a class="nav-link" href="/admin">Admin</a></li>
                <?php endif; ?>
            </ul>
            <form class="d-flex me-3" action="/product" method="GET">
                <input class="form-control me-2" type="search" name="search" placeholder="Zoek hardware..." aria-label="Zoek">
                <button class="btn btn-outline-light" type="submit">Zoek</button>
            </form>
            <ul class="navbar-nav">
                <?php if (Session::isLoggedIn()): ?>
                <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/message">Berichten</a></li>
                <li class="nav-item"><a class="nav-link" href="/profile"><?= View::e(Session::get('username')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/auth/logout">Uitloggen</a></li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="/auth/login">Inloggen</a></li>
                <li class="nav-item"><a class="nav-link" href="/auth/register">Registreren</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (!empty($flash)): ?>
<div class="container mt-3">
    <div class="alert alert-<?= View::e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show">
        <?= nl2br(View::e($flash['message'])) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<main class="container py-4">
    <?= $content ?>
</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>Cloudmarkplaats</h5>
                <p class="text-muted">Het onafhankelijke handelsplatform voor de IT community. 100% gratis, mogelijk gemaakt door onze sponsors.</p>
            </div>
            <div class="col-md-4">
                <h5>Links</h5>
                <ul class="list-unstyled">
                    <li><a href="/product" class="text-muted">Marktplaats</a></li>
                    <li><a href="/forum" class="text-muted">Forum</a></li>
                    <li><a href="/legal/tos" class="text-muted">Algemene Voorwaarden</a></li>
                    <li><a href="/legal/privacy" class="text-muted">Privacybeleid</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Account</h5>
                <ul class="list-unstyled">
                    <?php if (Session::isLoggedIn()): ?>
                    <li><a href="/dashboard" class="text-muted">Dashboard</a></li>
                    <li><a href="/profile" class="text-muted">Profiel</a></li>
                    <?php else: ?>
                    <li><a href="/auth/login" class="text-muted">Inloggen</a></li>
                    <li><a href="/auth/register" class="text-muted">Registreren</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="text-muted">
        <p class="text-center text-muted mb-0">&copy; <?= date('Y') ?> Cloudmarkplaats.nl</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.body.addEventListener('htmx:configRequest', function(event) {
    event.detail.headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
});
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
</script>

<?php require __DIR__ . '/../partials/cookie_banner.php'; ?>
</body>
</html>
