# iCalculei

Portal de calculadoras, conversores e notícias. PHP 8.2+ e MySQL, sem bibliotecas externas.

## Rodar localmente

```bash
php bin/migrate.php          # cria/atualiza as tabelas (pode rodar sempre)
composer serve               # ou: php -S localhost:8000 -t public public/index.php
```

Abra http://localhost:8000. O `.env` precisa estar preenchido (modelo em `.env.example`).

## Painel administrativo

Defina o acesso ao painel no `.env` (um único usuário; senha com no mínimo 10 caracteres):

```
ADMIN_USER=seu-usuario
ADMIN_PASSWORD=uma-senha-forte
```

Depois entre em http://localhost:8000/painel. Para trocar a senha, mude no `.env` (quem estiver logado é desconectado).

O painel tem: visitas (online agora, indicadores, tendência, gráficos, mapa de calor, origens e aparelhos),
uso das calculadoras (com aviso de revisão) e mensagens do formulário de contato.

## Onde fica cada coisa

| O que | Onde |
|---|---|
| Calculadoras: nome, textos, data de revisão | `content/tools.php` |
| Calculadoras: as contas | `public/assets/js/calculators.js` |
| Tabelas de INSS e IR | bloco `TABELAS OFICIAIS` em `public/assets/js/calculators.js` |
| Notícias | `content/news.php` |
| Destaques da página inicial | `content/showcase.php` |
| Espaços de anúncio | `content/ads.php` + `ADSENSE_CLIENT` no `.env` |
| Termos, privacidade, sobre, contato | `app/Views/pages/` |
| Dados do responsável (aparecem nos termos) | `.env`: `SITE_OWNER_NAME`, `SITE_OWNER_DOCUMENT`, `CONTACT_EMAIL`, `SITE_OWNER_CITY` |
| Visual | `public/assets/css/site.css` |

## Endereços

| Página | Endereço |
|---|---|
| Início e busca | `/`, `/?busca=juros`, `/?categoria=trabalho` |
| Calculadora | `/calculadoras/{id}` |
| Notícias | `/noticias`, `/noticias/{id}` |
| Institucionais | `/sobre`, `/contato`, `/termos-de-uso`, `/privacidade` |
| Google | `/sitemap.xml`, `/robots.txt` |
| Painel | `/painel` |

## Publicar no cPanel

1. Envie a pasta do projeto para fora do `public_html` (ex.: `/home/usuario/icalculei`).
2. Aponte o domínio para `icalculei/public` (ou coloque o conteúdo de `public/` no `public_html` e ajuste o caminho do `bootstrap.php` no `index.php`).
3. Crie o `.env` no servidor **fora da pasta do projeto**, um ou dois níveis acima (ex.: projeto em `/home/USUARIO/public_html/icalculei` → `/home/USUARIO/.env`), com `APP_ENV=production`, `APP_DEBUG=false`, `ADS_PLACEHOLDERS=false` e o `APP_URL` com https.
4. Rode `php bin/migrate.php` pelo Terminal do cPanel e defina `ADMIN_USER` e `ADMIN_PASSWORD` no `.env` do servidor.
5. Para as próximas versões: faça commit e push no GitHub e clique em **Painel → 🚀 Atualizar site** (baixa o código e roda as migrations). Se o repositório for privado, preencha `DEPLOY_TOKEN` no `.env` do servidor. O PHP do servidor precisa das extensões curl e zip.
6. Ative o HTTPS forçado no `public/.htaccess` (linhas comentadas).
6. Depois da aprovação do AdSense: `ADSENSE_CLIENT` no `.env` e os `slot_id` em `content/ads.php`.
