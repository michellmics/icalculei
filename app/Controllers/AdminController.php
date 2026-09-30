<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Http;
use App\Core\Session;
use App\Core\View;
use App\Models\ContactMessage;
use App\Models\RateLimit;
use App\Services\Content;
use App\Services\Deployer;
use App\Services\Migrator;
use App\Services\VisitStats;
use PDOException;
use Throwable;

/**
 * Área administrativa (/painel): login, visitas, uso das calculadoras, mensagens e atualização do site.
 */
class AdminController
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;
    private const MIN_PASSWORD_LENGTH = 10;
    private const REMEMBER_COOKIE = 'v2k_remember';
    private const REMEMBER_DAYS = 180;

    public function __construct()
    {
        Session::start();
        header('X-Robots-Tag: noindex, nofollow');
    }

    /**
     * Impressão digital do usuário e da senha do .env. Fica na sessão depois do login:
     * se o dono trocar o usuário ou a senha no .env, todas as sessões abertas caem.
     */
    private function credentialsFingerprint(): string
    {
        // ADMIN_REMEMBER_KEY entra na conta: trocar a chave no .env desconecta todo mundo
        return hash_hmac('sha256', config('admin_user') . "\0" . config('admin_password') . "\0" . config('admin_remember_key'), config('app_key') ?: 'vibe2000');
    }

    private function currentAdmin(): ?array
    {
        if (config('admin_user') === '') {
            return null;
        }
        $sessionFingerprint = Session::get('admin_fingerprint');
        if (is_string($sessionFingerprint) && hash_equals($this->credentialsFingerprint(), $sessionFingerprint)) {
            return ['user' => config('admin_user')];
        }

        // "Manter conectado": o cookie assinado reabre a sessão sem pedir a senha de novo
        if ($this->hasValidRememberCookie()) {
            Session::regenerate();
            Session::set('admin_fingerprint', $this->credentialsFingerprint());

            return ['user' => config('admin_user')];
        }

        return null;
    }

    /* ---------- Manter conectado (180 dias) ----------
       O cookie guarda só a data de validade e uma assinatura (HMAC) feita com o usuário, a senha e a
       ADMIN_REMEMBER_KEY do .env. Não dá para falsificar sem saber esses valores, e trocar qualquer um
       deles no .env invalida todos os cookies (todos os aparelhos saem). */
    private function canRemember(): bool
    {
        return config('admin_remember_key') !== '';
    }

    private function rememberSignature(int $expiresAt): string
    {
        return hash_hmac('sha256', 'remember|' . $expiresAt, $this->credentialsFingerprint());
    }

    private function hasValidRememberCookie(): bool
    {
        $cookieValue = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if (!$this->canRemember() || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $cookieValue, $parts)) {
            return false;
        }
        $expiresAt = (int) $parts[1];

        return $expiresAt > time() && hash_equals($this->rememberSignature($expiresAt), $parts[2]);
    }

    private function setRememberCookie(bool $remember): void
    {
        $expiresAt = $remember ? time() + self::REMEMBER_DAYS * 86400 : time() - 3600;
        $cookieValue = $remember ? $expiresAt . '.' . $this->rememberSignature($expiresAt) : '';
        setcookie(self::REMEMBER_COOKIE, $cookieValue, ['expires' => $expiresAt, 'path' => '/painel', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Session::isHttps()]);
    }

    /**
     * Só deixa continuar quem está logado. Pedidos do JavaScript recebem 401.
     */
    private function requireAdmin(bool $isJsonRequest = false): array
    {
        $admin = $this->currentAdmin();
        if ($admin === null) {
            if ($isJsonRequest) {
                Http::json([], 401);
            }
            Http::redirect('/painel');
        }

        return $admin;
    }

    private function render(string $viewName, array $data): void
    {
        $data += ['newMessages' => ContactMessage::countNew(), 'activeTab' => ''];
        echo View::render($viewName, $data, 'layouts/admin');
    }

    public function loginForm(): void
    {
        if ($this->currentAdmin() !== null) {
            Http::redirect('/painel/visitas');
        }
        echo View::render('admin/login', ['error' => Session::pullFlash('login_error'), 'canRemember' => $this->canRemember(), 'rememberDays' => self::REMEMBER_DAYS], null);
    }

    public function login(): void
    {
        if (!Csrf::isValid($_POST['_csrf_token'] ?? null)) {
            Session::flash('login_error', 'A página expirou. Tente de novo.');
            Http::redirect('/painel');
        }

        $limitKey = RateLimit::key('admin-login', $_SERVER['REMOTE_ADDR'] ?? '');
        if (RateLimit::tooManyAttempts($limitKey, self::MAX_LOGIN_ATTEMPTS)) {
            Session::flash('login_error', 'Muitas tentativas. Aguarde ' . self::LOGIN_LOCK_MINUTES . ' minutos.');
            Http::redirect('/painel');
        }

        // Usuário e senha ficam no .env (ADMIN_USER e ADMIN_PASSWORD)
        $configuredUser = config('admin_user');
        $configuredPassword = config('admin_password');
        if ($configuredUser === '' || strlen($configuredPassword) < self::MIN_PASSWORD_LENGTH) {
            Session::flash('login_error', 'Painel desativado: defina ADMIN_USER e ADMIN_PASSWORD (mínimo de ' . self::MIN_PASSWORD_LENGTH . ' caracteres) no arquivo .env.');
            Http::redirect('/painel');
        }

        $typedUser = trim((string) ($_POST['user'] ?? ''));
        $typedPassword = (string) ($_POST['password'] ?? '');
        // hash_equals compara em tempo constante (não dá pistas pelo tempo de resposta)
        $userMatches = hash_equals(hash('sha256', $configuredUser), hash('sha256', $typedUser));
        $passwordMatches = hash_equals(hash('sha256', $configuredPassword), hash('sha256', $typedPassword));
        if (!$userMatches || !$passwordMatches) {
            RateLimit::hit($limitKey, self::LOGIN_LOCK_MINUTES);
            Session::flash('login_error', 'Usuário ou senha incorretos.');
            Http::redirect('/painel');
        }

        RateLimit::clear($limitKey);
        Session::regenerate();
        Session::set('admin_fingerprint', $this->credentialsFingerprint());
        $this->setRememberCookie($this->canRemember() && ($_POST['remember'] ?? '') === '1');

        // Marca este navegador como do dono: as visitas dele não entram na contagem
        setcookie(VisitController::ADMIN_COOKIE, '1', ['expires' => time() + 365 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Session::isHttps()]);

        Http::redirect('/painel/visitas');
    }

    public function logout(): void
    {
        if (Csrf::isValid($_POST['_csrf_token'] ?? null)) {
            Session::forget('admin_fingerprint');
            Session::regenerate();
            $this->setRememberCookie(false); // sair também esquece este aparelho
        }
        Http::redirect('/painel');
    }

    public function visits(): void
    {
        $admin = $this->requireAdmin();
        $days = isset(VisitStats::PERIODS[(int) ($_GET['dias'] ?? 0)]) ? (int) $_GET['dias'] : 30;

        try {
            $stats = VisitStats::dashboard($days);
            $online = VisitStats::online();
            $databaseError = null;
        } catch (PDOException $exception) {
            $stats = null;
            $online = null;
            $databaseError = str_contains($exception->getMessage(), "doesn't exist")
                ? 'As tabelas de visitas ainda não existem. Rode: php bin/migrate.php'
                : 'Não foi possível ler as visitas no banco de dados.';
        }

        $this->render('admin/visits', [
            'pageTitle' => 'Visitas',
            'activeTab' => 'visits',
            'admin' => $admin,
            'days' => $days,
            'stats' => $stats,
            'online' => $online,
            'databaseError' => $databaseError,
        ]);
    }

    /**
     * "Online agora" em JSON, para o painel atualizar a cada 10 segundos.
     */
    public function online(): void
    {
        $this->requireAdmin(true);
        try {
            Http::json(VisitStats::online());
        } catch (PDOException) {
            Http::json([], 503);
        }
    }

    public function tools(): void
    {
        $admin = $this->requireAdmin();
        $usage = VisitStats::toolUsage();
        $rows = [];
        foreach (Content::tools() as $tool) {
            $currentUses = (int) ($usage['current'][$tool['id']] ?? 0);
            $previousUses = (int) ($usage['previous'][$tool['id']] ?? 0);
            $rows[] = ['tool' => $tool, 'uses' => $currentUses, 'change' => VisitStats::percentChange($currentUses, $previousUses)];
        }
        usort($rows, fn (array $first, array $second) => $second['uses'] <=> $first['uses']);

        $this->render('admin/tools', ['pageTitle' => 'Calculadoras', 'activeTab' => 'tools', 'admin' => $admin, 'rows' => $rows, 'categories' => Content::categories()]);
    }

    public function messages(): void
    {
        $admin = $this->requireAdmin();
        $this->render('admin/messages', [
            'pageTitle' => 'Mensagens',
            'activeTab' => 'messages',
            'admin' => $admin,
            'messages' => ContactMessage::latest(200),
            'subjects' => ContactMessage::SUBJECTS,
            'tools' => Content::tools(),
        ]);
    }

    /**
     * Atualizar site: mostra o commit do servidor e o do GitHub, com o botão de atualizar.
     */
    public function deployPage(): void
    {
        $admin = $this->requireAdmin();
        $remoteCommit = null;
        $remoteError = null;
        try {
            $remoteCommit = Deployer::remoteCommit();
        } catch (Throwable $exception) {
            $remoteError = $exception->getMessage();
        }
        $this->render('admin/deploy', [
            'pageTitle' => 'Atualizar site',
            'activeTab' => 'deploy',
            'admin' => $admin,
            'settings' => Deployer::config(),
            'lastDeploy' => Deployer::lastDeploy(),
            'remoteCommit' => $remoteCommit,
            'remoteError' => $remoteError,
            'isBlockedHere' => Deployer::isBlockedHere(),
            'result' => Session::pullFlash('deploy_result'),
            'migrations' => Migrator::status(),
        ]);
    }

    public function deploy(): void
    {
        $this->requireAdmin();
        if (!Csrf::isValid($_POST['_csrf_token'] ?? null)) {
            Session::flash('deploy_result', ['ok' => false, 'steps' => ['A página expirou. Tente de novo.'], 'commit' => null]);
            Http::redirect('/painel/atualizar');
        }
        Session::flash('deploy_result', Deployer::run());
        Http::redirect('/painel/atualizar');
    }

    public function updateMessage(string $messageId): void
    {
        $this->requireAdmin();
        $status = (string) ($_POST['status'] ?? '');
        if (Csrf::isValid($_POST['_csrf_token'] ?? null) && in_array($status, ['new', 'answered'], true)) {
            ContactMessage::setStatus((int) $messageId, $status);
        }
        Http::redirect('/painel/mensagens');
    }
}
