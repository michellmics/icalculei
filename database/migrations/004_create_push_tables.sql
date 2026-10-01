-- Notificações push no app do painel (aviso a cada 1.000 visitantes)
-- Cada aparelho que ativou as notificações no painel vira uma linha.
CREATE TABLE push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(700) NOT NULL,
    -- sha256 do endpoint (endereço longo demais para índice único)
    endpoint_hash CHAR(64) NOT NULL,
    public_key VARCHAR(120) NOT NULL,
    auth_secret VARCHAR(40) NOT NULL,
    -- nome curto do aparelho, ex.: "Android · Chrome"
    device VARCHAR(80) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY push_subscriptions_endpoint_hash_unique (endpoint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contadores do site. "visitors" = total de visitantes desde o início (soma 1 a cada visitante novo).
CREATE TABLE site_counters (
    name VARCHAR(40) NOT NULL PRIMARY KEY,
    value BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Começa com quem já visitou o site
INSERT INTO site_counters (name, value) SELECT 'visitors', COUNT(DISTINCT visitor) FROM visits;
