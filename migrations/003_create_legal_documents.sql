CREATE TABLE legal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('tos','privacy') NOT NULL,
    version INT UNSIGNED NOT NULL,
    language CHAR(2) NOT NULL,
    content LONGTEXT NOT NULL,
    published_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_type_version_lang (type, version, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
