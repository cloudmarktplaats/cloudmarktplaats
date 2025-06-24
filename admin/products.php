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

// Verwerk product goedkeuring/afwijzing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    
    if ($productId && in_array($action, ['approve', 'reject'])) {
        try {
            if ($action === 'approve') {
                $db->update('products', 
                    ['approved' => 1], 
                    'id = ?', 
                    [$productId]
                );
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Product is goedgekeurd.'
                ];
            } else {
                $db->delete('products', 'id = ?', [$productId]);
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Product is afgewezen en verwijderd.'
                ];
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Database fout: ' . $e->getMessage()
            ];
        }
        
        header('Location: products.php' . 
               (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
        exit;
    }
}

// Haal filters op
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

// Bouw query op
$query = "SELECT p.*, u.username 
          FROM products p 
          JOIN users u ON p.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($status === 'pending') {
    $query .= " AND p.approved = 0";
} elseif ($status === 'approved') {
    $query .= " AND p.approved = 1";
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($category !== 'all') {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

// Sorteer
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY p.created_at ASC";
        break;
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Haal producten op
$products = $db->fetchAll($query, $params);

// Haal categorieën op voor filter
$categories = $db->fetchAll(
    "SELECT DISTINCT category FROM products ORDER BY category"
);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producten Beheren - Cloudmarkplaats.nl</title>
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
                        <a class="nav-link active" href="/admin/products.php">Producten</a>
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

        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h5 mb-0">Producten Beheren</h1>
                    <a href="/admin" class="btn btn-sm btn-outline-primary">
                        Terug naar Dashboard
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alle</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>In afwachting</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Goedgekeurd</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="category" class="form-label">Categorie</label>
                        <select class="form-select" id="category" name="category">
                            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>Alle</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                        <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="sort" class="form-label">Sorteren op</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Nieuwste eerst</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oudste eerst</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prijs (laag-hoog)</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prijs (hoog-laag)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Zoeken</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Zoek op naam, beschrijving of verkoper">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            Filter Toepassen
                        </button>
                        <a href="products.php" class="btn btn-outline-secondary">
                            Reset Filters
                        </a>
                    </div>
                </form>
                
                <?php if (empty($products)): ?>
                    <p class="text-muted mb-0">Geen producten gevonden</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Naam</th>
                                    <th>Verkoper</th>
                                    <th>Categorie</th>
                                    <th>Prijs</th>
                                    <th>Status</th>
                                    <th>Datum</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <a href="/product.php?id=<?= $product['id'] ?>">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($product['username']) ?></td>
                                        <td><?= htmlspecialchars($product['category']) ?></td>
                                        <td>€<?= number_format($product['price'], 2) ?></td>
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
                                        <td>
                                            <?php if (!$product['approved']): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-success"
                                                            onclick="return confirm('Weet je zeker dat je dit product wilt goedkeuren?')">
                                                        <i class="bi bi-check-lg"></i> Goedkeuren
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Weet je zeker dat je dit product wilt afwijzen?')">
                                                        <i class="bi bi-x-lg"></i> Afwijzen
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 