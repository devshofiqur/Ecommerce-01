<?php
// ============================================================
// app/Models/ArticleModel.php
// ============================================================

namespace Dunrovin\Models;

use PDO;

class ArticleModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ----------------------------------------------------------
    // Public: fetch published articles (paginated)
    // ----------------------------------------------------------
    public function getPublished(int $page = 1, int $perPage = ARTICLES_PER_PAGE): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.published_at, a.reading_time, a.view_count,
                    c.name AS category_name, c.slug AS category_slug,
                    ad.username AS author
             FROM   articles a
             LEFT JOIN categories c  ON c.id = a.category_id
             LEFT JOIN admins ad     ON ad.id = a.admin_id
             WHERE  a.status = 'published'
               AND  a.published_at <= NOW()
             ORDER BY a.published_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countPublished(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM articles WHERE status='published' AND published_at <= NOW()"
        )->fetchColumn();
    }

    // ----------------------------------------------------------
    // Public: single article by slug
    // ----------------------------------------------------------
    public function getBySlug(string $slug): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                    ad.username AS author
             FROM   articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN admins ad    ON ad.id = a.admin_id
             WHERE  a.slug = :slug
               AND  a.status = 'published'
               AND  a.published_at <= NOW()
             LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $article = $stmt->fetch();
        if ($article) {
            $article['tags'] = $this->getTagsForArticle((int)$article['id']);
            $this->incrementViews((int)$article['id']);
        }
        return $article;
    }

    public function getTagsForArticle(int $articleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.name, t.slug FROM tags t
             INNER JOIN article_tags at ON at.tag_id = t.id
             WHERE at.article_id = :id ORDER BY t.name"
        );
        $stmt->execute([':id' => $articleId]);
        return $stmt->fetchAll();
    }

    private function incrementViews(int $id): void
    {
        $this->db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = :id")
                 ->execute([':id' => $id]);
    }

    // ----------------------------------------------------------
    // Public: articles by category
    // ----------------------------------------------------------
    public function getByCategory(string $categorySlug, int $page = 1): array
    {
        $offset = ($page - 1) * ARTICLES_PER_PAGE;
        $stmt = $this->db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.published_at, a.reading_time,
                    c.name AS category_name, c.slug AS category_slug,
                    ad.username AS author
             FROM   articles a
             INNER JOIN categories c ON c.id = a.category_id AND c.slug = :slug
             LEFT JOIN admins ad     ON ad.id = a.admin_id
             WHERE  a.status = 'published' AND a.published_at <= NOW()
             ORDER BY a.published_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        $stmt->bindValue(':slug',   $categorySlug,       PDO::PARAM_STR);
        $stmt->bindValue(':limit',  ARTICLES_PER_PAGE,   PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,             PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------
    // Full-text search
    // ----------------------------------------------------------
    public function search(string $query, int $page = 1): array
    {
        $offset = ($page - 1) * ARTICLES_PER_PAGE;
        $stmt = $this->db->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.published_at, a.reading_time,
                    c.name AS category_name, c.slug AS category_slug,
                    MATCH(a.title, a.excerpt, a.body) AGAINST(:q IN NATURAL LANGUAGE MODE) AS relevance
             FROM   articles a
             LEFT JOIN categories c ON c.id = a.category_id
             WHERE  a.status = 'published'
               AND  a.published_at <= NOW()
               AND  MATCH(a.title, a.excerpt, a.body) AGAINST(:q2 IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC
             LIMIT  :limit OFFSET :offset"
        );
        $stmt->bindValue(':q',      $query,            PDO::PARAM_STR);
        $stmt->bindValue(':q2',     $query,            PDO::PARAM_STR);
        $stmt->bindValue(':limit',  ARTICLES_PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,           PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------
    // Admin: get all articles
    // ----------------------------------------------------------
    public function adminGetAll(int $page = 1, string $status = ''): array
    {
        $offset = ($page - 1) * ADMIN_PER_PAGE;
        $where  = $status ? "WHERE a.status = :status" : "WHERE 1=1";
        $stmt   = $this->db->prepare(
            "SELECT a.id, a.title, a.slug, a.status, a.published_at,
                    a.view_count, c.name AS category_name, ad.username AS author
             FROM   articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN admins ad    ON ad.id = a.admin_id
             $where
             ORDER BY a.updated_at DESC
             LIMIT :limit OFFSET :offset"
        );
        if ($status) $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':limit',  ADMIN_PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,        PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function adminGetById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, GROUP_CONCAT(t.id) AS tag_ids
             FROM   articles a
             LEFT JOIN article_tags at ON at.article_id = a.id
             LEFT JOIN tags t          ON t.id = at.tag_id
             WHERE  a.id = :id
             GROUP BY a.id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ----------------------------------------------------------
    // Admin: create / update
    // ----------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO articles
                (admin_id, category_id, title, slug, excerpt, body, featured_image,
                 status, published_at, scheduled_at, meta_title, meta_description, reading_time)
             VALUES
                (:admin_id, :category_id, :title, :slug, :excerpt, :body, :featured_image,
                 :status, :published_at, :scheduled_at, :meta_title, :meta_desc, :rt)"
        );
        $stmt->execute($this->bindArticleData($data));
        $id = (int)$this->db->lastInsertId();
        if (!empty($data['tags'])) $this->syncTags($id, $data['tags']);
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE articles SET
                category_id=:category_id, title=:title, slug=:slug, excerpt=:excerpt,
                body=:body, featured_image=:featured_image, status=:status,
                published_at=:published_at, scheduled_at=:scheduled_at,
                meta_title=:meta_title, meta_description=:meta_desc, reading_time=:rt
             WHERE id=:id"
        );
        $params = $this->bindArticleData($data);
        $params[':id'] = $id;
        unset($params[':admin_id']);
        $result = $stmt->execute($params);
        if (!empty($data['tags'])) $this->syncTags($id, $data['tags']);
        return $result;
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare("DELETE FROM articles WHERE id=:id")
                        ->execute([':id' => $id]);
    }

    // ----------------------------------------------------------
    // Sitemap
    // ----------------------------------------------------------
    public function getAllForSitemap(): array
    {
        return $this->db->query(
            "SELECT slug, updated_at FROM articles
             WHERE status='published' AND published_at <= NOW()
             ORDER BY published_at DESC"
        )->fetchAll();
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    private function bindArticleData(array $d): array
    {
        $body = $d['body'] ?? '';
        $wordCount = str_word_count(strip_tags($body));
        $readingTime = max(1, (int)ceil($wordCount / 238));

        return [
            ':admin_id'    => $d['admin_id'] ?? null,
            ':category_id' => $d['category_id'] ?: null,
            ':title'       => $d['title'],
            ':slug'        => $d['slug'],
            ':excerpt'     => $d['excerpt'] ?? null,
            ':body'        => $body,
            ':featured_image' => $d['featured_image'] ?? null,
            ':status'      => $d['status'] ?? 'draft',
            ':published_at' => $d['published_at'] ?? null,
            ':scheduled_at' => $d['scheduled_at'] ?? null,
            ':meta_title'  => $d['meta_title'] ?? null,
            ':meta_desc'   => $d['meta_description'] ?? null,
            ':rt'          => $readingTime,
        ];
    }

    private function syncTags(int $articleId, array $tagIds): void
    {
        $this->db->prepare("DELETE FROM article_tags WHERE article_id=:id")
                 ->execute([':id' => $articleId]);
        if (empty($tagIds)) return;
        $placeholders = implode(',', array_fill(0, count($tagIds), '(?,?)'));
        $values = [];
        foreach ($tagIds as $tid) { $values[] = $articleId; $values[] = (int)$tid; }
        $this->db->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES $placeholders")
                 ->execute($values);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM articles WHERE slug=:slug AND id!=:id"
        );
        $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        return (bool)$stmt->fetchColumn();
    }
}
