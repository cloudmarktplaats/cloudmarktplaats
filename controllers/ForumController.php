<?php
class ForumController extends BaseController {
    public function index() {
        // Haal alle forum categorieën op met topic telling
        $query = "SELECT fc.*, 
                    (SELECT COUNT(*) FROM forum_topics WHERE category_id = fc.id) as topic_count,
                    (SELECT COUNT(*) FROM forum_replies fr 
                     JOIN forum_topics ft ON fr.topic_id = ft.id 
                     WHERE ft.category_id = fc.id) as reply_count
                 FROM forum_categories fc
                 ORDER BY fc.name ASC";
        
        $categories = $this->db->fetchAll($query);
        
        $this->render('forum/index', [
            'categories' => $categories
        ]);
    }

    public function new_category() {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $this->setFlash('error', 'Vul een naam in voor de categorie.');
                $this->redirect('/forum');
            }
            
            $this->db->query(
                "INSERT INTO forum_categories (name, description) VALUES (?, ?)",
                [$name, $description]
            );
            
            $this->setFlash('success', 'Categorie succesvol aangemaakt!');
            $this->redirect('/forum');
        }
        
        $this->render('forum/new_category');
    }

    public function category() {
        $category_id = $_GET['id'] ?? 0;
        
        // Haal categorie informatie op
        $category = $this->db->fetch(
            "SELECT * FROM forum_categories WHERE id = ?", 
            [$category_id]
        );
        
        if (!$category) {
            $this->render('404');
            return;
        }
        
        // Haal topics op met laatste reply informatie
        $query = "SELECT ft.*, 
                    u.username as author_name,
                    (SELECT COUNT(*) FROM forum_replies WHERE topic_id = ft.id) as reply_count,
                    (SELECT created_at FROM forum_replies 
                     WHERE topic_id = ft.id 
                     ORDER BY created_at DESC LIMIT 1) as last_reply_date,
                    (SELECT username FROM users u2 
                     JOIN forum_replies fr ON u2.id = fr.user_id 
                     WHERE fr.topic_id = ft.id 
                     ORDER BY fr.created_at DESC LIMIT 1) as last_reply_author
                 FROM forum_topics ft
                 JOIN users u ON ft.user_id = u.id
                 WHERE ft.category_id = ?
                 ORDER BY ft.updated_at DESC";
        
        $topics = $this->db->fetchAll($query, [$category_id]);
        
        $this->render('forum/category', [
            'category' => $category,
            'topics' => $topics
        ]);
    }

    public function topic() {
        $topic_id = $_GET['id'] ?? 0;
        
        // Haal topic informatie op
        $topic = $this->db->fetch(
            "SELECT ft.*, u.username as author_name, fc.name as category_name
             FROM forum_topics ft
             JOIN users u ON ft.user_id = u.id
             JOIN forum_categories fc ON ft.category_id = fc.id
             WHERE ft.id = ?", 
            [$topic_id]
        );
        
        if (!$topic) {
            $this->render('404');
            return;
        }
        
        // Verhoog aantal views
        $this->db->query(
            "UPDATE forum_topics SET views = views + 1 WHERE id = ?",
            [$topic_id]
        );
        
        // Haal replies op
        $replies = $this->db->fetchAll(
            "SELECT fr.*, u.username
             FROM forum_replies fr
             JOIN users u ON fr.user_id = u.id
             WHERE fr.topic_id = ?
             ORDER BY fr.created_at ASC",
            [$topic_id]
        );
        
        $this->render('forum/topic', [
            'topic' => $topic,
            'replies' => $replies
        ]);
    }

    public function new_topic() {
        $this->requireLogin();
        
        $category_id = $_GET['category_id'] ?? 0;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if (empty($title) || empty($content)) {
                $this->setFlash('error', 'Vul alle verplichte velden in.');
                $this->redirect("/forum/new_topic?category_id=" . $category_id);
            }
            
            // Maak nieuw topic aan
            $this->db->query(
                "INSERT INTO forum_topics (category_id, user_id, title, content) 
                 VALUES (?, ?, ?, ?)",
                [$category_id, $_SESSION['user_id'], $title, $content]
            );
            
            $topic_id = $this->db->lastInsertId();
            
            $this->setFlash('success', 'Topic succesvol aangemaakt!');
            $this->redirect("/forum/topic?id=" . $topic_id);
        }
        
        // Haal categorie informatie op
        $category = $this->db->fetch(
            "SELECT * FROM forum_categories WHERE id = ?",
            [$category_id]
        );
        
        if (!$category) {
            $this->render('404');
            return;
        }
        
        $this->render('forum/new_topic', [
            'category' => $category
        ]);
    }

    public function reply() {
        $this->requireLogin();
        
        $topic_id = $_POST['topic_id'] ?? 0;
        $content = trim($_POST['content'] ?? '');
        
        if (empty($content)) {
            $this->setFlash('error', 'Vul een reactie in.');
            $this->redirect("/forum/topic?id=" . $topic_id);
        }
        
        // Voeg reply toe
        $this->db->query(
            "INSERT INTO forum_replies (topic_id, user_id, content) 
             VALUES (?, ?, ?)",
            [$topic_id, $_SESSION['user_id'], $content]
        );
        
        // Update topic's updated_at
        $this->db->query(
            "UPDATE forum_topics SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$topic_id]
        );
        
        $this->setFlash('success', 'Reactie succesvol toegevoegd!');
        $this->redirect("/forum/topic?id=" . $topic_id);
    }
} 