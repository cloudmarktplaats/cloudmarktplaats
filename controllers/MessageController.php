<?php
require_once 'controllers/BaseController.php';

class MessageController extends BaseController {
    public function index() {
        $this->requireLogin();
        
        $conversations = $this->getConversations();
        $selected_user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;
        $messages = [];
        $other_user = null;
        $user_products = [];

        if ($selected_user_id) {
            $messages = $this->getMessages($selected_user_id);
            $other_user = $this->getOtherUser($selected_user_id);
            $user_products = $this->getUserProducts();
        }

        $this->render('messages/index', [
            'conversations' => $conversations,
            'selected_user_id' => $selected_user_id,
            'messages' => $messages,
            'other_user' => $other_user,
            'user_products' => $user_products
        ]);
    }

    public function send() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/messages');
        }

        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
        $message = trim($_POST['message'] ?? '');
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;

        if (!$receiver_id || empty($message)) {
            $this->setFlash('Ongeldige berichtgegevens.', 'danger');
            $this->redirect('/messages');
        }

        $this->db->query("
            INSERT INTO messages (sender_id, receiver_id, product_id, message) 
            VALUES (?, ?, ?, ?)
        ", [
            $_SESSION['user_id'],
            $receiver_id,
            $product_id,
            $message
        ]);

        $this->redirect("/messages?user={$receiver_id}");
    }

    private function getConversations() {
        return $this->db->fetchAll("
            SELECT DISTINCT 
                CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id 
                    ELSE m.sender_id 
                END as other_user_id,
                u.username as other_username,
                p.name as product_name,
                p.id as product_id,
                (
                    SELECT message 
                    FROM messages 
                    WHERE (sender_id = ? AND receiver_id = other_user_id) 
                       OR (sender_id = other_user_id AND receiver_id = ?)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT COUNT(*) 
                    FROM messages 
                    WHERE receiver_id = ? 
                    AND sender_id = other_user_id 
                    AND is_read = 0
                ) as unread_count
            FROM messages m
            JOIN users u ON u.id = CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END
            LEFT JOIN products p ON p.id = m.product_id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY m.created_at DESC
        ", [
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
    }

    private function getMessages($other_user_id) {
        $messages = $this->db->fetchAll("
            SELECT m.*, 
                   u_sender.username as sender_username,
                   u_receiver.username as receiver_username,
                   p.name as product_name,
                   p.id as product_id
            FROM messages m
            JOIN users u_sender ON m.sender_id = u_sender.id
            JOIN users u_receiver ON m.receiver_id = u_receiver.id
            LEFT JOIN products p ON m.product_id = p.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ", [
            $_SESSION['user_id'],
            $other_user_id,
            $other_user_id,
            $_SESSION['user_id']
        ]);

        // Markeer berichten als gelezen
        $this->db->query("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ", [$other_user_id, $_SESSION['user_id']]);

        return $messages;
    }

    private function getOtherUser($user_id) {
        return $this->db->fetch("
            SELECT id, username 
            FROM users 
            WHERE id = ?
        ", [$user_id]);
    }

    private function getUserProducts() {
        return $this->db->fetchAll("
            SELECT p.*, u.username as seller_username
            FROM products p
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ? OR p.id IN (
                SELECT product_id 
                FROM orders 
                WHERE buyer_id = ?
            )
            ORDER BY p.created_at DESC
        ", [$_SESSION['user_id'], $_SESSION['user_id']]);
    }
} 