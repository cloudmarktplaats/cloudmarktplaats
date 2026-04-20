<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\Message;
use App\Models\Product;

class MessageController extends BaseController
{
    private Message $messageModel;

    public function __construct()
    {
        parent::__construct();
        $this->messageModel = new Message();
    }

    public function index(string $user_id = ''): void
    {
        $userId = $this->userId();
        $conversations = $this->messageModel->getConversations($userId);

        $selectedUserId = !empty($user_id) ? (int) $user_id : null;
        $messages = [];
        $otherUser = null;

        if ($selectedUserId) {
            $messages = $this->messageModel->getMessages($userId, $selectedUserId);
            $this->messageModel->markAsRead($userId, $selectedUserId);
            $otherUser = $this->db->fetch("SELECT id, username FROM users WHERE id = ?", [$selectedUserId]);
        } elseif (!empty($conversations)) {
            $selectedUserId = (int) $conversations[0]['other_user_id'];
            $messages = $this->messageModel->getMessages($userId, $selectedUserId);
            $this->messageModel->markAsRead($userId, $selectedUserId);
            $otherUser = $this->db->fetch("SELECT id, username FROM users WHERE id = ?", [$selectedUserId]);
        }

        $productModel = new Product();
        $userProducts = $productModel->getByUser($userId);

        $this->render('messages/index', [
            'title' => 'Berichten',
            'conversations' => $conversations,
            'messages' => $messages,
            'other_user' => $otherUser,
            'selected_user_id' => $selectedUserId,
            'user_products' => $userProducts,
        ]);
    }

    public function send(): void
    {
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $productId = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : null;

        if ($receiverId === 0 || empty($message)) {
            $this->flash('error', 'Ontvanger en bericht zijn verplicht.');
            $this->redirect('/message');
            return;
        }

        $this->messageModel->send($this->userId(), $receiverId, $message, $productId);
        $this->redirect('/message/conversation/' . $receiverId);
    }
}
