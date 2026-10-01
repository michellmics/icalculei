-- País e estado aproximados de cada visita (painel → Visitas).
-- Vêm dos cabeçalhos do Cloudflare (CF-IPCountry e cf-region-code); o IP continua não sendo guardado.
-- Visitas antigas e acessos sem Cloudflare (ex.: desenvolvimento) ficam com NULL.
ALTER TABLE visits
    ADD COLUMN country CHAR(2) NULL AFTER device,
    ADD COLUMN region VARCHAR(3) NULL AFTER country;
