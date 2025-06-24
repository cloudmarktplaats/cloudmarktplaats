<?php
require_once 'controllers/BaseController.php';

class DashboardController extends BaseController {
    public function index() {
        $this->requireLogin();

        // Haal producten van de gebruiker op
        $products = $this->db->fetchAll(
            "SELECT p.*, 
                    (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url
             FROM products p 
             WHERE p.user_id = ? 
             ORDER BY p.created_at DESC",
            [$_SESSION['user_id']]
        );

        // Haal ongelezen berichten op
        $unread_messages = $this->db->fetch(
            "SELECT COUNT(*) as count 
             FROM messages 
             WHERE receiver_id = ? AND read_at IS NULL",
            [$_SESSION['user_id']]
        )['count'];

        // Haal recente berichten op
        $recent_messages = $this->db->fetchAll(
            "SELECT m.*, u.username as sender_username
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.receiver_id = ?
             ORDER BY m.created_at DESC
             LIMIT 5",
            [$_SESSION['user_id']]
        );

        $this->render('dashboard/index', [
            'user' => $this->user,
            'products' => $products,
            'unread_messages' => $unread_messages,
            'recent_messages' => $recent_messages
        ]);
    }
} 