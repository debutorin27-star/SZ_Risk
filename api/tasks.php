<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = prReadJsonBody();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? $body['action'] ?? 'list');

try {
    if ($method === 'GET' && $action === 'list') {
        prSkipOpenExcludedWorkflowTasks((int)$user['id']);
        prApiResponse(true, ['tasks' => prFetchUserTasks((int)$user['id'])]);
    }

    if ($method === 'POST' && $action === 'decision') {
        prSkipOpenExcludedWorkflowTasks((int)$user['id']);
        prApplyTaskDecision(
            (int)($body['task_id'] ?? 0),
            (int)$user['id'],
            (string)($body['decision'] ?? 'approve'),
            (string)($body['comment'] ?? ''),
            is_array($body['item_ids'] ?? null) ? $body['item_ids'] : [],
            is_array($body['warehouse'] ?? null) ? $body['warehouse'] : [],
            is_array($body['supply'] ?? null) ? $body['supply'] : [],
            is_array($body['item_decisions'] ?? null) ? $body['item_decisions'] : []
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
