<?php
require_once 'controllers/BaseController.php';

class ProfileController extends BaseController {
    public function view() {
        $user_id = $_GET['id'] ?? null;
        
        if (!$user_id) {
            $this->redirect('/');
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        
        if (!$user) {
            $this->redirect('/');
        }

        // Haal producten van de gebruiker op
        $products = $this->db->fetchAll(
            "SELECT * FROM products WHERE user_id = ? AND approved = 1 ORDER BY created_at DESC",
            [$user_id]
        );

        // Haal reviews van de gebruiker op
        $reviews = $this->db->fetchAll(
            "SELECT r.*, p.name as product_name, p.id as product_id 
             FROM reviews r 
             JOIN products p ON r.product_id = p.id 
             WHERE r.user_id = ? 
             ORDER BY r.created_at DESC",
            [$user_id]
        );

        $this->render('profile/view', [
            'profile_user' => $user,
            'products' => $products,
            'reviews' => $reviews
        ]);
    }

    public function index() {
        $this->requireLogin();
        
        $user = $this->getUser();
        
        // Haal statistieken op
        $stats = [
            'products' => $this->db->fetch("SELECT COUNT(*) as count FROM products WHERE user_id = ?", [$user['id']])['count'],
            'topics' => $this->db->fetch("SELECT COUNT(*) as count FROM forum_topics WHERE user_id = ?", [$user['id']])['count'],
            'replies' => $this->db->fetch("SELECT COUNT(*) as count FROM forum_replies WHERE user_id = ?", [$user['id']])['count'],
            'favorites' => $this->db->fetch("SELECT COUNT(*) as count FROM favorites WHERE user_id = ?", [$user['id']])['count']
        ];
        
        $this->render('profile/index', [
            'user' => $user,
            'stats' => $stats
        ]);
    }

    public function products() {
        $this->requireLogin();
        
        $user = $this->getUser();
        $products = $this->db->fetchAll(
            "SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
            [$user['id']]
        );
        
        $this->render('profile/_products', [
            'products' => $products
        ]);
    }

    public function topics() {
        $this->requireLogin();
        
        $user = $this->getUser();
        $topics = $this->db->fetchAll(
            "SELECT * FROM forum_topics WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
            [$user['id']]
        );
        
        $this->render('profile/_topics', [
            'topics' => $topics
        ]);
    }

    public function edit() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            // Valideer gebruikersnaam
            if (empty($username)) {
                $errors[] = "Gebruikersnaam is verplicht.";
            } elseif (strlen($username) < 3) {
                $errors[] = "Gebruikersnaam moet minimaal 3 tekens lang zijn.";
            }
            
            // Valideer email
            if (empty($email)) {
                $errors[] = "Email is verplicht.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Ongeldig email adres.";
            }
            
            // Controleer of gebruikersnaam of email al bestaat
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
                [$username, $email, $_SESSION['user_id']]
            );
            
            if ($existing) {
                $errors[] = "Gebruikersnaam of email is al in gebruik.";
            }
            
            // Als er een nieuw wachtwoord wordt ingevoerd
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $errors[] = "Wachtwoord moet minimaal 6 tekens lang zijn.";
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = "Wachtwoorden komen niet overeen.";
                }
                
                // Controleer huidig wachtwoord
                $user = $this->getUser();
                if (!password_verify($current_password, $user['password'])) {
                    $errors[] = "Huidig wachtwoord is onjuist.";
                }
            }
            
            if (empty($errors)) {
                // Update gebruikersgegevens
                $query = "UPDATE users SET username = ?, email = ?";
                $params = [$username, $email];
                
                if (!empty($new_password)) {
                    $query .= ", password = ?";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                
                $query .= " WHERE id = ?";
                $params[] = $_SESSION['user_id'];
                
                $this->db->query($query, $params);
                
                $this->setFlash('success', 'Profiel succesvol bijgewerkt!');
                $this->redirect('/profile');
            } else {
                $this->setFlash('error', implode("<br>", $errors));
            }
        }
        
        $user = $this->getUser();
        $this->render('profile/edit', [
            'user' => $user
        ]);
    }

    public function delete() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = $this->getUser();
            
            // Verwijder alle gerelateerde data
            $this->db->query("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?", [$user['id'], $user['id']]);
            $this->db->query("DELETE FROM reviews WHERE user_id = ?", [$user['id']]);
            $this->db->query("DELETE FROM favorites WHERE user_id = ?", [$user['id']]);
            
            // Verwijder producten en bijbehorende reviews
            $products = $this->db->fetchAll("SELECT id FROM products WHERE user_id = ?", [$user['id']]);
            foreach ($products as $product) {
                $this->db->query("DELETE FROM reviews WHERE product_id = ?", [$product['id']]);
                $this->db->query("DELETE FROM favorites WHERE product_id = ?", [$product['id']]);
            }
            $this->db->query("DELETE FROM products WHERE user_id = ?", [$user['id']]);
            
            // Verwijder de gebruiker
            $this->db->query("DELETE FROM users WHERE id = ?", [$user['id']]);
            
            session_destroy();
            $this->setFlash('Je account is succesvol verwijderd.', 'success');
            $this->redirect('/');
        }

        $this->redirect('/profile/edit');
    }
} 