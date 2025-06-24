<?php
require_once '../config.php';
require_once '../Database.php';

// Controleer of gebruiker is ingelogd en admin is
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Je hebt geen toegang tot deze pagina.'
    ];
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();

// Haal statistieken op
$stats = $db->fetch(
    "SELECT 
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM products WHERE approved = 0) as pending_products,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COUNT(*) FROM reviews) as total_reviews,
        (SELECT COUNT(*) FROM favorites) as total_favorites"
);

// Haal recente producten op
$recentProducts = $db->fetchAll(
    "SELECT p.*, u.username 
     FROM products p 
     JOIN users u ON p.user_id = u.id 
     ORDER BY p.created_at DESC 
     LIMIT 5"
);

// Haal recente gebruikers op
$recentUsers = $db->fetchAll(
    "SELECT * FROM users ORDER BY created_at DESC LIMIT 5"
);

// Haal recente bestellingen op
$recentOrders = $db->fetchAll(
    "SELECT o.*, 
            p.name as product_name,
            u.username as buyer_username
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cloudmarkplaats.nl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/">Cloudmarkplaats.nl</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/products.php">Producten</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users.php">Gebruikers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/orders.php">Bestellingen</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/profile">Profiel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">Uitloggen</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show">
                <?= $_SESSION['flash']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Totaal Producten</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_products'] ?></h2>
                            </div>
                            <i class="bi bi-box" style="font-size: 2rem;"></i>
                        </div>
                        <?php if ($stats['pending_products'] > 0): ?>
                            <div class="mt-2">
                                <a href="/admin/products.php?status=pending" class="text-white">
                                    <?= $stats['pending_products'] ?> wachten op goedkeuring
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Totaal Gebruikers</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_users'] ?></h2>
                            </div>
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Totaal Bestellingen</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_orders'] ?></h2>
                            </div>
                            <i class="bi bi-cart" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Totaal Beoordelingen</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_reviews'] ?></h2>
                            </div>
                            <i class="bi bi-star" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Recente Producten</h2>
                        <a href="/admin/products.php" class="btn btn-sm btn-primary">
                            Bekijk Alles
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentProducts)): ?>
                            <p class="text-muted mb-0">Geen producten gevonden</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Naam</th>
                                            <th>Verkoper</th>
                                            <th>Status</th>
                                            <th>Datum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentProducts as $product): ?>
                                            <tr>
                                                <td>
                                                    <a href="/product.php?id=<?= $product['id'] ?>">
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($product['username']) ?></td>
                                                <td>
                                                    <?php if ($product['approved']): ?>
                                                        <span class="badge bg-success">Goedgekeurd</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">In afwachting</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('d-m-Y', strtotime($product['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Recente Gebruikers</h2>
                        <a href="/admin/users.php" class="btn btn-sm btn-primary">
                            Bekijk Alles
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentUsers)): ?>
                            <p class="text-muted mb-0">Geen gebruikers gevonden</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Gebruikersnaam</th>
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Datum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Recente Bestellingen</h2>
                        <a href="/admin/orders.php" class="btn btn-sm btn-primary">
                            Bekijk Alles
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <p class="text-muted mb-0">Geen bestellingen gevonden</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Koper</th>
                                            <th>Bedrag</th>
                                            <th>Status</th>
                                            <th>Datum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="/product.php?id=<?= $order['product_id'] ?>">
                                                        <?= htmlspecialchars($order['product_name']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($order['buyer_username']) ?></td>
                                                <td>€<?= number_format($order['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $order['status'] === 'completed' ? 'success' : 
                                                                          ($order['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= date('d-m-Y', strtotime($order['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 