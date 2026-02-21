<?php
// ============================================================
// app/Controllers/AdminController.php
// ============================================================

namespace Dunrovin\Controllers;

use Dunrovin\Models\{ArticleModel, AuthModel, CategoryModel, TagModel};
use Dunrovin\Helpers\{View, Security, Session, Str, Upload};

class AdminController
{
    private ArticleModel  $articles;
    private CategoryModel $categories;
    private TagModel      $tags;
    private AuthModel     $auth;

    public function __construct()
    {
        $this->articles   = new ArticleModel();
        $this->categories = new CategoryModel();
        $this->tags       = new TagModel();
        $this->auth       = new AuthModel();
    }

    // ----------------------------------------------------------
    // Auth guard
    // ----------------------------------------------------------
    private function requireAuth(): void
    {
        if (!Session::has('admin_id')) {
            header('Location: /' . ADMIN_PATH . '/login');
            exit;
        }
    }

    private function currentAdmin(): array|false
    {
        return $this->auth->findById((int)Session::get('admin_id'));
    }

    // ----------------------------------------------------------
    // Login
    // ----------------------------------------------------------
    public function loginForm(): void
    {
        if (Session::has('admin_id')) {
            header('Location: /' . ADMIN_PATH . '/dashboard');
            exit;
        }
        View::make('admin.login', [
            'csrf'    => Security::generateCsrf(),
            'error'   => Session::getFlash('login_error'),
        ])->render();
    }

    public function loginPost(): void
    {
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('login_error', 'Invalid security token. Please try again.');
            header('Location: /' . ADMIN_PATH . '/login');
            exit;
        }

        $email = Security::strip($_POST['email'] ?? '');

        if (!Security::checkLoginAttempts($email)) {
            $remaining = ceil(Security::getRemainingLockout($email) / 60);
            Session::flash('login_error', "Too many failed attempts. Try again in {$remaining} minutes.");
            header('Location: /' . ADMIN_PATH . '/login');
            exit;
        }

        $admin = $this->auth->findByEmail($email);
        if (!$admin || !Security::verifyPassword($_POST['password'] ?? '', $admin['password'])) {
            Security::incrementLoginAttempts($email);
            Session::flash('login_error', 'Invalid email or password.');
            header('Location: /' . ADMIN_PATH . '/login');
            exit;
        }

        Security::clearLoginAttempts($email);

        // Rehash if needed
        if (Security::needsRehash($admin['password'])) {
            $this->auth->updatePassword($admin['id'], Security::hashPassword($_POST['password']));
        }

        Session::set('admin_id',   $admin['id']);
        Session::set('admin_role', $admin['role']);
        Session::set('admin_name', $admin['username']);

        header('Location: /' . ADMIN_PATH . '/dashboard');
        exit;
    }

    public function logout(): void
    {
        Session::destroy();
        header('Location: /' . ADMIN_PATH . '/login');
        exit;
    }

    // ----------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------
    public function dashboard(): void
    {
        $this->requireAuth();
        $db = \Dunrovin\Models\Database::connect();

        $stats = [
            'published' => (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn(),
            'draft'     => (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='draft'")->fetchColumn(),
            'scheduled' => (int)$db->query("SELECT COUNT(*) FROM articles WHERE status='scheduled'")->fetchColumn(),
            'views'     => (int)$db->query("SELECT SUM(view_count) FROM articles")->fetchColumn(),
            'categories'=> (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
            'tags'      => (int)$db->query("SELECT COUNT(*) FROM tags")->fetchColumn(),
        ];

        $recent = $this->articles->adminGetAll(1);

        View::make('admin.dashboard', [
            'stats'    => $stats,
            'recent'   => $recent,
            'admin'    => $this->currentAdmin(),
            'csrf'     => Security::generateCsrf(),
            'flash'    => Session::getFlash('success'),
        ])->render();
    }

    // ----------------------------------------------------------
    // Articles list
    // ----------------------------------------------------------
    public function articlesList(): void
    {
        $this->requireAuth();
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $status   = $_GET['status'] ?? '';
        $articles = $this->articles->adminGetAll($page, $status);

        View::make('admin.articles.list', [
            'articles' => $articles,
            'admin'    => $this->currentAdmin(),
            'status'   => $status,
            'flash'    => Session::getFlash('success'),
            'error'    => Session::getFlash('error'),
            'csrf'     => Security::generateCsrf(),
        ])->render();
    }

    // ----------------------------------------------------------
    // Create article
    // ----------------------------------------------------------
    public function createForm(): void
    {
        $this->requireAuth();
        View::make('admin.articles.form', [
            'article'    => null,
            'categories' => $this->categories->getAll(),
            'tags'       => $this->tags->getAll(),
            'admin'      => $this->currentAdmin(),
            'csrf'       => Security::generateCsrf(),
            'error'      => Session::getFlash('error'),
            'mode'       => 'create',
        ])->render();
    }

    public function createPost(): void
    {
        $this->requireAuth();
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'CSRF token mismatch.');
            header('Location: /' . ADMIN_PATH . '/articles/create');
            exit;
        }

        $data = $this->extractArticleData();
        $data['admin_id'] = (int)Session::get('admin_id');

        // Handle upload
        if (!empty($_FILES['featured_image']['name'])) {
            $data['featured_image'] = Upload::handleImage($_FILES['featured_image']) ?: null;
        }

        // Unique slug
        $data['slug'] = $this->uniqueSlug(Str::slug($data['title']));

        $id = $this->articles->create($data);
        Session::flash('success', 'Article created successfully.');
        header('Location: /' . ADMIN_PATH . '/articles/' . $id . '/edit');
        exit;
    }

    // ----------------------------------------------------------
    // Edit article
    // ----------------------------------------------------------
    public function editForm(array $params): void
    {
        $this->requireAuth();
        $article = $this->articles->adminGetById((int)$params['id']);
        if (!$article) { header('Location: /' . ADMIN_PATH . '/articles'); exit; }

        View::make('admin.articles.form', [
            'article'    => $article,
            'categories' => $this->categories->getAll(),
            'tags'       => $this->tags->getAll(),
            'admin'      => $this->currentAdmin(),
            'csrf'       => Security::generateCsrf(),
            'error'      => Session::getFlash('error'),
            'flash'      => Session::getFlash('success'),
            'mode'       => 'edit',
        ])->render();
    }

    public function editPost(array $params): void
    {
        $this->requireAuth();
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'CSRF token mismatch.');
            header('Location: /' . ADMIN_PATH . '/articles/' . $params['id'] . '/edit');
            exit;
        }

        $id   = (int)$params['id'];
        $data = $this->extractArticleData();

        if (!empty($_FILES['featured_image']['name'])) {
            $uploaded = Upload::handleImage($_FILES['featured_image']);
            if ($uploaded) $data['featured_image'] = $uploaded;
        }

        // Preserve or rebuild slug
        $requestedSlug = Str::slug($_POST['slug'] ?? '');
        if (empty($requestedSlug)) $requestedSlug = Str::slug($data['title']);
        $data['slug'] = $this->uniqueSlug($requestedSlug, $id);

        $this->articles->update($id, $data);
        Session::flash('success', 'Article updated successfully.');
        header('Location: /' . ADMIN_PATH . '/articles/' . $id . '/edit');
        exit;
    }

    // ----------------------------------------------------------
    // Delete article
    // ----------------------------------------------------------
    public function deletePost(array $params): void
    {
        $this->requireAuth();
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Security error.');
            header('Location: /' . ADMIN_PATH . '/articles');
            exit;
        }
        $this->articles->delete((int)$params['id']);
        Session::flash('success', 'Article deleted.');
        header('Location: /' . ADMIN_PATH . '/articles');
        exit;
    }

    // ----------------------------------------------------------
    // Categories
    // ----------------------------------------------------------
    public function categoriesList(): void
    {
        $this->requireAuth();
        View::make('admin.categories', [
            'categories' => $this->categories->getAll(),
            'admin'      => $this->currentAdmin(),
            'csrf'       => Security::generateCsrf(),
            'flash'      => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ])->render();
    }

    public function categoryCreate(): void
    {
        $this->requireAuth();
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Security error.'); header('Location: /' . ADMIN_PATH . '/categories'); exit;
        }
        $this->categories->create([
            'name' => Security::strip($_POST['name'] ?? ''),
            'slug' => Str::slug($_POST['name'] ?? ''),
            'description' => Security::strip($_POST['description'] ?? ''),
        ]);
        Session::flash('success', 'Category created.');
        header('Location: /' . ADMIN_PATH . '/categories'); exit;
    }

    public function categoryDelete(array $params): void
    {
        $this->requireAuth();
        if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Security error.'); header('Location: /' . ADMIN_PATH . '/categories'); exit;
        }
        $this->categories->delete((int)$params['id']);
        Session::flash('success', 'Category deleted.');
        header('Location: /' . ADMIN_PATH . '/categories'); exit;
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    private function extractArticleData(): array
    {
        return [
            'title'            => Security::strip($_POST['title'] ?? ''),
            'excerpt'          => Security::strip($_POST['excerpt'] ?? ''),
            'body'             => $_POST['body'] ?? '', // Rich HTML — sanitized separately if needed
            'category_id'      => (int)($_POST['category_id'] ?? 0),
            'status'           => in_array($_POST['status'] ?? '', ['draft','published','scheduled']) ? $_POST['status'] : 'draft',
            'published_at'     => !empty($_POST['published_at']) ? $_POST['published_at'] : null,
            'scheduled_at'     => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
            'meta_title'       => Security::strip($_POST['meta_title'] ?? ''),
            'meta_description' => Security::strip($_POST['meta_description'] ?? ''),
            'tags'             => array_map('intval', $_POST['tags'] ?? []),
        ];
    }

    private function uniqueSlug(string $base, int $excludeId = 0): string
    {
        $slug = $base;
        $i    = 1;
        while ($this->articles->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
