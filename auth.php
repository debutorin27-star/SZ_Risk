<?php

require_once __DIR__ . '/runtime.php';

function prReadAuthPayload(): array
{
    $contextPayload = prLoadAuthContextPayload();

    $jsonPayload = [];
    if (!empty($_REQUEST['app_auth_payload'])) {
        $decoded = json_decode((string)$_REQUEST['app_auth_payload'], true);
        if (is_array($decoded)) {
            $jsonPayload = $decoded;
        }
    }

    return array_merge($contextPayload, $jsonPayload, $_GET ?? [], $_POST ?? [], $_REQUEST ?? []);
}

function prNormalizeAuthPayload(array $payload): array
{
    $domain = trim((string)($payload['DOMAIN'] ?? $payload['domain'] ?? ''));
    $rawServerEndpoint = trim((string)(
        $payload['SERVER_ENDPOINT']
        ?? $payload['server_endpoint']
        ?? $payload['CLIENT_ENDPOINT']
        ?? $payload['client_endpoint']
        ?? ''
    ));

    $serverEndpoint = '';
    if ($domain !== '') {
        $domain = preg_replace('~^https?://~i', '', $domain);
        $domain = rtrim($domain, '/');
        $serverEndpoint = 'https://' . $domain . '/rest/';
    } elseif ($rawServerEndpoint !== '' && stripos($rawServerEndpoint, 'oauth.bitrix') === false) {
        $serverEndpoint = rtrim($rawServerEndpoint, '/') . '/';
    }

    return [
        'AUTH_ID' => (string)($payload['AUTH_ID'] ?? $payload['auth'] ?? $payload['access_token'] ?? $payload['ACCESS_TOKEN'] ?? ''),
        'REFRESH_ID' => (string)($payload['REFRESH_ID'] ?? $payload['refresh_token'] ?? ''),
        'DOMAIN' => $domain,
        'SERVER_ENDPOINT' => $serverEndpoint,
        'RAW_SERVER_ENDPOINT' => $rawServerEndpoint,
        'APP_SID' => (string)($payload['APP_SID'] ?? $payload['APPLICATION_TOKEN'] ?? $payload['application_token'] ?? ''),
        'APPLICATION_TOKEN' => (string)($payload['APPLICATION_TOKEN'] ?? ''),
        'PLACEMENT' => (string)($payload['PLACEMENT'] ?? ''),
        'member_id' => (string)($payload['member_id'] ?? ''),
    ];
}

function prCallRest(string $method, array $params, array $auth): array
{
    if ($auth['AUTH_ID'] === '' || $auth['SERVER_ENDPOINT'] === '') {
        return ['ok' => false, 'error' => 'empty_rest_auth'];
    }

    $url = rtrim($auth['SERVER_ENDPOINT'], '/') . '/' . $method . '.json';
    $params['auth'] = $auth['AUTH_ID'];

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl_missing'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'PurchaseRequests',
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    prLog('rest', ['method' => $method, 'http' => $http, 'error' => $error, 'raw' => substr((string)$raw, 0, 2000)]);

    if ($raw === false || $error !== '') {
        return ['ok' => false, 'error' => 'curl_error', 'details' => $error, 'http' => $http];
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_json', 'details' => substr((string)$raw, 0, 1000), 'http' => $http];
    }

    if (!empty($decoded['error'])) {
        return ['ok' => false, 'error' => (string)$decoded['error'], 'details' => (string)($decoded['error_description'] ?? ''), 'http' => $http, 'decoded' => $decoded];
    }

    return ['ok' => true, 'result' => $decoded['result'] ?? null, 'http' => $http];
}

function prEnsureAuthorizedFromRequest(string $scope = 'auth'): array
{
    global $USER;

    $payload = prReadAuthPayload();
    $auth = prNormalizeAuthPayload($payload);
    $sessionAuthorized = is_object($USER) && $USER->IsAuthorized();
    $sessionUserId = $sessionAuthorized ? (int)$USER->GetID() : 0;

    prLog($scope, [
        'event' => 'auth_start',
        'session_user_id' => $sessionUserId,
        'has_auth_id' => $auth['AUTH_ID'] !== '' ? 'Y' : 'N',
        'domain' => $auth['DOMAIN'],
        'endpoint' => $auth['SERVER_ENDPOINT'],
    ]);

    if ($auth['AUTH_ID'] !== '') {
        $restResult = prCallRest('user.current', [], $auth);
        if (!empty($restResult['ok']) && is_array($restResult['result'])) {
            $restUser = $restResult['result'];
            $userId = (int)($restUser['ID'] ?? 0);
            if ($userId <= 0) {
                return ['ok' => false, 'source' => 'rest', 'error' => 'empty_user_id'];
            }

            if (is_object($USER) && (!$USER->IsAuthorized() || (int)$USER->GetID() !== $userId)) {
                $USER->Authorize($userId, false, true);
            }

            return [
                'ok' => true,
                'source' => is_object($USER) && $USER->IsAuthorized() ? 'rest_authorize' : 'rest_only',
                'user_id' => $userId,
                'rest_user' => $restUser,
                'auth' => $auth,
            ];
        }

        if ($sessionAuthorized) {
            return ['ok' => true, 'source' => 'bitrix_session_after_rest_error', 'user_id' => $sessionUserId, 'auth' => $auth];
        }

        return ['ok' => false, 'source' => 'rest', 'error' => $restResult['error'] ?? 'rest_failed', 'details' => $restResult['details'] ?? ''];
    }

    if ($sessionAuthorized) {
        return ['ok' => true, 'source' => 'bitrix_session', 'user_id' => $sessionUserId, 'auth' => $auth];
    }

    return ['ok' => false, 'source' => 'none', 'error' => 'no_bitrix_session_and_no_auth_id'];
}

function prCurrentUserName(int $userId, array $authState): string
{
    if ($userId > 0 && class_exists('CUser')) {
        $rsUser = CUser::GetByID($userId);
        $user = $rsUser ? $rsUser->Fetch() : false;
        if ($user) {
            $fio = trim(implode(' ', array_filter([
                (string)($user['LAST_NAME'] ?? ''),
                (string)($user['NAME'] ?? ''),
                (string)($user['SECOND_NAME'] ?? ''),
            ])));
            return $fio !== '' ? $fio : (string)($user['LOGIN'] ?? ('Пользователь #' . $userId));
        }
    }

    $restUser = is_array($authState['rest_user'] ?? null) ? $authState['rest_user'] : [];
    $fio = trim(implode(' ', array_filter([
        (string)($restUser['LAST_NAME'] ?? ''),
        (string)($restUser['NAME'] ?? ''),
        (string)($restUser['SECOND_NAME'] ?? ''),
    ])));

    return $fio !== '' ? $fio : 'Пользователь #' . $userId;
}
