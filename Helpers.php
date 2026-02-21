<?php
// ============================================================
// app/Helpers/Security.php
// ============================================================

namespace Dunrovin\Helpers;

class Security
{
    // ----------------------------------------------------------
    // CSRF
    // ----------------------------------------------------------
    public static function generateCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCsrf() . '">';
    }

    // ----------------------------------------------------------
    // XSS Sanitization
    // ----------------------------------------------------------
    public static function clean(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }
        return htmlspecialchars((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function strip(string $input): string
    {
        return strip_tags(trim($input));
    }

    // ----------------------------------------------------------
    // Password
    // ----------------------------------------------------------
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }

    // ----------------------------------------------------------
    // Rate limiting (session-based)
    // ----------------------------------------------------------
    public static function checkLoginAttempts(string $identifier): bool
    {
        $key = 'login_attempts_' . md5($identifier);
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        $data = &$_SESSION[$key];
        // Reset after lockout period
        if (time() - $data['time'] > LOCKOUT_MINUTES * 60) {
            $data = ['count' => 0, 'time' => time()];
        }
        return $data['count'] < MAX_LOGIN_ATTEMPTS;
    }

    public static function incrementLoginAttempts(string $identifier): void
    {
        $key = 'login_attempts_' . md5($identifier);
        $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
        $_SESSION[$key]['time'] = $_SESSION[$key]['time'] ?? time();
    }

    public static function clearLoginAttempts(string $identifier): void
    {
        unset($_SESSION['login_attempts_' . md5($identifier)]);
    }

    public static function getRemainingLockout(string $identifier): int
    {
        $key = 'login_attempts_' . md5($identifier);
        if (!isset($_SESSION[$key])) return 0;
        $elapsed = time() - ($_SESSION[$key]['time'] ?? time());
        return max(0, LOCKOUT_MINUTES * 60 - $elapsed);
    }
}


// ============================================================
// app/Helpers/Str.php — String utilities
// ============================================================

namespace Dunrovin\Helpers;

class Str
{
    public static function slug(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\w\s-]/u', '', $text);
        $text = preg_replace('/[\s_-]+/', '-', $text);
        return trim($text, '-');
    }

    public static function excerpt(string $html, int $length = 180): string
    {
        $text = strip_tags($html);
        if (mb_strlen($text) <= $length) return $text;
        return rtrim(mb_substr($text, 0, $length)) . '…';
    }

    public static function readableDate(string $datetime): string
    {
        return date('F j, Y', strtotime($datetime));
    }

    public static function timeAgo(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        return match(true) {
            $diff < 60      => 'Just now',
            $diff < 3600    => floor($diff/60)    . 'm ago',
            $diff < 86400   => floor($diff/3600)  . 'h ago',
            $diff < 604800  => floor($diff/86400) . 'd ago',
            default         => date('M j, Y', strtotime($datetime)),
        };
    }

    public static function sanitizeSlug(string $slug, string $table = 'articles'): string
    {
        return preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    }
}


// ============================================================
// app/Helpers/Session.php
// ============================================================

namespace Dunrovin\Helpers;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $secure   = APP_ENV === 'production';
        $lifetime = 7200; // 2 hours

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Regenerate ID periodically to prevent fixation
        if (!isset($_SESSION['_last_regenerated']) ||
            time() - $_SESSION['_last_regenerated'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}


// ============================================================
// app/Helpers/View.php — View renderer
// ============================================================

namespace Dunrovin\Helpers;

class View
{
    private array $data = [];
    private string $layout = '';

    public function __construct(private string $template) {}

    public function with(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function layout(string $name): static
    {
        $this->layout = $name;
        return $this;
    }

    public function render(): void
    {
        extract($this->data, EXTR_SKIP);
        $templatePath = VIEW_PATH . '/' . str_replace('.', '/', $this->template) . '.php';

        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: {$templatePath}");
        }

        if ($this->layout) {
            ob_start();
            require $templatePath;
            $content = ob_get_clean();
            $layoutPath = VIEW_PATH . '/layouts/' . $this->layout . '.php';
            require $layoutPath;
        } else {
            require $templatePath;
        }
    }

    public static function make(string $template, array $data = []): static
    {
        return (new static($template))->with($data);
    }
}


// ============================================================
// app/Helpers/Upload.php — Image upload handler
// ============================================================

namespace Dunrovin\Helpers;

class Upload
{
    public static function handleImage(array $file, string $subfolder = 'articles'): string|false
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return false;

        $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
        if ($file['size'] > $maxBytes) return false;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) return false;

        $ext  = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => false,
        };
        if (!$ext) return false;

        $dir = UPLOAD_PATH . '/' . $subfolder . '/' . date('Y/m');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

        // Resize if too large (requires GD)
        if (extension_loaded('gd')) {
            self::resizeImage($dest, $mime, IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT);
        }

        return '/uploads/' . $subfolder . '/' . date('Y/m') . '/' . $filename;
    }

    private static function resizeImage(string $path, string $mime, int $maxW, int $maxH): void
    {
        [$w, $h] = getimagesize($path);
        if ($w <= $maxW && $h <= $maxH) return;

        $ratio = min($maxW / $w, $maxH / $h);
        $nw = (int)($w * $ratio);
        $nh = (int)($h * $ratio);

        $src = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => null,
        };
        if (!$src) return;

        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        match($mime) {
            'image/jpeg' => imagejpeg($dst, $path, 85),
            'image/png'  => imagepng($dst, $path, 7),
            'image/webp' => imagewebp($dst, $path, 85),
            default      => null,
        };

        imagedestroy($src);
        imagedestroy($dst);
    }
}


// ============================================================
// app/Helpers/Paginator.php
// ============================================================

namespace Dunrovin\Helpers;

class Paginator
{
    public int $total;
    public int $perPage;
    public int $currentPage;
    public int $totalPages;

    public function __construct(int $total, int $perPage, int $currentPage)
    {
        $this->total       = $total;
        $this->perPage     = $perPage;
        $this->currentPage = max(1, $currentPage);
        $this->totalPages  = max(1, (int)ceil($total / $perPage));
    }

    public function hasPages(): bool
    {
        return $this->totalPages > 1;
    }

    public function links(string $baseUrl): string
    {
        if (!$this->hasPages()) return '';
        $html = '<nav class="pagination" aria-label="Pagination"><ul>';

        if ($this->currentPage > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($this->currentPage - 1) . '" rel="prev">&#8592; Previous</a></li>';
        }

        $range = range(max(1, $this->currentPage - 2), min($this->totalPages, $this->currentPage + 2));
        foreach ($range as $p) {
            $active = $p === $this->currentPage ? ' class="active" aria-current="page"' : '';
            $html .= "<li><a href=\"{$baseUrl}?page={$p}\"{$active}>{$p}</a></li>";
        }

        if ($this->currentPage < $this->totalPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($this->currentPage + 1) . '" rel="next">Next &#8594;</a></li>';
        }

        return $html . '</ul></nav>';
    }
}
