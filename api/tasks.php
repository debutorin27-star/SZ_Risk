<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = prReadJsonBody();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? $body['action'] ?? 'list');

try {
    if ($method === 'GET' && $action === 'list') {
        prApiResponse(true, ['tasks' => prFetchUserTasks((int)$user['id'])]);
    }

    if ($method === 'POST' && $action === 'decision') {
        prApplyTaskDecision(
            (int)($body['task_id'] ?? 0),
            (int)$user['id'],
            (string)($body['decision'] ?? 'approve'),
            (string)($body['comment'] ?? ''),
            is_array($body['item_ids'] ?? null) ? $body['item_ids'] : [],
            is_array($body['warehouse'] ?? null) ? $body['warehouse'] : [],
            is_array($body['registration'] ?? null) ? $body['registration'] : []
        );
        prApiResponse(true, [
            'tasks' => prFetchUserTasks((int)$user['id']),
            'message' => 'Решение сохранено.',
        ]);
    }

    prApiResponse(false, ['errors' => ['Неизвестное действие заданий.']], 400);
} catch (Throwable $e) {
    prLog('tasks_api', ['event' => 'exception', 'action' => $action, 'message' => $e->getMessage()]);
    prApiResponse(false, ['errors' => [$e->getMessage()]], 400);
}
