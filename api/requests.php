<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = prReadJsonBody();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? $body['action'] ?? 'list');

function prRequestForApi(array $request): array
{
    $request['ITEMS'] = array_values($request['ITEMS'] ?? []);
    $request['ROUTE'] = array_values($request['ROUTE'] ?? []);
    return $request;
}

function prFetchRequestDecisions(int $requestId): array
{
    $rows = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_decisions
        WHERE REQUEST_ID = " . $requestId . "
        ORDER BY CREATED_AT, ID
    ");
    while ($row = $rs->fetch()) {
        $row['ITEM_IDS_ARRAY'] = prJsonDecode($row['ITEM_IDS'] ?? '[]');
        $rows[] = $row;
    }
    return $rows;
}

try {
    if ($method === 'GET' && $action === 'list') {
        prApiResponse(true, [
            'rows' => prListRequests((int)$user['id']),
            'is_admin' => !empty($user['is_admin']),
            'is_observer' => !empty($user['is_observer']),
            'can_view_all' => !empty($user['can_view_all']),
        ]);
    }

    if ($method === 'GET' && $action === 'all_list') {
        if (empty($user['can_view_all'])) {
            prApiResponse(false, ['errors' => ['Недостаточно прав для общего реестра заявок.']], 403);
        }
        prApiResponse(true, [
            'rows' => prListAllRequests((int)$user['id'], $_GET),
            'can_view_all' => true,
        ]);
    }

    if ($method === 'GET' && $action === 'view') {
        $requestId = (int)($_GET['id'] ?? 0);
        if ($requestId <= 0 || !prUserCanViewRequest($requestId, (int)$user['id'], !empty($user['can_view_all']))) {
            prApiResponse(false, ['errors' => ['Заявка недоступна.']], 403);
        }
        $request = prGetRequest($requestId);
        if (!$request) {
            prApiResponse(false, ['errors' => ['Заявка не найдена.']], 404);
        }
        prApiResponse(true, [
            'request' => prRequestForApi($request),
            'decisions' => prFetchRequestDecisions($requestId),
        ]);
    }

    if ($method === 'POST' && $action === 'route_preview') {
        $draftId = prSaveDraft($body['request'] ?? $body, (int)$user['id'], (string)$user['name']);
        $request = prGetRequest($draftId);
        prApiResponse(true, [
            'id' => $draftId,
            'route' => prResolveRoute($request),
        ]);
    }

    if ($method === 'POST' && $action === 'save_draft') {
        $id = prSaveDraft($body['request'] ?? $body, (int)$user['id'], (string)$user['name']);
        prApiResponse(true, ['id' => $id, 'request' => prRequestForApi(prGetRequest($id))]);
    }

    if ($method === 'POST' && $action === 'upload_attachments') {
        $requestId = (int)($_POST['request_id'] ?? $body['request_id'] ?? 0);
        $request = $requestId > 0 ? prGetRequest($requestId) : null;
        if (!$request || (int)$request['INITIATOR_ID'] !== (int)$user['id']) {
            prApiResponse(false, ['errors' => ['Заявка недоступна для загрузки вложений.']], 403);
        }
        if (!in_array((string)$request['STATUS'], ['DRAFT', 'REVISION'], true)) {
            prApiResponse(false, ['errors' => ['После отправки добавление вложений пока недоступно.']], 400);
        }
        $errors = [];
        $fileIds = prSaveUploadedAttachments($requestId, (int)$request['CURRENT_VERSION'], (int)$user['id'], 'attachments', $errors);
        if ($errors) {
            prApiResponse(false, ['errors' => $errors], 400);
        }
        prApiResponse(true, ['file_ids' => $fileIds, 'request' => prRequestForApi(prGetRequest($requestId))]);
    }

    if ($method === 'POST' && $action === 'submit') {
        $payload = $body['request'] ?? $body;
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !empty($payload['items'])) {
            $id = prSaveDraft($payload, (int)$user['id'], (string)$user['name']);
        }
        prSubmitRequest($id, (int)$user['id']);
        prApiResponse(true, ['id' => $id, 'request' => prRequestForApi(prGetRequest($id))]);
    }

    prApiResponse(false, ['errors' => ['Неизвестное действие заявок.']], 400);
} catch (Throwable $e) {
    prLog('requests_api', ['event' => 'exception', 'action' => $action, 'message' => $e->getMessage()]);
    prApiResponse(false, ['errors' => [$e->getMessage()]], 400);
}
