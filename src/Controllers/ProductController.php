<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Models\Product;

class ProductController extends BaseController
{
    private Product $productModel;

    private const ALLOWED_CATEGORIES = [
        'Servers', 'Netwerk', 'Storage', 'Workstations',
        'Laptops', 'Componenten', 'Randapparatuur', 'Datacenter', 'Overig',
    ];
    private const ALLOWED_STATES = ['Nieuw', 'Als nieuw', 'Gebruikt', 'Voor reparatie'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $filters = [
            'category' => $_GET['category'] ?? null,
            'search' => $_GET['search'] ?? null,
            'state' => $_GET['state'] ?? null,
            'sort' => $_GET['sort'] ?? 'newest',
        ];

        $products = $this->productModel->getApproved($filters);

        $this->render('product/index', [
            'title' => 'Producten',
            'products' => $products,
            'filters' => $filters,
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function view(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product) {
            $this->flash('error', 'Product niet gevonden.');
            $this->redirect('/product');
            return;
        }

        $images = $this->productModel->getImages((int) $id);
        $tags = $this->productModel->getTags((int) $id);
        $reviewModel = new \App\Models\Review();
        $reviews = $reviewModel->getForProduct((int) $id);

        $this->render('product/view', [
            'title' => $product['name'],
            'product' => $product,
            'images' => $images,
            'tags' => $tags,
            'reviews' => $reviews,
        ]);
    }

    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $category = $_POST['category'] ?? '';
            $state = $_POST['state'] ?? '';
            $price = $_POST['price'] ?? '';
            $specs = trim($_POST['specs'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            $errors = [];
            if (empty($name)) $errors[] = 'Naam is verplicht.';
            if (!in_array($category, self::ALLOWED_CATEGORIES)) $errors[] = 'Ongeldige categorie.';
            if (!in_array($state, self::ALLOWED_STATES)) $errors[] = 'Ongeldige staat.';
            if (!is_numeric($price) || $price < 0) $errors[] = 'Ongeldige prijs.';
            if (count($tags) > (int) Config::get('MAX_PRODUCT_TAGS', 5)) {
                $errors[] = 'Maximaal ' . Config::get('MAX_PRODUCT_TAGS', 5) . ' tags.';
            }

            if (!empty($errors)) {
                $this->flash('error', implode('<br>', $errors));
                $this->render('product/add', [
                    'title' => 'Product Toevoegen',
                    'categories' => self::ALLOWED_CATEGORIES,
                    'states' => self::ALLOWED_STATES,
                    'input' => $_POST,
                ]);
                return;
            }

            $productId = $this->productModel->create([
                'user_id' => $this->userId(),
                'name' => $name,
                'category' => $category,
                'state' => $state,
                'price' => (float) $price,
                'specs' => $specs,
                'description' => $description,
                'approved' => Config::get('REQUIRE_APPROVAL', true) ? 0 : 1,
            ]);

            $this->handleImageUploads($productId);

            foreach (array_slice($tags, 0, (int) Config::get('MAX_PRODUCT_TAGS', 5)) as $tag) {
                if (!empty($tag)) {
                    $this->productModel->addTag($productId, $tag);
                }
            }

            $this->flash('success', 'Product toegevoegd! Het wordt beoordeeld door een moderator.');
            $this->redirect('/product/view/' . $productId);
            return;
        }

        $this->render('product/add', [
            'title' => 'Product Toevoegen',
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function edit(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product || $product['user_id'] !== $this->userId()) {
            $this->flash('error', 'Product niet gevonden of geen toegang.');
            $this->redirect('/dashboard');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'category' => $_POST['category'] ?? '',
                'state' => $_POST['state'] ?? '',
                'price' => (float) ($_POST['price'] ?? 0),
                'specs' => trim($_POST['specs'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            $this->productModel->update((int) $id, $data);

            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
            $this->productModel->deleteTags((int) $id);
            foreach (array_slice($tags, 0, (int) Config::get('MAX_PRODUCT_TAGS', 5)) as $tag) {
                if (!empty($tag)) {
                    $this->productModel->addTag((int) $id, $tag);
                }
            }

            $this->handleImageUploads((int) $id);

            $this->flash('success', 'Product bijgewerkt.');
            $this->redirect('/product/view/' . $id);
            return;
        }

        $images = $this->productModel->getImages((int) $id);
        $tags = $this->productModel->getTags((int) $id);

        $this->render('product/edit', [
            'title' => 'Product Bewerken',
            'product' => $product,
            'images' => $images,
            'tags' => $tags,
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function delete(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product || ($product['user_id'] !== $this->userId() && !$this->isAdmin())) {
            $this->flash('error', 'Geen toegang.');
            $this->redirect('/dashboard');
            return;
        }

        $images = $this->productModel->getImages((int) $id);
        foreach ($images as $image) {
            $path = dirname(__DIR__, 2) . '/uploads/products/' . $image['image_url'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->productModel->delete((int) $id);
        $this->flash('success', 'Product verwijderd.');
        $this->redirect('/dashboard');
    }

    private function handleImageUploads(int $productId): void
    {
        if (empty($_FILES['images']['name'][0])) {
            return;
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxImages = (int) Config::get('MAX_PRODUCT_IMAGES', 5);
        $uploaded = 0;

        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($uploaded >= $maxImages) break;
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['images']['size'][$i] > self::MAX_IMAGE_SIZE) continue;

            $tmpFile = $_FILES['images']['tmp_name'][$i];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS)) continue;

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($tmpFile, $destination)) {
                $this->productModel->addImage($productId, $filename);
                $uploaded++;
            }
        }
    }
}
