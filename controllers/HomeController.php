<?php
require_once 'controllers/BaseController.php';

class HomeController extends BaseController {
    public function index() {
        // Haal recente producten op
        $recent_products = $this->db->fetchAll(
            "SELECT p.*, u.username 
             FROM products p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.approved = 1 
             ORDER BY p.created_at DESC 
             LIMIT 8"
        );

        // Haal populaire categorieën op
        $popular_categories = $this->db->fetchAll(
            "SELECT category, COUNT(*) as product_count 
             FROM products 
             WHERE approved = 1 
             GROUP BY category 
             ORDER BY product_count DESC 
             LIMIT 6"
        );

        $this->render('home/index', [
            'recent_products' => $recent_products,
            'popular_categories' => $popular_categories
        ]);
    }
} 