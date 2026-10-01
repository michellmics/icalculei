-- Encurtador de URL (/calculadoras/encurtador-url): cada link curto vira uma linha.
-- O endereço curto é /l/{code}. Para tirar um link do ar (golpe, spam), apague a linha.
CREATE TABLE short_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(12) NOT NULL,
    url VARCHAR(2000) NOT NULL,
    -- sha256 do endereço longo: o mesmo link sempre recebe o mesmo código
    url_hash CHAR(64) NOT NULL,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY short_links_code_unique (code),
    UNIQUE KEY short_links_url_hash_unique (url_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
