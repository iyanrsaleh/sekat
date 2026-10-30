<?php

declare(strict_types=1);

use App\Controller\ApiTokenController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\ProxyController;
use App\Controller\SettingsController;
use App\Controller\CsrfController;
use App\Model\AuditLog;
use App\Controller\MeController;
use App\Controller\PageController;
use App\Controller\PostSearchController;
use App\Controller\ResourceController;
use App\Controller\SystemController;
use App\Controller\UploadController;
use App\Core\Database;
use App\Core\Router;
use App\Proxy\ProxyClient;
use App\Core\RateLimiter;
use App\Middleware\ApiTokenMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequireRole;
use App\Repository\ApiTokenRepository;
use App\Repository\AuditRepository;
use App\Repository\SettingRepository;
use App\Repository\EmailTokenRepository;
use App\Repository\CrudRepository;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;

return static function (Router $router): void {
    $database = Database::fromConfigFile(__DIR__ . '/database.php');

    $users = new UserRepository($database);
    $tokens = new TokenRepository($database);
    $auth = new AuthMiddleware($tokens);
    $csrf = new CsrfMiddleware();
    $audit = new AuditRepository($database);
    $debug = (bool) ((require __DIR__ . '/app.php')['debug'] ?? false);
    $oauth = require __DIR__ . '/oauth.php';
    $googleClientId = (string) ($oauth['google']['client_id'] ?? '');
    $googleClientSecret = (string) ($oauth['google']['client_secret'] ?? '');
    $authController = new AuthController($users, $tokens, $audit, new EmailTokenRepository($database), $debug, (bool) getenv('AUTH_COOKIE_REFRESH'), $googleClientId, $googleClientSecret);
    $rateLimit = new RateLimitMiddleware(new RateLimiter(dirname(__DIR__, 2) . '/vendor/storage/cache'), 10, 60);
    $adminOnly = new RequireRole('admin');
    $settingsController = new SettingsController(new SettingRepository($database), $audit);
    $apiTokens = new ApiTokenRepository($database);
    $apiTokenController = new ApiTokenController($apiTokens);

    // API sistem
    $system = new SystemController($database);
    $router->get('/api/ping', [$system, 'ping']);
    $router->get('/api/stats', [$system, 'stats'], [$auth, $adminOnly]);

    // Auth
    $router->post('/api/auth/login', [$authController, 'login'], [$rateLimit]);
    $router->get('/api/auth/google', [$authController, 'googleClient']);
    $router->post('/api/auth/google', [$authController, 'google'], [$rateLimit]);
    $router->post('/api/auth/register', [$authController, 'register'], [$rateLimit]);
    $router->post('/api/auth/refresh', [$authController, 'refresh'], [$csrf, $rateLimit]);
    $router->post('/api/auth/verify-email', [$authController, 'verifyEmail'], [$rateLimit]);
    $router->post('/api/auth/forgot-password', [$authController, 'forgotPassword'], [$rateLimit]);
    $router->post('/api/auth/reset-password', [$authController, 'resetPassword'], [$rateLimit]);
    $router->post('/api/auth/logout', [$authController, 'logout'], [$csrf, $auth]);
    $uploads = new UploadController(dirname(__DIR__, 2) . '/vendor/storage/uploads');
    $meController = new MeController($users, $uploads, $audit, $tokens);
    $router->get('/api/me', [$meController, 'show'], [$auth]);
    $router->put('/api/me', [$meController, 'updateProfile'], [$csrf, $auth]);
    $router->post('/api/me/logout-all', [$meController, 'logoutAll'], [$csrf, $auth]);
    $router->post('/api/me/avatar', [$meController, 'updateAvatar'], [$auth]);
    $router->put('/api/me/password', [$meController, 'changePassword'], [$csrf, $auth]);
    $router->get('/api/csrf', [new CsrfController(), 'token']);

    // Pengaturan dasar
    $router->get('/api/settings/public', [$settingsController, 'publicIndex']);
    $router->get('/api/settings', [$settingsController, 'index'], [$auth, $adminOnly]);
    $router->put('/api/settings', [$settingsController, 'update'], [$csrf, $auth, $adminOnly]);

    // Token API (admin): nama + masa berlaku + grant
    $router->get('/api/api-tokens', [$apiTokenController, 'index'], [$auth, $adminOnly]);
    $router->post('/api/api-tokens', [$apiTokenController, 'store'], [$csrf, $auth, $adminOnly]);
    $router->delete('/api/api-tokens/{name}', [$apiTokenController, 'destroy'], [$csrf, $auth, $adminOnly]);
    $router->get('/api/api-grants', [$apiTokenController, 'grantsIndex'], [$auth, $adminOnly]);
    $router->post('/api/api-grants', [$apiTokenController, 'grantStore'], [$csrf, $auth, $adminOnly]);
    $router->delete('/api/api-grants/{name}/{kind}/{resource}', [$apiTokenController, 'grantDestroy'], [$csrf, $auth, $adminOnly]);

    // Contoh resource yang dilindungi token API (grant 'api/protected')
    $router->get('/api/protected', [$system, 'ping'], [new ApiTokenMiddleware($apiTokens, 'api', 'protected')]);

    // Upload (tulis admin, baca publik)
    $router->post('/api/uploads', [$uploads, 'store'], [$auth, $adminOnly]);
    $router->get('/api/uploads', [$uploads, 'index'], [$auth, $adminOnly]);
    $router->get('/api/uploads/{year}/{month}/{name}', [$uploads, 'show']);
    $router->delete('/api/uploads/{year}/{month}/{name}', [$uploads, 'destroy'], [$csrf, $auth, $adminOnly]);

    // Audit log (baca saja, admin)
    $router->get('/api/audit-logs', [new ResourceController(AuditLog::class, new CrudRepository($database, AuditLog::class)), 'index'], [$auth, $adminOnly]);

    // Query kustom (didahulukan sebelum rute resource)
    $router->get('/api/posts/search', [new PostSearchController($database), 'index']);

    // Auto CRUD: satu Model = satu resource (hanya Model $exposed)
    /** @var array<int, class-string<\App\Core\Model>> $models */
    $models = require __DIR__ . '/models.php';

    foreach ($models as $model) {
        if (!$model::exposed()) {
            continue;
        }

        $resource = $model::resource();
        $controller = new ResourceController($model, new CrudRepository($database, $model), $audit);

        $router->get('/api/' . $resource, [$controller, 'index']);
        $router->post('/api/' . $resource, [$controller, 'store'], [$csrf, $auth, $adminOnly]);
        $router->get('/api/' . $resource . '/export', [$controller, 'export'], [$auth, $adminOnly]);
        $router->get('/api/' . $resource . '/{id}', [$controller, 'show']);
        $router->put('/api/' . $resource . '/{id}', [$controller, 'update'], [$csrf, $auth, $adminOnly]);
        $router->patch('/api/' . $resource . '/{id}', [$controller, 'update'], [$csrf, $auth, $adminOnly]);
        $router->delete('/api/' . $resource . '/{id}', [$controller, 'destroy'], [$csrf, $auth, $adminOnly]);
        $router->post('/api/' . $resource . '/{id}/restore', [$controller, 'restore'], [$csrf, $auth, $adminOnly]);
    }

    // Proxy: /api/{alias}/{path} diteruskan ke URL tujuan (alias tidak bocorkan host)
    $proxyAliases = (array) ((require __DIR__ . '/proxy.php')['aliases'] ?? []);
    $envUpstreams = getenv('NX_PROXY_UPSTREAMS');
    $proxyUpstreams = $envUpstreams !== false && $envUpstreams !== ''
        ? (is_array(json_decode($envUpstreams, true)) ? json_decode($envUpstreams, true) : [])
        : require __DIR__ . '/upstreams.php';
    $proxy = new ProxyController($proxyAliases, $proxyUpstreams, new ProxyClient());
    foreach ($proxyAliases as $proxyAlias) {
        $router->any('/api/' . $proxyAlias, static function (array $params, $request) use ($proxy, $proxyAlias) {
            return $proxy->dispatch(['alias' => $proxyAlias] + $params, $request);
        });
        $router->any('/api/' . $proxyAlias . '/{path:.*}', static function (array $params, $request) use ($proxy, $proxyAlias) {
            return $proxy->dispatch(['alias' => $proxyAlias] + $params, $request);
        });
    }

    $router->any('/api/{path:.*}', [$system, 'notFound']);

    // Dashboard (area admin) — dipisah dari halaman publik
    $router->get('/dashboard', [new DashboardController(), 'index']);
    $router->get('/dashboard/{page}', [new DashboardController(), 'show']);
    $router->get('/dashboard/{page}/{rest:.*}', [new DashboardController(), 'show']);

    // Frontend dinamis: nama halaman diambil dari segmen URL,
    // file dicari di public/<halaman>.html
    $router->get('/', [PageController::class, 'home']);
    $router->get('/{page}', [PageController::class, 'show']);
    $router->get('/{page}/{rest:.*}', [PageController::class, 'show']);
};