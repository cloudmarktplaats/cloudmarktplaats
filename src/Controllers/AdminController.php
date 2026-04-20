<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\User;

class AdminController extends BaseController
{
    private Product $productModel;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
        $this->userModel = new User();
    }

    public function index(): void
    {
        $stats = [
            'total_products' => $this->db->fetch("SELECT COUNT(*) as cnt FROM products")['cnt'],
            'pending_products' => $this->db->fetch("SELECT COUNT(*) as cnt FROM products WHERE approved = 0")['cnt'],
            'total_users' => $this->db->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'],
            'total_reviews' => $this->db->fetch("SELECT COUNT(*) as cnt FROM reviews")['cnt'],
        ];

        $recentProducts = $this->db->fetchAll(
            "SELECT p.*, u.username FROM products p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 10"
        );
        $recentUsers = $this->db->fetchAll(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT 10"
        );

        $this->render('admin/index', [
            'title' => 'Admin Dashboard',
            'stats' => $stats,
            'recent_products' => $recentProducts,
            'recent_users' => $recentUsers,
        ]);
    }

    public function products(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'category' => $_GET['category'] ?? null,
        ];

        $status = $_GET['status'] ?? 'pending';
        if ($status === 'pending') {
            $filters['approved'] = 0;
        } elseif ($status === 'approved') {
            $filters['approved'] = 1;
        }

        $products = $this->productModel->getForAdmin($filters);

        $this->render('admin/products', [
            'title' => 'Producten Beheren',
            'products' => $products,
            'current_status' => $status,
        ]);
    }

    public function approveProduct(string $id): void
    {
        $this->productModel->approve((int) $id);
        $this->flash('success', 'Product goedgekeurd.');
        $this->redirect('/admin/products');
    }

    public function rejectProduct(string $id): void
    {
        $this->productModel->reject((int) $id);
        $this->flash('success', 'Product afgewezen.');
        $this->redirect('/admin/products');
    }

    public function deleteProduct(string $id): void
    {
        $this->productModel->delete((int) $id);
        $this->flash('success', 'Product verwijderd.');
        $this->redirect('/admin/products');
    }

    public function users(): void
    {
        $search = $_GET['search'] ?? null;
        $role = $_GET['role'] ?? null;

        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];

        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($search) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY created_at DESC";
        $users = $this->db->fetchAll($sql, $params);

        $this->render('admin/users', [
            'title' => 'Gebruikers Beheren',
            'users' => $users,
            'current_role' => $role,
        ]);
    }

    public function toggleAdmin(string $id): void
    {
        if ((int) $id === $this->userId()) {
            $this->flash('error', 'Je kunt je eigen rol niet wijzigen.');
            $this->redirect('/admin/users');
            return;
        }
        $this->userModel->toggleAdmin((int) $id);
        $this->flash('success', 'Gebruikersrol aangepast.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(string $id): void
    {
        if ((int) $id === $this->userId()) {
            $this->flash('error', 'Je kunt je eigen account niet verwijderen via admin.');
            $this->redirect('/admin/users');
            return;
        }
        $this->userModel->delete((int) $id);
        $this->flash('success', 'Gebruiker verwijderd.');
        $this->redirect('/admin/users');
    }
}
