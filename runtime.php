<?php

require_once __DIR__ . '/config.php';

function prRuntimeCandidates(string $name): array
{
    $name = trim($name, '/');
    $candidates = [__DIR__ . '/' . $name];

    $documentRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($documentRoot !== '') {
        $candidates[] = rtrim($documentRoot, '/') . '/upload/purchase_requests_runtime/' . $name;
        $candidates[] = rtrim($documentRoot, '/') . '/bitrix/tmp/purchase_requests_runtime/' . $name;
    }

    $tmp = sys_get_temp_dir();
    if ($tmp !== '') {
        $candidates[] = rtrim($tmp, '/') . '/purchase_requests_runtime/' . $name;
    }

    return array_values(array_unique($candidates));
}

function prRuntimeDir(string $name): string
{
    $diagnostics = [];
    foreach (prRuntimeCandidates($name) as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $diagnostics[] = [
            'dir' => $dir,
            'exists' => is_dir($dir) ? 'Y' : 'N',
            'writable' => is_dir($dir) && is_writable($dir) ? 'Y' : 'N',
        ];

        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }

        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        return $dir;
    }

    $fallback = __DIR__ . '/' . trim($name, '/');
    error_log('purchase_requests runtime dir is not writable: ' . json_encode($diagnostics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $fallback;
}

function prApiJobsDir(): string
{
    return prRuntimeDir('api_jobs');
}

function prLogFile(): string
{
    return prRuntimeDir('logs') . '/purchase_requests.log';
}

function prLog(string $scope, array $data): void
{
    $file = prLogFile();
    $written = @file_put_contents(
        $file,
        date('Y-m-d H:i:s') . ' | ' . $scope . ' | ' .
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
        PHP_EOL,
        FILE_APPEND
    );
    if ($written === false) {
        error_log('purchase_requests log write failed: ' . $file . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function prAuthContextDir(): string
{
    return prRuntimeDir('auth_context');
}

function prCreateAuthContext(array $payload): string
{
    $dir = prAuthContextDir();
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        if (@filemtime($file) && @filemtime($file) < time() - 6 * 3600) {
            @unlink($file);
        }
    }

    try {
        $context = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $context = md5(uniqid('', true) . microtime(true));
    }

    $payload['_created_at'] = time();
    $payload['_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $payload['_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    @file_put_contents($dir . '/' . $context . '.json', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $context;
}

function prLoadAuthContextPayload(): array
{
    $context = strtolower((string)($_REQUEST['auth_context'] ?? $_GET['auth_context'] ?? $_POST['auth_context'] ?? ''));
    if ($context === '' || !preg_match('/^[a-f0-9]{32}$/', $context)) {
        return [];
    }

    $file = prAuthContextDir() . '/' . $context . '.json';
    if (!is_file($file)) {
        return [];
    }

    $payload = json_decode((string)@file_get_contents($file), true);
    return is_array($payload) ? $payload : [];
}

function prJsonResponse(bool $ok, array $payload = [], int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        if (function_exists('header_remove')) {
            @header_remove('X-Frame-Options');
            @header_remove('Content-Security-Policy');
            @header_remove('Content-Security-Policy-Report-Only');
        }
    }

    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function prReadJsonBody(): array
{
    if (isset($GLOBALS['PR_API_JOB_BODY'])) {
        $raw = (string)$GLOBALS['PR_API_JOB_BODY'];
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function prH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prAppDir(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/local/purchase_requests/index.php')), '/');
    return $dir === '' ? '/local/purchase_requests' : preg_replace('~/api$~', '', $dir);
}

function prAbsoluteUrl(string $path, array $params = []): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $base = rtrim(prAppDir(), '/');
    $query = $params ? '?' . http_build_query($params) : '';
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/') . $query;
}
