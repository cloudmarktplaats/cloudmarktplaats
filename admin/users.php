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

// Verwerk gebruikersacties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    
    if ($userId && in_array($action, ['make_admin', 'remove_admin', 'delete'])) {
        try {
            if ($action === 'make_admin') {
                $db->update('users', 
                    ['role' => 'admin'], 
                    'id = ?', 
                    [$userId]
                );
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Gebruiker is nu admin.'
                ];
            } elseif ($action === 'remove_admin') {
                $db->update('users', 
                    ['role' => 'user'], 
                    'id = ?', 
                    [$userId]
                );
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Admin rechten zijn verwijderd.'
                ];
            } else { // delete
                $db->delete('users', 'id = ?', [$userId]);
                
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Gebruiker is verwijderd.'
                ];
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Database fout: ' . $e->getMessage()
            ];
        }
        
        header('Location: users.php');
        exit;
    }
}

// Haal filters op
$role = $_GET['role'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

// Bouw query op
$query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM products WHERE user_id = u.id) as total_products,
                 (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
                 (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as total_reviews
          FROM users u 
          WHERE 1=1";
$params = [];

if ($role !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

// Sorteer
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY u.created_at ASC";
        break;
    case 'username':
        $query .= " ORDER BY u.username ASC";
        break;
    case 'products':
        $query .= " ORDER BY total_products DESC";
        break;
    case 'orders':
        $query .= " ORDER BY total_orders DESC";
        break;
    default: // newest
        $query .= " ORDER BY u.created_at DESC";
}

// Haal gebruikers op
$users = $db->fetchAll($query, $params);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikers Beheren - Cloudmarkplaats.nl</title>
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
                        <a class="nav-link active" href="/admin/users.php">Gebruikers</a>
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
                    <h1 class="h5 mb-0">Gebruikers Beheren</h1>
                    <a href="/admin" class="btn btn-sm btn-outline-primary">
                        Terug naar Dashboard
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role">
                            <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>Alle</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Gebruiker</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="sort" class="form-label">Sorteren op</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Nieuwste eerst</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oudste eerst</option>
                            <option value="username" <?= $sort === 'username' ? 'selected' : '' ?>>Gebruikersnaam</option>
                            <option value="products" <?= $sort === 'products' ? 'selected' : '' ?>>Meeste producten</option>
                            <option value="orders" <?= $sort === 'orders' ? 'selected' : '' ?>>Meeste bestellingen</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Zoeken</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Zoek op gebruikersnaam of email">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            Filter Toepassen
                        </button>
                        <a href="users.php" class="btn btn-outline-secondary">
                            Reset Filters
                        </a>
                    </div>
                </form>
                
                <?php if (empty($users)): ?>
                    <p class="text-muted mb-0">Geen gebruikers gevonden</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Gebruikersnaam</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Producten</th>
                                    <th>Bestellingen</th>
                                    <th>Beoordelingen</th>
                                    <th>Lid sinds</th>
                                    <th>Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= $user['total_products'] ?></td>
                                        <td><?= $user['total_orders'] ?></td>
                                        <td><?= $user['total_reviews'] ?></td>
                                        <td>
                                            <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($user['id'] !== $_SESSION['user']['id']): ?>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="remove_admin">
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-warning"
                                                                onclick="return confirm('Weet je zeker dat je de admin rechten wilt verwijderen?')">
                                                            <i class="bi bi-person-x"></i> Admin Verwijderen
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="make_admin">
                                                        <button type="submit" 
                                                                class="btn btn-sm btn-success"
                                                                onclick="return confirm('Weet je zeker dat je deze gebruiker admin wilt maken?')">
                                                            <i class="bi bi-person-check"></i> Admin Maken
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?')">
                                                        <i class="bi bi-trash"></i> Verwijderen
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