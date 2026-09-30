-- O login do painel passou a usar ADMIN_USER e ADMIN_PASSWORD do arquivo .env.
-- A tabela de administradores não é mais usada.
DROP TABLE IF EXISTS admins;
