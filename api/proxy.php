<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_AGENT_CHECK', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('DisableEventsCheck', true);
define('BX_SECURITY_SKIP_FRAMECHECK', true);

require_once __DIR__ . '/../runtime.php';

function prProxyResponse(bool $ok, array $payload = [], int $status = 200): void
{
    prJsonResponse($ok, $payload, $status);
}

function prProxyRandomHex(int $bytes = 16): string
{
    try {
        return bin2hex(random_bytes($bytes));
    } catch (Throwable $e) {
        return md5(uniqid('', true) . mt_rand());
    }
}

function prProxyWorkerUrl(string $jobId, string $secret): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/local/purchase_requests/api/proxy.php'))), '/');
    return $scheme . '://' . $host . $dir . '/worker.php?job_id=' . rawurlencode($jobId) . '&secret=' . rawurlencode($secret);
}

function prProxyCallWorker(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PurchaseRequests API proxy',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['raw' => (string)$raw, 'error' => $error, 'http' => $http];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 120,
            'header' => "Accept: application/json\r\nUser-Agent: PurchaseRequests API proxy\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return ['raw' => (string)$raw, 'error' => $raw === false ? 'file_get_contents failed' : '', 'http' => 0];
}

function prProxyRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            prProxyRemoveDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function prProxyStageUploadedFiles(array $files, string $jobDir): array
{
    $result = [];
    $filesDir = $jobDir . '/files';
    if (!is_dir($filesDir)) {
        @mkdir($filesDir, 0775, true);
    }

    foreach ($files as $fieldName => $fileData) {
        if (!is_array($fileData) || !isset($fileData['name'])) {
            continue;
        }

        if (is_array($fileData['name'])) {
            $result[$fieldName] = [
                'name' => [],
                'type' => [],
                'tmp_name' => [],
                'error' => [],
                'size' => [],
            ];

            foreach ($fileData['name'] as $i => $name) {
                $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename((string)$name));
                if ($safeBase === '' || $safeBase === '.' || $safeBase === '..') {
                    $safeBase = 'file_' . $i;
                }
                $target = $filesDir . '/' . $fieldName . '_' . $i . '_' . $safeBase;
                $tmpName = (string)($fileData['tmp_name'][$i] ?? '');
                $error = (int)($fileData['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_OK && $tmpName !== '' && is_uploaded_file($tmpName)) {
                    if (!@move_uploaded_file($tmpName, $target)) {
                        @copy($tmpName, $target);
                    }
                }

                $result[$fieldName]['name'][$i] = (string)$name;
                $result[$fieldName]['type'][$i] = (string)($fileData['type'][$i] ?? '');
                $result[$fieldName]['tmp_name'][$i] = is_file($target) ? $target : $tmpName;
                $result[$fieldName]['error'][$i] = $error;
                $result[$fieldName]['size'][$i] = (int)($fileData['size'][$i] ?? (is_file($target) ? filesize($target) : 0));
            }
        } else {
            $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename((string)($fileData['name'] ?? 'file')));
            if ($safeBase === '' || $safeBase === '.' || $safeBase === '..') {
                $safeBase = 'file';
            }
            $target = $filesDir . '/' . $fieldName . '_' . $safeBase;
            $tmpName = (string)($fileData['tmp_name'] ?? '');
            $error = (int)($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_OK && $tmpName !== '' && is_uploaded_file($tmpName)) {
                if (!@move_uploaded_file($tmpName, $target)) {
                    @copy($tmpName, $target);
                }
            }

            $result[$fieldName] = [
                'name' => (string)($fileData['name'] ?? ''),
                'type' => (string)($fileData['type'] ?? ''),
                'tmp_name' => is_file($target) ? $target : $tmpName,
                'error' => $error,
                'size' => (int)($fileData['size'] ?? (is_file($target) ? filesize($target) : 0)),
            ];
        }
    }

    return $result;
}

function prProxyNormalizeTarget(string $target): string
{
    $target = trim($target);
    if ($target === '') {
        return '';
    }

    $path = (string)(parse_url($target, PHP_URL_PATH) ?: $target);
    $path = str_replace('\\', '/', $path);
    return basename($path);
}

$rawTarget = (string)($_GET['target'] ?? $_POST['target'] ?? '');
$target = prProxyNormalizeTarget($rawTarget);
$allowed = ['requests.php', 'tasks.php', 'admin.php', 'users.php'];
if (!in_array($target, $allowed, true)) {
    prProxyResponse(false, ['errors' => ['Недопустимый API target.'], 'debug' => ['target' => $rawTarget]], 400);
}

$jobsDir = prApiJobsDir();
foreach (glob($jobsDir . '/*/job.json') ?: [] as $file) {
    if (@filemtime($file) && @filemtime($file) < time() - 3600) {
        $dir = dirname($file);
        prProxyRemoveDir($dir);
    }
}

$jobId = date('Ymd_His') . '_' . prProxyRandomHex(8);
$secret = prProxyRandomHex(24);
$jobDir = $jobsDir . '/' . $jobId;
if (!is_dir($jobDir) && !@mkdir($jobDir, 0775, true)) {
    prProxyResponse(false, [
        'errors' => ['Не удалось создать API job.'],
        'debug' => [
            'jobs_dir' => $jobsDir,
            'jobs_dir_exists' => is_dir($jobsDir) ? 'Y' : 'N',
            'jobs_dir_writable' => is_dir($jobsDir) && is_writable($jobsDir) ? 'Y' : 'N',
            'candidates' => function_exists('prRuntimeCandidates') ? prRuntimeCandidates('api_jobs') : [],
        ],
    ], 500);
}

$get = $_GET;
unset($get['target']);
$post = $_POST;
unset($post['target']);

$job = [
    'secret' => $secret,
    'target' => $target,
    'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    'get' => $get,
    'post' => $post,
    'files' => prProxyStageUploadedFiles($_FILES, $jobDir),
    'request' => array_merge($_REQUEST, $get, $post),
    'raw_body' => (string)file_get_contents('php://input'),
    'created_at' => time(),
];

@file_put_contents($jobDir . '/job.json', json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$workerResult = prProxyCallWorker(prProxyWorkerUrl($jobId, $secret));
if ($workerResult['error'] !== '' || $workerResult['raw'] === '') {
    prLog('api_proxy', ['event' => 'worker_failed', 'result' => $workerResult]);
    prProxyResponse(false, ['errors' => ['API worker недоступен.', $workerResult['error']]], 502);
}

if (!headers_sent()) {
    http_response_code($workerResult['http'] > 0 ? $workerResult['http'] : 200);
    header('Content-Type: application/json; charset=UTF-8');
    if (function_exists('header_remove')) {
        @header_remove('X-Frame-Options');
        @header_remove('Content-Security-Policy');
        @header_remove('Content-Security-Policy-Report-Only');
    }
}
echo $workerResult['raw'];
