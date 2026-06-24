<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_AGENT_CHECK', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('DisableEventsCheck', true);
define('BX_SECURITY_SKIP_FRAMECHECK', true);

$__prApiBaseObLevel = ob_get_level();
ob_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include.php');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/storage.php';
require_once __DIR__ . '/../lib/workflow.php';

if (function_exists('header_remove')) {
    @header_remove('X-Frame-Options');
    @header_remove('Content-Security-Policy');
    @header_remove('Content-Security-Policy-Report-Only');
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    prLog('api_fatal', $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['ok' => false, 'errors' => ['PHP fatal error: ' . $error['message']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function prApiFlushCapturedOutput(): void
{
    global $__prApiBaseObLevel;
    $captured = '';
    while (ob_get_level() > $__prApiBaseObLevel) {
        $captured .= (string)ob_get_clean();
    }
    if ($captured !== '') {
        prLog('api_output', ['captured' => substr($captured, 0, 3000)]);
    }
}

function prApiResponse(bool $ok, array $payload = [], int $status = 200): void
{
    prApiFlushCapturedOutput();
    prJsonResponse($ok, $payload, $status);
}

function prRequireApiUser(): array
{
    global $USER;

    if (!is_object($USER) && class_exists('CUser')) {
        $USER = new CUser;
    }

    $authState = prEnsureAuthorizedFromRequest('api');
    if (empty($authState['ok'])) {
        prApiResponse(false, ['errors' => ['Не удалось авторизоваться.', (string)($authState['error'] ?? '')], 'debug' => $authState], 401);
    }

    $userId = 0;
    if (is_object($USER) && $USER->IsAuthorized()) {
        $userId = (int)$USER->GetID();
    }
    if ($userId <= 0 && !empty($authState['user_id'])) {
        $userId = (int)$authState['user_id'];
    }
    if ($userId <= 0) {
        prApiResponse(false, ['errors' => ['Не удалось определить пользователя.']], 401);
    }

    prEnsureTables();

    return [
        'id' => $userId,
        'name' => prCurrentUserName($userId, $authState),
        'auth_state' => $authState,
        'is_admin' => prIsProcessAdmin($userId),
    ];
}

function prRequireAdmin(array $user): void
{
    if (empty($user['is_admin'])) {
        prApiResponse(false, ['errors' => ['Недостаточно прав администратора процесса.']], 403);
    }
}
