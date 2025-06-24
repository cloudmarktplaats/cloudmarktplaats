<?php
require_once 'config.php';
require_once 'includes/Database.php';

try {
    $db = new Database();
    
    // Maak forum tabellen aan
    $db->query("
        CREATE TABLE IF NOT EXISTS forum_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Forum categorieën tabel aangemaakt.<br>";
    
    $db->query("
        CREATE TABLE IF NOT EXISTS forum_topics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES forum_categories(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "Forum topics tabel aangemaakt.<br>";
    
    $db->query("
        CREATE TABLE IF NOT EXISTS forum_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "Forum replies tabel aangemaakt.<br>";
    
    // Controleer of er al categorieën zijn
    $categories = $db->fetchAll("SELECT COUNT(*) as count FROM forum_categories");
    if ($categories[0]['count'] == 0) {
        // Voeg basis categorieën toe
        $db->query("
            INSERT INTO forum_categories (name, description) VALUES
            ('Algemeen', 'Algemene discussies over hardware en IT'),
            ('Servers', 'Discussies over server hardware en configuraties'),
            ('Networking', 'Netwerk apparatuur en configuraties'),
            ('Storage', 'Opslag oplossingen en hardware'),
            ('Security', 'IT beveiliging en best practices')
        ");
        echo "Basis forum categorieën toegevoegd.<br>";
    }
    
    echo "<br>Forum setup voltooid! Je kunt nu <a href='/forum'>naar het forum</a> gaan.";
} catch (Exception $e) {
    die("Setup mislukt: " . $e->getMessage());
} 