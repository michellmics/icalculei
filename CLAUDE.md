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
   - `answer` (opcional): resposta direta de 40 a 60 palavras abaixo do título, no lugar do lead (destaque no Google). Se tiver números (tabelas, datas), atualizar junto quando eles mudarem.
   - SEO: toda calculadora tem `seo_title` (busca de cauda longa no começo, até ~55 caracteres) e pelo menos 2 perguntas frequentes em `<details>` (viram FAQPage). Textos curtos e diretos.
4. Nova calculadora: criar nos dois arquivos com o mesmo id; ela ganha página, sitemap e aparece no diretório sozinha.
   Depois rode `php bin/og-images.php` para gerar o banner de compartilhamento dela (também ao mudar nome ou descrição).
5. Campo de valor em dinheiro: `<input id="..." inputmode="numeric" data-money value="1.000,00">` (máscara automática; ler com `parseNumber`).

## Tabelas oficiais (INSS, IR, seguro-desemprego)
- Bloco `TABELAS OFICIAIS` em `public/assets/js/calculators.js` (`TAX_TABLES`), com fonte e `validFrom`.
- Conferir SEMPRE em fonte oficial (gov.br: Receita Federal, INSS/Previdência) antes de mudar.
- Depois de mudar, atualizar `reviewed` de: salario-liquido, rescisao, decimo-terceiro, ferias.
- As tabelas também aparecem no texto: `$taxTablesHtml` no topo de `content/tools.php` (13º e férias), junto com as tabelas de exemplos dessas duas páginas e os exemplos da notícia do 13º. Recalcular e atualizar ao mudar.
- Vigente: tabelas de 2026 (Portaria Interministerial MPS/MF nº 13/2026; Lei 15.270/2025 com redução até R$ 7.350).
- Seguro-desemprego: `TAX_TABLES.unemploymentInsurance` (tabela do MTE, muda todo janeiro pelo INPC; piso = salário mínimo). Ao mudar, atualizar `reviewed` e o texto de seguro-desemprego em `content/tools.php`.
- IPVA e licenciamento por estado: objeto `STATES` dentro de `CALCULATORS["ipva"]` (muda todo ano; conferir nas Sefaz/Detrans). IOF de crédito: constantes em `CALCULATORS["financiamento-veiculo"]`.

## Publicar uma notícia
- `content/news.php`: novo item no começo da lista. `id` em minúsculas com hífens.
- `body`: strings = parágrafos, `['heading' => ...]` = subtítulo, `['ad' => true]` = anúncio no meio.
- `related_tools`: ids de `content/tools.php`. Não inventar números atuais (taxas, valores) sem fonte.
- Depois de salvar a foto JPG, rode `php bin/news-webp.php` (gera os WebP de 960 px e 480 px que o site usa com srcset).
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
- `public/ads.txt`: linha do AdSense (pub-1658139075721224). Precisa estar em public/ para abrir em /ads.txt.

## Desempenho (PageSpeed)
- Fontes hospedadas no site (`public/assets/fonts`, @font-face no topo do site.css, preload no layout). Não voltar para o Google Fonts.
- Nas páginas de calculadora, `calculators.js` carrega logo depois do `#calculator` (em `site/tool.php`, sem defer) para não haver CLS. Na página inicial carrega no fim, com defer.
- Páginas públicas mandam `Cache-Control: s-maxage=600` para o cache do Cloudflare; estáticos com 1 ano (`public/.htaccess`).
- Colunas em grid sempre com `grid-template-columns: minmax(0, 1fr)` para nada estourar a largura no celular.

## Calculadora de viagem (/calculadoras/custo-de-viagem)
- Servidor: `app/Services/TripPlanner.php` (endereços e rota no OpenRouteService, praças de pedágio no Overpass/OpenStreetMap com o valor da tag `charge` quando existir) e `app/Controllers/TripController.php` (`/api/viagem/rota` e `/api/viagem/pedagios`).
- `.env`: `ORS_API_KEY` (grátis: 2.000 rotas e 1.000 buscas por dia; o código para em 1.800/900). `HTTP_CA_BUNDLE=windows` só no Windows de desenvolvimento.
- Cache em `storage/cache/trip` (endereços 30 dias, rotas e pedágios 7 dias). Limite de 30 rotas por hora por visitante.
- Front: `CALCULATORS["custo-de-viagem"]` (Leaflet do cdnjs com SRI, mapa do OpenStreetMap; crédito "© OpenStreetMap" obrigatório).

## Consulta FIPE (/calculadoras/depreciacao-veiculo, aba "Consultar a FIPE")
- `app/Services/FipeClient.php` usa a API interna do site veiculos.fipe.org.br (grátis, sem chave, NÃO é oficial: pode mudar). Plano B: a aba "Estimar o futuro" funciona sem a FIPE.
- Cache em `storage/cache/fipe`: preço de mês passado fica para sempre; listas 30 dias; meses de referência 1 dia.
- Rotas: `/api/fipe/marcas|modelos|anos|historico` (`app/Controllers/FipeController.php`). Combustível vem no código do ano ("2020-5": 5 = flex); ano 32000 = zero km.
- Gráfico com Chart.js (cdnjs, com SRI), carregado só quando há resultado.
- Página inicial: destaque principal = calculadora de viagem (`.trip-hero`); ao lado, índices IPCA/IGP-M/Selic (`app/Services/EconomicIndicators.php`, Banco Central SGS, cache 6 h, rota `/api/indices`, preenchido pelo site.js) e atalho da consulta FIPE.

## Correção monetária (/calculadoras/correcao-monetaria)
- Séries mensais do Banco Central em `app/Services/MonetaryIndexes.php` (IPCA 433, IGP-M 189, INPC 188, IGP-DI 190, Selic 4390, CDI 4391, poupança 195 a partir de 2012). Cache 12 h em `storage/cache/indexes` com atualização incremental (só os meses novos); se o Banco Central cair, usa a última versão.
- Rota `/api/indices/serie?indice=...`; a conta (composta, do mês inicial ao final) é feita no navegador.
- Atalho na página inicial: `.correction-promo`, acima das notícias do destaque.

## Feriados (/feriados/{ano} e /feriados/{ano}/{uf})
- Dados em `content/holidays.php` (nacionais, facultativos, estaduais e das 27 capitais; datas "mm-dd" ou relativas à Páscoa). Lógica em `app/Services/Holidays.php`, páginas em `HolidayController` (+ `.ics` em `/agenda`). Conferir datas locais todo ano e atualizar `reviewed`.
- Card "Próximo feriado" na página inicial (canto inferior direito, no lugar da antiga conta rápida). 80 páginas no sitemap (ano atual e o próximo, nacional, por mês e por estado).
- Páginas por mês: `/feriados/2026/novembro` (mesma rota dos estados; `HolidayController::state` reconhece o mês por `Holidays::MONTH_SLUGS`). Mostram nacionais, estaduais e das capitais de todos os estados (`Holidays::localForMonth`), dias úteis e perguntas frequentes geradas a partir dos dados (FAQPage).

## App instalado (PWA): `public/assets/js/app-shell.js`
- Só no modo app: puxar para atualizar e barra de navegação no rodapé (Voltar, Avançar, Início, Atualizar) em telas de celular. Marcação `#app-nav` nos layouts do site e do painel.

## FIPE em produção
- A FIPE (atrás do Cloudflare dela) só responde a acessos do Brasil: o servidor (EUA) recebe HTTP 403, direto ou por Worker (confirmado: Worker chamado pelo servidor roda em Miami e leva 403).
- Histórico: o NAVEGADOR chama o Cloudflare Worker `cloudflare/fipe-worker.js` (`GET /historico`, só aceita Origin do site; roda em São Paulo para visitantes do Brasil). Endereço em `FIPE_PROXY_URL` no .env → atributo `data-fipe-worker` na página e liberado no CSP (`Http::sendSecurityHeaders`). Mudou o Worker? Colar de novo no painel do Cloudflare.
- Sem Worker ou se ele falhar: `/api/fipe/historico` no servidor, que cai para a Parallelum (só o valor atual) e a tela avisa. Listas (marcas/modelos/anos) vêm do servidor pela Parallelum (mesmos códigos da FIPE).
- Erros de serviços externos respondem 503 (o Cloudflare troca respostas 502 pela página de erro dele). Histórico no servidor pede os 6 meses em paralelo (`HttpClient::requestMany`).

## Faixa "Indicadores" (topo da página inicial)
- `app/Services/MarketTicker.php` + rota `/api/indicadores`; preenchida e animada pelo site.js. Itens: Bitcoin (AwesomeAPI), CDI (BCB 4389), gasolina/etanol/diesel S10 (planilha semanal da ANP, aba BRASIL, semana atual × anterior), poupança (BCB 195) e Focus (IPCA e Selic esperados para o ano).
- Cache por item em `storage/cache/ticker`; fonte que falhar usa o último valor guardado ou some da faixa.
- Teste local: o `php -S` atende um pedido por vez e às vezes derruba CSS/fontes no primeiro carregamento (e com o service worker ativo). Nos testes com puppeteer use `setBypassServiceWorker(true)`.

## Notificações push no app do painel (aviso a cada 1.000 visitantes)
- `app/Services/WebPush.php` (VAPID + criptografia aes128gcm só com openssl; conferido com os valores do RFC 8291) e `app/Services/PushNotifier.php` (`VISITOR_MILESTONE`).
- Total de visitantes: tabela `site_counters` (`visitors`), soma 1 a cada visitante novo em `VisitController` (`Visit::countNewVisitor`, atômico). Múltiplo de 1.000 → avisa todos os aparelhos de `push_subscriptions`; inscrição vencida (404/410) é apagada.
- Chaves VAPID criadas sozinhas em `storage/keys/vapid.json` (no .gitignore; o deploy não mexe em storage/). Apagou o arquivo? Ativar de novo no painel.
- Painel → Visitas, quadro "Avisos no celular": ativar, enviar teste, desativar (admin.js). `sw-painel.js` mostra a notificação e abre o painel ao tocar. No iPhone só no app instalado (iOS 16.4+).

## Investimentos e FGTS
- `investimentos` (CDB × LCI/LCA × Tesouro Selic × poupança): IR regressivo por depósito, custódia B3 0,20% a.a. acima de R$ 10 mil no Tesouro Selic, LCI/LCA com carência de 6 meses (Res. CMN 5.215/2025). CDI e poupança vêm de `/api/indicadores`. Regras conferidas em 01/10/2026 (MP 1.303 caducou: tabela regressiva e isenção de LCI/LCA mantidas).
- `fgts` (8% do salário, 3% a.a., multa de 40%) e `saque-aniversario-fgts` (tabela da Caixa, Lei 13.932/2019). Antecipação: a partir de 01/11/2026, até 3 parcelas (atualizar o texto depois dessa data).

## Compartilhar no WhatsApp (todas as calculadoras)
- Barra `#share-bar` em `site/tool.php`; lógica em `calculators.js` (`setupShareBar`, `applySharedValues`). O link leva os campos curtos na URL (`?id-do-campo=valor`, até 40 caracteres; sem textarea) e os botões `.segmented`; quem abre vê a mesma conta. `utm_source=whatsapp` ou `link` aparece em "Origem" no painel.

## Banner de compartilhamento (og:image)
- `php bin/og-images.php` gera PNGs 1200×630 em `public/assets/img/og/` (um por calculadora + `vibe2000.png` geral + `feriados.png`), com GD e a fonte IBM Plex Sans (OFL) de `bin/fonts/`. Os PNGs vão no commit; o servidor não desenha nada.
- `share_banner($nome)` (helpers.php) devolve o endereço com `?v=` da data do arquivo. Notícias usam a própria foto.

## Encurtador de URL (/calculadoras/encurtador-url)
- `app/Controllers/ShortLinkController.php` + `app/Models/ShortLink.php`, tabela `short_links` (migration 005). `POST /api/encurtar` cria; `/l/{code}` redireciona (302, conta cliques, noindex, fora do sitemap e do service worker).
- Mesmo link longo = mesmo código. Limite de 20 links por hora por visitante. Link de golpe/spam: apagar a linha no banco.

## Ferramentas de TI (categoria "dev", nome no menu: TI)
- Tudo roda no navegador (nada vai para o servidor): `json-csv` (Excel BR = `;` + vírgula decimal + BOM), `regex` (padrões prontos em `PRESETS`), `cores` (o canvas do navegador interpreta a cor), `tamanho-dados` (bases 1.000 e 1.024 + tempo de download), `http-status` (lista `CODES`, inclui 520–526 do Cloudflare).
