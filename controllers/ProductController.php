<?php
require_once 'controllers/BaseController.php';

class ProductController extends BaseController {
    private $allowed_categories = [
        'Servers',
        'Netwerk',
        'Storage',
        'Workstations',
        'Laptops',
        'Componenten',
        'Randapparatuur',
        'Datacenter',
        'Overig'
    ];

    private $allowed_states = [
        'Nieuw',
        'Als nieuw',
        'Gebruikt',
        'Voor reparatie'
    ];

    public function index() {
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        $sort = $_GET['sort'] ?? 'newest';

        $query = "
            SELECT p.*, u.username 
            FROM products p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.approved = 1
        ";
        $params = [];

        if ($category) {
            $query .= " AND p.category = ?";
            $params[] = $category;
        }

        if ($search) {
            $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        switch ($sort) {
            case 'price_asc':
                $query .= " ORDER BY p.price ASC";
                break;
            case 'price_desc':
                $query .= " ORDER BY p.price DESC";
                break;
            case 'oldest':
                $query .= " ORDER BY p.created_at ASC";
                break;
            default: // newest
                $query .= " ORDER BY p.created_at DESC";
        }

        $products = $this->db->fetchAll($query, $params);
        $categories = $this->db->fetchAll("SELECT * FROM categories");

        $this->render('product/index', [
            'products' => $products,
            'categories' => $categories,
            'current_category' => $category,
            'current_search' => $search,
            'current_sort' => $sort
        ]);
    }

    public function view() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('/');
        }

        $product = $this->db->fetch("
            SELECT p.*, u.username 
            FROM products p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ? AND p.approved = 1
        ", [$id]);

        if (!$product) {
            $this->redirect('/');
        }

        $reviews = $this->db->fetchAll("
            SELECT r.*, u.username 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC
        ", [$id]);

        $this->render('product/view', [
            'product' => $product,
            'reviews' => $reviews
        ]);
    }

    public function add() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $category = $_POST['category'] ?? '';
            $state = $_POST['state'] ?? '';
            $price = floatval($_POST['price'] ?? 0);
            $specs = trim($_POST['specs'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            // Validatie
            if (empty($name) || empty($category) || empty($state) || empty($specs)) {
                $this->setFlash('danger', 'Vul alle verplichte velden in.');
                $this->redirect('/product/add');
            }

            if (!in_array($category, $this->allowed_categories)) {
                $this->setFlash('danger', 'Ongeldige categorie.');
                $this->redirect('/product/add');
            }

            if (!in_array($state, $this->allowed_states)) {
                $this->setFlash('danger', 'Ongeldige staat.');
                $this->redirect('/product/add');
            }

            if ($price <= 0) {
                $this->setFlash('danger', 'Voer een geldig bedrag in.');
                $this->redirect('/product/add');
            }

            if (count($tags) > MAX_PRODUCT_TAGS) {
                $this->setFlash('danger', 'Maximaal ' . MAX_PRODUCT_TAGS . ' tags toegestaan.');
                $this->redirect('/product/add');
            }

            // Verwerk afbeeldingen
            $images = [];
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = 'uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $images[] = $filepath;
                        }
                    }
                }
            }

            // Sla product op
            $this->db->query(
                "INSERT INTO products (user_id, name, category, state, price, specs, description, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [$_SESSION['user_id'], $name, $category, $state, $price, $specs, $description]
            );

            $product_id = $this->db->lastInsertId();

            // Sla tags op
            foreach ($tags as $tag) {
                $this->db->query(
                    "INSERT INTO product_tags (product_id, tag) VALUES (?, ?)",
                    [$product_id, $tag]
                );
            }

            // Sla afbeeldingen op
            foreach ($images as $image) {
                $this->db->query(
                    "INSERT INTO product_images (product_id, image_url) VALUES (?, ?)",
                    [$product_id, $image]
                );
            }

            $this->setFlash('success', 'Product succesvol toegevoegd!');
            $this->redirect('/dashboard');
        }

        $this->render('product/add', [
            'categories' => $this->allowed_categories,
            'states' => $this->allowed_states
        ]);
    }

    public function edit() {
        $this->requireLogin();

        $product_id = $_GET['id'] ?? 0;
        $product = $this->db->fetch(
            "SELECT * FROM products WHERE id = ? AND user_id = ?",
            [$product_id, $_SESSION['user_id']]
        );

        if (!$product) {
            $this->setFlash('danger', 'Product niet gevonden.');
            $this->redirect('/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $category = $_POST['category'] ?? '';
            $state = $_POST['state'] ?? '';
            $price = floatval($_POST['price'] ?? 0);
            $specs = trim($_POST['specs'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            // Validatie
            if (empty($name) || empty($category) || empty($state) || empty($specs)) {
                $this->setFlash('danger', 'Vul alle verplichte velden in.');
                $this->redirect("/product/edit?id={$product_id}");
            }

            if (!in_array($category, $this->allowed_categories)) {
                $this->setFlash('danger', 'Ongeldige categorie.');
                $this->redirect("/product/edit?id={$product_id}");
            }

            if (!in_array($state, $this->allowed_states)) {
                $this->setFlash('danger', 'Ongeldige staat.');
                $this->redirect("/product/edit?id={$product_id}");
            }

            if ($price <= 0) {
                $this->setFlash('danger', 'Voer een geldig bedrag in.');
                $this->redirect("/product/edit?id={$product_id}");
            }

            if (count($tags) > MAX_PRODUCT_TAGS) {
                $this->setFlash('danger', 'Maximaal ' . MAX_PRODUCT_TAGS . ' tags toegestaan.');
                $this->redirect("/product/edit?id={$product_id}");
            }

            // Update product
            $this->db->query(
                "UPDATE products 
                 SET name = ?, category = ?, state = ?, price = ?, specs = ?, description = ? 
                 WHERE id = ? AND user_id = ?",
                [$name, $category, $state, $price, $specs, $description, $product_id, $_SESSION['user_id']]
            );

            // Update tags
            $this->db->query("DELETE FROM product_tags WHERE product_id = ?", [$product_id]);
            foreach ($tags as $tag) {
                $this->db->query(
                    "INSERT INTO product_tags (product_id, tag) VALUES (?, ?)",
                    [$product_id, $tag]
                );
            }

            // Verwerk nieuwe afbeeldingen
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = 'uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $this->db->query(
                                "INSERT INTO product_images (product_id, image_url) VALUES (?, ?)",
                                [$product_id, $filepath]
                            );
                        }
                    }
                }
            }

            $this->setFlash('success', 'Product succesvol bijgewerkt!');
            $this->redirect('/dashboard');
        }

        // Haal huidige tags op
        $tags = $this->db->fetchAll(
            "SELECT tag FROM product_tags WHERE product_id = ?",
            [$product_id]
        );
        $tags = array_column($tags, 'tag');

        // Haal huidige afbeeldingen op
        $images = $this->db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = ?",
            [$product_id]
        );

        $this->render('product/edit', [
            'product' => $product,
            'categories' => $this->allowed_categories,
            'states' => $this->allowed_states,
            'tags' => $tags,
            'images' => $images
        ]);
    }

    public function delete() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $product_id = $_POST['id'] ?? 0;

            // Controleer of het product bestaat en van de gebruiker is
            $product = $this->db->fetch(
                "SELECT * FROM products WHERE id = ? AND user_id = ?",
                [$product_id, $_SESSION['user_id']]
            );

            if (!$product) {
                $this->setFlash('danger', 'Product niet gevonden.');
                $this->redirect('/dashboard');
            }

            // Verwijder gerelateerde data
            $this->db->query("DELETE FROM product_tags WHERE product_id = ?", [$product_id]);
            $this->db->query("DELETE FROM product_images WHERE product_id = ?", [$product_id]);
            $this->db->query("DELETE FROM favorites WHERE product_id = ?", [$product_id]);
            $this->db->query("DELETE FROM products WHERE id = ?", [$product_id]);

            $this->setFlash('success', 'Product succesvol verwijderd!');
        }

        $this->redirect('/dashboard');
    }
} 