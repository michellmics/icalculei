# Vibe2000 — guia para atualizações

Portal de calculadoras, conversores e notícias (PHP 8.2 + MySQL, sem Composer/dependências, roda no cPanel).
O dono pede atualizações de calculadoras e notícias com frequência; o conteúdo fica separado do código para isso.

## Regras do projeto
- Código simples e legível; nomes em inglês, comentários e textos do site em português.
- Sem commits até o dono pedir. O dono sobe o servidor sozinho (`composer serve`); não deixe servidor rodando.
- Tema sempre claro. Resultados são estimativas: manter os avisos (rodapé e notas das calculadoras).

## Atualizar uma calculadora
1. Conta: `public/assets/js/calculators.js`, objeto `CALCULATORS["id"]` (`html` + `setup`).
2. Textos (lead, explicação, perguntas frequentes) e metadados: `content/tools.php`, mesmo id.
3. Sempre atualizar `reviewed` (data da revisão) em `content/tools.php`.
   - SEO: toda calculadora tem `seo_title` (busca de cauda longa no começo, até ~55 caracteres) e pelo menos 2 perguntas frequentes em `<details>` (viram FAQPage). Textos curtos e diretos.
4. Nova calculadora: criar nos dois arquivos com o mesmo id; ela ganha página, sitemap e aparece no diretório sozinha.
5. Campo de valor em dinheiro: `<input id="..." inputmode="numeric" data-money value="1.000,00">` (máscara automática; ler com `parseNumber`).

## Tabelas oficiais (INSS, IR, seguro-desemprego)
- Bloco `TABELAS OFICIAIS` em `public/assets/js/calculators.js` (`TAX_TABLES`), com fonte e `validFrom`.
- Conferir SEMPRE em fonte oficial (gov.br: Receita Federal, INSS/Previdência) antes de mudar.
- Depois de mudar, atualizar `reviewed` de: salario-liquido, rescisao, decimo-terceiro, ferias.
- Vigente: tabelas de 2026 (Portaria Interministerial MPS/MF nº 13/2026; Lei 15.270/2025 com redução até R$ 7.350).
- Seguro-desemprego: `TAX_TABLES.unemploymentInsurance` (tabela do MTE, muda todo janeiro pelo INPC; piso = salário mínimo). Ao mudar, atualizar `reviewed` e o texto de seguro-desemprego em `content/tools.php`.
- IPVA e licenciamento por estado: objeto `STATES` dentro de `CALCULATORS["ipva"]` (muda todo ano; conferir nas Sefaz/Detrans). IOF de crédito: constantes em `CALCULATORS["financiamento-veiculo"]`.

## Publicar uma notícia
- `content/news.php`: novo item no começo da lista. `id` em minúsculas com hífens.
- `body`: strings = parágrafos, `['heading' => ...]` = subtítulo, `['ad' => true]` = anúncio no meio.
- `related_tools`: ids de `content/tools.php`. Não inventar números atuais (taxas, valores) sem fonte.
- Depois de salvar a foto JPG, rode `php bin/news-webp.php` (gera o WebP que o site usa).
- Imagem: SEMPRE foto real (nunca ícone/desenho), livre (domínio público/CC0, ex.: rawpixel, StockSnap via api.openverse.org), JPG 960 px em `public/assets/img/news/{id}.jpg`, com `image_alt` e `image_credit`.
- Destaques/“mais lidas”/“em alta”: `content/showcase.php`.

## .env
- Procurado nesta ordem: um nível acima do projeto, dois níveis acima, raiz do projeto (`App\Core\Env::loadFromProject`). Em produção fica fora da pasta do site. O painel (Atualizar site) mostra qual foi lido.
- Painel: `ADMIN_USER`/`ADMIN_PASSWORD` (login) e `ADMIN_REMEMBER_KEY` ("manter conectado" por 180 dias; trocar a chave desconecta todos os aparelhos).

## Estrutura
- `public/index.php` rotas · `app/Controllers` (Site, Visit = contador, Admin = painel) · `app/Views`
- `app/Services/VisitStats.php` números do painel (modelo do painel do projeto direitaconservada)
- `database/migrations/*.sql` + `php bin/migrate.php` (lógica em `app/Services/Migrator.php`) · login do painel: `ADMIN_USER` e `ADMIN_PASSWORD` no `.env`

## Atualizar produção
- Painel → 🚀 Atualizar site (`/painel/atualizar`, `app/Services/Deployer.php`): baixa o último commit da branch pelo GitHub (ZIP), troca os arquivos e roda as migrations pendentes. Não mexe em `.env` nem `storage/`.
- Só funciona depois de commit + push. Bloqueado com `APP_ENV=local`.

## PWA (dois apps)
- Site: `public/manifest.webmanifest` + `public/sw.js` (guarda páginas visitadas para abrir offline). Painel: `public/manifest-painel.webmanifest` + `public/sw-painel.js` (não guarda dados).
- Ícones em `public/icons/` (site-* verde, painel-* escuro). Mudou a lógica de um service worker? Aumente o `CACHE_VERSION` dele.
- Convite "Instalar o app" (só no site): `public/assets/js/install-app.js` + estilos `.install-*` no site.css. Botões: qualquer elemento com `data-install-app` (topo, rodapé e lateral da home).

## SEO
- Títulos, descrição, canonical, robots e og:* no layout do site; dados estruturados JSON-LD em `app/Services/StructuredData.php` (WebSite, WebApplication, FAQPage, Article, BreadcrumbList).
- `/sitemap.xml` (com lastmod) e `/robots.txt` são gerados sozinhos a partir do conteúdo. Busca por texto (`?busca=`) é noindex.
