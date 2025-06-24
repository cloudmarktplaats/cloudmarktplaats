<?php

namespace App\Controllers;

class HomeController extends BaseController {
    public function index(): void {
        // Haal recente producten op
        $recentProducts = $this->db->fetchAll(
            "SELECT p.*, u.username 
             FROM products p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.status = 'active' 
             ORDER BY p.created_at DESC 
             LIMIT 8"
        );

        // Haal populaire tags op
        $popularTags = $this->db->fetchAll(
            "SELECT tag, COUNT(*) as count 
             FROM product_tags 
             GROUP BY tag 
             ORDER BY count DESC 
             LIMIT 10"
        );

        $this->view('home/index', [
            'title' => 'Welkom bij Cloudmarkplaats.nl',
            'recentProducts' => $recentProducts,
            'popularTags' => $popularTags
        ]);
    }
} 