<?php
// ============================================================
// public/index.php — Dunrovin Group Front Controller
// ============================================================

define('DUNROVIN', true);

// -- Autoloader ----------------------------------------------
spl_autoload_register(function (string $class): void {
    // Namespace map: Dunrovin\X\Y → /app/X/Y.php
    $base  = dirname(__DIR__) . '/app/';
    $class = str_replace('Dunrovin\\', '', $class);
    $file  = $base . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require_once $file;
});

// -- Config --------------------------------------------------
require_once dirname(__DIR__) . '/config/config.php';

// -- Error handling ------------------------------------------
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_exception_handler(function (Throwable $e) {
        error_log('[Dunrovin] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        echo '<!DOCTYPE html><html><body><h1>500 — Server Error</h1><p>Something went wrong. Please try again.</p></body></html>';
    });
}

// -- Security headers ----------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (APP_ENV === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// -- Session -------------------------------------------------
use Dunrovin\Helpers\Session;
Session::start();

// -- Autoload consolidated helpers & models ------------------
require_once dirname(__DIR__) . '/app/Helpers/Helpers.php';
require_once dirname(__DIR__) . '/app/Models/Database.php';
require_once dirname(__DIR__) . '/app/Models/Models.php';

// -- Router --------------------------------------------------
$router = new Dunrovin\Router();

$public = new Dunrovin\Controllers\PublicController();
$admin  = new Dunrovin\Controllers\AdminController();
$ap     = ADMIN_PATH;

// == Public Routes ==========================================

$router->get('/',                  fn($p) => $public->home());
$router->get('/articles/:slug',    fn($p) => $public->article($p));
$router->get('/category/:slug',    fn($p) => $public->category($p));
$router->get('/search',            fn($p) => $public->search());
$router->get('/sitemap.xml',       fn($p) => $public->sitemap());
$router->get('/robots.txt',        fn($p) => $public->robots());

// == Admin Routes ===========================================

$router->get("/{$ap}/login",       fn($p) => $admin->loginForm());
$router->post("/{$ap}/login",      fn($p) => $admin->loginPost());
$router->post("/{$ap}/logout",     fn($p) => $admin->logout());

$router->get("/{$ap}/dashboard",   fn($p) => $admin->dashboard());

$router->get("/{$ap}/articles",    fn($p) => $admin->articlesList());
$router->get("/{$ap}/articles/create", fn($p) => $admin->createForm());
$router->post("/{$ap}/articles/store", fn($p) => $admin->createPost());
$router->get("/{$ap}/articles/:id/edit",   fn($p) => $admin->editForm($p));
$router->post("/{$ap}/articles/:id/update", fn($p) => $admin->editPost($p));
$router->post("/{$ap}/articles/:id/delete", fn($p) => $admin->deletePost($p));

$router->get("/{$ap}/categories",  fn($p) => $admin->categoriesList());
$router->post("/{$ap}/categories/store", fn($p) => $admin->categoryCreate());
$router->post("/{$ap}/categories/:id/delete", fn($p) => $admin->categoryDelete($p));

// Dispatch
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
