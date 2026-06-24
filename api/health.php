<?php

require_once __DIR__ . '/../runtime.php';

$checks = [];
foreach (['auth_context', 'api_jobs', 'logs'] as $name) {
    $dir = prRuntimeDir($name);
    $probe = $dir . '/.write_test_' . uniqid('', true);
    $writeOk = @file_put_contents($probe, 'ok') !== false;
    if ($writeOk) {
        @unlink($probe);
    }
    $checks[$name] = [
        'dir' => $dir,
        'exists' => is_dir($dir) ? 'Y' : 'N',
        'writable' => is_dir($dir) && is_writable($dir) ? 'Y' : 'N',
        'write_test' => $writeOk ? 'Y' : 'N',
        'candidates' => prRuntimeCandidates($name),
    ];
}

prJsonResponse(true, ['checks' => $checks]);
