-- Mensagens do formulário de contato (avisos de erro, sugestões, anúncios)
CREATE TABLE contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL,
    subject VARCHAR(30) NOT NULL,
    -- calculadora relacionada, quando o assunto é "encontrei um erro"
    tool_id VARCHAR(40) NULL,
    message TEXT NOT NULL,
    -- new = nova | answered = respondida
    status ENUM('new', 'answered') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY contact_messages_status_index (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administradores do painel (criados pelo terminal: php bin/create-admin.php)
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY admins_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de tentativas (login do painel e formulário de contato)
CREATE TABLE rate_limits (
    limit_key VARCHAR(120) NOT NULL PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
