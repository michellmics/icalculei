<?php

declare(strict_types=1);

/**
 * Ponto de entrada único do site: toda requisição passa por aqui.
 */

use App\Controllers\AdminController;
use App\Controllers\FipeController;
use App\Controllers\HolidayController;
use App\Controllers\IndicatorsController;
use App\Controllers\SiteController;
use App\Controllers\TripController;
use App\Controllers\VisitController;
use App\Core\Http;
use App\Core\Router;

// No servidor embutido do PHP (desenvolvimento), arquivos como CSS e JS são entregues direto
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';

Http::sendSecurityHeaders();

$router = new Router();

// Site
$router->get('/', [SiteController::class, 'home']);
$router->get('/calculadoras/{slug}', [SiteController::class, 'tool']);
$router->get('/noticias', [SiteController::class, 'newsList']);
$router->get('/noticias/{slug}', [SiteController::class, 'article']);
$router->get('/sobre', [SiteController::class, 'about']);
$router->get('/contato', [SiteController::class, 'contact']);
$router->post('/contato', [SiteController::class, 'sendContact']);
$router->get('/termos-de-uso', [SiteController::class, 'terms']);
$router->get('/privacidade', [SiteController::class, 'privacy']);
// Calendário de feriados (a rota da agenda vem antes da do estado)
$router->get('/feriados', [HolidayController::class, 'index']);
$router->get('/feriados/{year}', [HolidayController::class, 'year']);
$router->get('/feriados/{year}/agenda', [HolidayController::class, 'agenda']);
$router->get('/feriados/{year}/{state}', [HolidayController::class, 'state']);
$router->get('/feriados/{year}/{state}/agenda', [HolidayController::class, 'stateAgenda']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt', [SiteController::class, 'robots']);

// Contador de visitas
$router->post('/api/visita', [VisitController::class, 'track']);

// Calculadora de viagem (rota e pedágios)
$router->get('/api/viagem/rota', [TripController::class, 'route']);
$router->get('/api/viagem/pedagios', [TripController::class, 'tolls']);

// Consulta FIPE (calculadora de depreciação)
$router->get('/api/fipe/marcas', [FipeController::class, 'brands']);
$router->get('/api/fipe/modelos', [FipeController::class, 'models']);
$router->get('/api/fipe/anos', [FipeController::class, 'years']);
$router->get('/api/fipe/historico', [FipeController::class, 'history']);

// Índices econômicos da página inicial (IPCA, IGP-M, Selic)
$router->get('/api/indices', [IndicatorsController::class, 'show']);
$router->get('/api/indices/serie', [IndicatorsController::class, 'series']);

// Painel administrativo
$router->get('/painel', [AdminController::class, 'loginForm']);
$router->post('/painel', [AdminController::class, 'login']);
$router->post('/painel/sair', [AdminController::class, 'logout']);
$router->get('/painel/visitas', [AdminController::class, 'visits']);
$router->get('/painel/online', [AdminController::class, 'online']);
$router->get('/painel/calculadoras', [AdminController::class, 'tools']);
$router->get('/painel/mensagens', [AdminController::class, 'messages']);
$router->post('/painel/mensagens/{id}', [AdminController::class, 'updateMessage']);
$router->get('/painel/atualizar', [AdminController::class, 'deployPage']);
$router->post('/painel/atualizar', [AdminController::class, 'deploy']);

$path = '/' . trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path === '/' ? '/' : $path);
