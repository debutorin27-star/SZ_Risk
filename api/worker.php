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

$jobId = (string)($_GET['job_id'] ?? '');
$secret = (string)($_GET['secret'] ?? '');

if ($jobId === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $jobId) || $secret === '') {
    prJsonResponse(false, ['errors' => ['Некорректный API job.']], 400);
}

$jobFile = prApiJobsDir() . '/' . $jobId . '/job.json';
if (!is_file($jobFile)) {
    prJsonResponse(false, ['errors' => ['API job не найден.']], 404);
}

$job = json_decode((string)@file_get_contents($jobFile), true);
if (!is_array($job) || !hash_equals((string)($job['secret'] ?? ''), $secret)) {
    prJsonResponse(false, ['errors' => ['API job secret не совпадает.']], 403);
}

$target = (string)($job['target'] ?? '');
$allowed = ['requests.php', 'tasks.php', 'admin.php'];
if (!in_array($target, $allowed, true)) {
    prJsonResponse(false, ['errors' => ['Недопустимый API target.']], 400);
}

$_SERVER['REQUEST_METHOD'] = (string)($job['method'] ?? 'GET');
$_GET = is_array($job['get'] ?? null) ? $job['get'] : [];
$_POST = is_array($job['post'] ?? null) ? $job['post'] : [];
$_FILES = is_array($job['files'] ?? null) ? $job['files'] : [];
$_REQUEST = array_merge(is_array($job['request'] ?? null) ? $job['request'] : [], $_GET, $_POST);
$GLOBALS['PR_API_JOB_BODY'] = (string)($job['raw_body'] ?? '');

require __DIR__ . '/' . $target;
