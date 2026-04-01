<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Message;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $userId = $this->userId();
        $productModel = new Product();
        $messageModel = new Message();

        $products = $productModel->getByUser($userId);
        $unreadCount = $messageModel->getUnreadCount($userId);
        $recentMessages = $messageModel->getRecent($userId, 5);

        $favorites = $this->db->fetchAll(
            "SELECT p.*, u.username,
             (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
             FROM favorites f
             JOIN products p ON f.product_id = p.id
             JOIN users u ON p.user_id = u.id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC",
            [$userId]
        );

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'products' => $products,
            'unread_count' => $unreadCount,
            'recent_messages' => $recentMessages,
            'favorites' => $favorites,
        ]);
    }
}
