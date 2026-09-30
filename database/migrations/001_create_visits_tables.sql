-- Contador de visitas (mesmo modelo do painel do Pote Político)
-- Cada página vista vira uma linha. O visitante é o hash de um cookie anônimo (nada de IP).
CREATE TABLE visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor CHAR(32) NOT NULL,
    -- 1 = primeira visita deste navegador
    is_new TINYINT(1) NOT NULL DEFAULT 0,
    -- página conhecida do site, ex.: "calculadora · Rescisão trabalhista"
    page VARCHAR(120) NOT NULL,
    -- de onde veio: google, direto, whatsapp... ("interno" = navegação dentro do site)
    source VARCHAR(80) NOT NULL,
    device ENUM('celular', 'computador', 'tablet') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY visits_created_at_index (created_at),
    KEY visits_visitor_index (visitor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem está com o site aberto agora (o navegador avisa a cada 30 segundos)
CREATE TABLE visits_online (
    visitor CHAR(32) NOT NULL PRIMARY KEY,
    page VARCHAR(120) NOT NULL,
    device ENUM('celular', 'computador', 'tablet') NOT NULL,
    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY visits_online_seen_at_index (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cálculos feitos em cada calculadora (conta uma vez por página aberta)
CREATE TABLE tool_uses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_id VARCHAR(40) NOT NULL,
    visitor CHAR(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY tool_uses_created_at_index (created_at),
    KEY tool_uses_tool_id_index (tool_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
