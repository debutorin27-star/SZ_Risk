<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_AGENT_CHECK', true);
define('BX_SECURITY_SHOW_MESSAGE', true);
define('DisableEventsCheck', true);
define('BX_SECURITY_SKIP_FRAMECHECK', true);
define('BX_SECURITY_SESSION_READONLY', true);

$__prBootstrapBaseObLevel = ob_get_level();
ob_start();

require_once __DIR__ . '/../auth.php';

function prBootstrapResponse(bool $ok, array $payload = [], int $status = 200): void
{
    global $__prBootstrapBaseObLevel;

    $captured = '';
    while (ob_get_level() > $__prBootstrapBaseObLevel) {
        $captured .= (string)ob_get_clean();
    }

    if ($captured !== '' && function_exists('prLog')) {
        prLog('bootstrap_output', [
            'captured' => substr($captured, 0, 4000),
        ]);
    }

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

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    global $__prBootstrapBaseObLevel;
    $captured = '';
    while (ob_get_level() > $__prBootstrapBaseObLevel) {
        $captured .= (string)ob_get_clean();
    }

    if (function_exists('prLog')) {
        prLog('bootstrap_fatal', [
            'error' => $error,
            'captured' => substr($captured, 0, 4000),
        ]);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode([
        'ok' => false,
        'errors' => [
            'PHP fatal error в bootstrap.php: ' . (string)$error['message'],
            'Файл: ' . (string)$error['file'],
            'Строка: ' . (string)$error['line'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

$authState = prEnsureAuthorizedFromRequest('bootstrap');
if (empty($authState['ok'])) {
    $canTrySession = (string)($authState['source'] ?? '') === 'none'
        && (string)($authState['error'] ?? '') === 'no_bitrix_session_and_no_auth_id';

    if ($canTrySession) {
        $includePath = (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/bitrix/modules/main/include.php';
        if (is_file($includePath)) {
            require_once($includePath);
            if (function_exists('header_remove')) {
                @header_remove('X-Frame-Options');
                @header_remove('Content-Security-Policy');
                @header_remove('Content-Security-Policy-Report-Only');
            }

            global $USER;
            if (!is_object($USER) && class_exists('CUser')) {
                $USER = new CUser;
            }

            if (is_object($USER) && $USER->IsAuthorized()) {
                $userId = (int)$USER->GetID();
                $profile = prCurrentUserProfile($userId, ['source' => 'bitrix_session_fallback']);
                prBootstrapResponse(true, [
                    'api_mode' => 'direct',
                    'user' => [
                        'id' => $userId,
                        'name' => prCurrentUserName($userId, ['source' => 'bitrix_session_fallback']),
                        'source' => 'bitrix_session_fallback',
                        'is_config_admin' => in_array($userId, PR_ADMIN_USER_IDS, true),
                        'position' => $profile['position'],
                        'department' => $profile['department'],
                        'email' => $profile['email'],
                    ],
                    'dictionaries' => [
                        'companies' => prCompanies(),
                        'sites' => prSites(),
                        'sites_by_company' => prSitesByCompany(),
                        'initiator_profiles' => prInitiatorProfiles(),
                        'initiator_departments' => prInitiatorDepartments(),
                        'request_types' => prRequestTypes(),
                        'item_categories' => prItemCategories(),
                        'units' => prUnits(),
                        'roles' => prRoleLabels(),
                        'statuses' => prStatusLabels(),
                        'supply_checklist' => prSupplyChecklistLabels(),
                        'allowed_file_extensions' => prAllowedFileExtensions(),
                    ],
                ]);
            }
        }
    }

    prBootstrapResponse(false, [
        'errors' => [
            'Не удалось авторизоваться в Bitrix24.',
            (string)($authState['error'] ?? ''),
        ],
        'debug' => $authState,
    ], 401);
}

$userId = (int)($authState['user_id'] ?? 0);
$profile = prCurrentUserProfile($userId, $authState);
prBootstrapResponse(true, [
    'api_mode' => 'proxy',
    'user' => [
        'id' => $userId,
        'name' => prCurrentUserName($userId, $authState),
        'source' => (string)($authState['source'] ?? ''),
        'is_config_admin' => in_array($userId, PR_ADMIN_USER_IDS, true),
        'position' => $profile['position'],
        'department' => $profile['department'],
        'email' => $profile['email'],
    ],
    'dictionaries' => [
        'companies' => prCompanies(),
        'sites' => prSites(),
        'sites_by_company' => prSitesByCompany(),
        'initiator_profiles' => prInitiatorProfiles(),
        'initiator_departments' => prInitiatorDepartments(),
        'request_types' => prRequestTypes(),
        'item_categories' => prItemCategories(),
        'units' => prUnits(),
        'roles' => prRoleLabels(),
        'statuses' => prStatusLabels(),
        'supply_checklist' => prSupplyChecklistLabels(),
        'allowed_file_extensions' => prAllowedFileExtensions(),
    ],
]);
