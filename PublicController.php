<?php
// ============================================================
// app/Controllers/PublicController.php
// ============================================================

namespace Dunrovin\Controllers;

use Dunrovin\Models\ArticleModel;
use Dunrovin\Models\CategoryModel;
use Dunrovin\Helpers\{View, Security, Str, Paginator};

class PublicController
{
    private ArticleModel  $articles;
    private CategoryModel $categories;

    public function __construct()
    {
        $this->articles   = new ArticleModel();
        $this->categories = new CategoryModel();
    }

    // ----------------------------------------------------------
    // Homepage
    // ----------------------------------------------------------
    public function home(): void
    {
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $total    = $this->articles->countPublished();
        $articles = $this->articles->getPublished($page);
        $paginator = new Paginator($total, ARTICLES_PER_PAGE, $page);

        View::make('public.home', [
            'articles'   => $articles,
            'paginator'  => $paginator,
            'categories' => $this->categories->getAll(),
            'pageTitle'  => APP_NAME . SEO_SEPARATOR . APP_TAGLINE,
            'metaDesc'   => DEFAULT_META_DESC,
        ])->layout('public')->render();
    }

    // ----------------------------------------------------------
    // Single article
    // ----------------------------------------------------------
    public function article(array $params): void
    {
        $article = $this->articles->getBySlug($params['slug']);
        if (!$article) {
            $this->notFound();
            return;
        }

        View::make('public.article', [
            'article'    => $article,
            'categories' => $this->categories->getAll(),
            'pageTitle'  => ($article['meta_title'] ?: $article['title']) . SEO_SEPARATOR . APP_NAME,
            'metaDesc'   => $article['meta_description'] ?: Str::excerpt($article['body']),
            'canonical'  => APP_URL . '/articles/' . $article['slug'],
        ])->layout('public')->render();
    }

    // ----------------------------------------------------------
    // Category archive
    // ----------------------------------------------------------
    public function category(array $params): void
    {
        $category = $this->categories->getBySlug($params['slug']);
        if (!$category) { $this->notFound(); return; }

        $page     = max(1, (int)($_GET['page'] ?? 1));
        $articles = $this->articles->getByCategory($params['slug'], $page);

        View::make('public.archive', [
            'articles'    => $articles,
            'category'    => $category,
            'categories'  => $this->categories->getAll(),
            'archiveType' => 'category',
            'pageTitle'   => $category['name'] . SEO_SEPARATOR . APP_NAME,
            'metaDesc'    => $category['description'] ?? DEFAULT_META_DESC,
        ])->layout('public')->render();
    }

    // ----------------------------------------------------------
    // Search
    // ----------------------------------------------------------
    public function search(): void
    {
        $query    = Security::strip($_GET['q'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $articles = $query ? $this->articles->search($query, $page) : [];

        View::make('public.search', [
            'articles'   => $articles,
            'query'      => Security::clean($query),
            'categories' => $this->categories->getAll(),
            'pageTitle'  => 'Search' . ($query ? ': ' . $query : '') . SEO_SEPARATOR . APP_NAME,
            'metaDesc'   => 'Search results for: ' . $query,
        ])->layout('public')->render();
    }

    // ----------------------------------------------------------
    // XML Sitemap
    // ----------------------------------------------------------
    public function sitemap(): void
    {
        $articles   = $this->articles->getAllForSitemap();
        $categories = $this->categories->getAll();

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static pages
        foreach (['', '/about', '/contact'] as $path) {
            echo "<url><loc>" . APP_URL . $path . "</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
        }

        // Categories
        foreach ($categories as $cat) {
            echo "<url><loc>" . APP_URL . "/category/{$cat['slug']}</loc><changefreq>daily</changefreq><priority>0.7</priority></url>\n";
        }

        // Articles
        foreach ($articles as $art) {
            $date = date('Y-m-d', strtotime($art['updated_at']));
            echo "<url><loc>" . APP_URL . "/articles/{$art['slug']}</loc>"
               . "<lastmod>{$date}</lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>\n";
        }

        echo '</urlset>';
    }

    // ----------------------------------------------------------
    // Robots.txt
    // ----------------------------------------------------------
    public function robots(): void
    {
        header('Content-Type: text/plain');
        echo "User-agent: *\n";
        echo "Disallow: /" . ADMIN_PATH . "/\n";
        echo "Disallow: /search?\n";
        echo "Allow: /\n\n";
        echo "Sitemap: " . APP_URL . "/sitemap.xml\n";
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::make('public.404', [
            'categories' => $this->categories->getAll(),
            'pageTitle'  => '404 Not Found' . SEO_SEPARATOR . APP_NAME,
            'metaDesc'   => 'Page not found.',
        ])->layout('public')->render();
    }
}
