<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    return \Core\Config::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}

function url(string $path = '', array $query = []): string
{
    $url = base_path() . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function asset(string $path): string
{
    $file = __DIR__ . '/../public/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    \Core\View::render('errors/error', ['title' => 'Erreur ' . $code, 'code' => $code, 'message' => $message]);
    exit;
}

function partial(string $name, array $data = []): void
{
    \Core\View::partial($name, $data);
}

// --- Flash & anciennes valeurs de formulaire -------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function with_old(array $input): void
{
    $_SESSION['_old'] = $input;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

// --- CSRF -------------------------------------------------------------------
function csrf_token(): string
{
    return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

// --- Cache de session court (évite de re-taper Jira à chaque F5) ----------
function remember(string $key, int $ttl, callable $producer): mixed
{
    $hit = $_SESSION['_cache'][$key] ?? null;
    if (is_array($hit) && $hit['exp'] > time()) {
        return $hit['val'];
    }
    $value = $producer();
    $_SESSION['_cache'][$key] = ['exp' => time() + $ttl, 'val' => $value];
    return $value;
}

// --- Formatage / Jira -------------------------------------------------------
function fmt_date(?string $date): string
{
    if (!$date) {
        return '–';
    }
    try {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    } catch (\Throwable) {
        return $date;
    }
}

function jira_url(string $key): string
{
    return rtrim((string) config('jira.base_url', ''), '/') . '/browse/' . rawurlencode($key);
}

/** Classe neutre vis-à-vis de la langue : done | test | progress | todo */
function status_class(array $status): string
{
    $category = strtolower((string) ($status['statusCategory']['key'] ?? ''));
    $name     = mb_strtolower((string) ($status['name'] ?? ''));

    if (str_contains($name, 'tester') || str_contains($name, 'recette')) {
        return 'test';
    }
    if ($category === 'done' || preg_match('/termin|fait|done|clos|ferm/u', $name)) {
        return 'done';
    }
    if ($category === 'indeterminate' || preg_match('/cours|progress|r[ée]alisation/u', $name)) {
        return 'progress';
    }
    return 'todo';
}

function sprint_options(array $sprints, int $selectedId = 0, ?string $fallbackName = null, string $placeholder = '— Aucun —'): string
{
    $html  = '<option value="">' . e($placeholder) . '</option>';
    $found = false;

    foreach ($sprints as $sprint) {
        $id    = (int) $sprint['id'];
        $isSel = $id === $selectedId;
        $found = $found || $isSel;
        $html .= sprintf(
            '<option value="%s"%s>%s (%s)</option>',
            e($id . '|' . $sprint['name']),
            $isSel ? ' selected' : '',
            e($sprint['name']),
            e($sprint['state'] ?? '')
        );
    }

    // Sprint enregistré mais absent de la liste (ex. clôturé depuis)
    if ($selectedId > 0 && !$found) {
        $label = $fallbackName ?: 'Sprint #' . $selectedId;
        $html .= sprintf('<option value="%s" selected>%s (hors liste)</option>', e($selectedId . '|' . $label), e($label));
    }

    return $html;
}