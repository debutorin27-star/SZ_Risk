<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
prRequireAdmin($user);

function prApiUserFullName(array $row): string
{
    $name = trim(implode(' ', array_filter([
        (string)($row['LAST_NAME'] ?? $row['last_name'] ?? ''),
        (string)($row['NAME'] ?? $row['name'] ?? ''),
        (string)($row['SECOND_NAME'] ?? $row['second_name'] ?? ''),
    ])));

    return $name !== '' ? $name : (string)($row['LOGIN'] ?? $row['login'] ?? ('Пользователь #' . (int)($row['ID'] ?? $row['id'] ?? 0)));
}

function prApiUserRow(array $row): array
{
    return [
        'id' => (int)($row['ID'] ?? $row['id'] ?? 0),
        'name' => prApiUserFullName($row),
        'email' => (string)($row['EMAIL'] ?? $row['email'] ?? ''),
        'login' => (string)($row['LOGIN'] ?? $row['login'] ?? ''),
        'position' => (string)($row['WORK_POSITION'] ?? $row['UF_WORK_POSITION'] ?? $row['work_position'] ?? ''),
        'department' => prFormatDepartmentValue($row['UF_DEPARTMENT'] ?? $row['department'] ?? ''),
    ];
}

function prApiAddUserRow(array $row, int $limit, array &$known): void
{
    $user = prApiUserRow($row);
    $id = (int)($user['id'] ?? 0);
    if ($id <= 0 || isset($known[$id]) || count($known) >= $limit) {
        return;
    }
    $known[$id] = $user;
}

function prApiUsersByFilter(array $filter, int $limit, array &$known): void
{
    if (!class_exists('CUser')) {
        return;
    }

    $by = 'last_name';
    $order = 'asc';
    $params = [
        'FIELDS' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'WORK_POSITION'],
        'SELECT' => ['UF_DEPARTMENT'],
    ];
    $rsUsers = CUser::GetList($by, $order, $filter, $params);
    if (!is_object($rsUsers) || !method_exists($rsUsers, 'Fetch')) {
        return;
    }
    while (($row = $rsUsers->Fetch()) && count($known) < $limit) {
        prApiAddUserRow($row, $limit, $known);
    }
}

function prApiLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function prApiUserMatchesQuery(array $row, string $query): bool
{
    $query = trim($query);
    if ($query === '') {
        return true;
    }
    $terms = array_values(array_filter(preg_split('/\s+/u', prApiLower($query)) ?: []));
    if (!$terms) {
        return true;
    }
    $haystack = prApiLower(implode(' ', [
        (string)($row['ID'] ?? $row['id'] ?? ''),
        (string)($row['LOGIN'] ?? $row['login'] ?? ''),
        (string)($row['EMAIL'] ?? $row['email'] ?? ''),
        (string)($row['LAST_NAME'] ?? $row['last_name'] ?? ''),
        (string)($row['NAME'] ?? $row['name'] ?? ''),
        (string)($row['SECOND_NAME'] ?? $row['second_name'] ?? ''),
        (string)($row['WORK_POSITION'] ?? $row['UF_WORK_POSITION'] ?? $row['work_position'] ?? ''),
        prFormatDepartmentValue($row['UF_DEPARTMENT'] ?? $row['department'] ?? ''),
    ]));
    foreach ($terms as $term) {
        if ($term !== '' && strpos($haystack, $term) === false) {
            return false;
        }
    }
    return true;
}

function prApiUsersByLocalScan(string $query, int $limit, array &$known): void
{
    if (!class_exists('CUser') || count($known) >= $limit) {
        return;
    }

    $by = 'last_name';
    $order = 'asc';
    $params = [
        'FIELDS' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'WORK_POSITION'],
        'SELECT' => ['UF_DEPARTMENT'],
    ];
    $rsUsers = CUser::GetList($by, $order, ['ACTIVE' => 'Y'], $params);
    if (!is_object($rsUsers) || !method_exists($rsUsers, 'Fetch')) {
        return;
    }

    $checked = 0;
    while (($row = $rsUsers->Fetch()) && count($known) < $limit) {
        $checked++;
        if ($checked > 5000) {
            break;
        }
        if (!prApiUserMatchesQuery($row, $query)) {
            continue;
        }
        prApiAddUserRow($row, $limit, $known);
    }
}

function prApiUsersByRestSearch(string $query, int $limit, array &$known, array $authState): void
{
    $auth = is_array($authState['auth'] ?? null) ? $authState['auth'] : [];
    if (empty($auth['AUTH_ID']) || empty($auth['SERVER_ENDPOINT']) || count($known) >= $limit) {
        return;
    }

    $filter = ['ACTIVE' => true];
    if ($query !== '') {
        $filter['FIND'] = $query;
    }

    foreach (['user.search', 'user.get'] as $method) {
        $result = prCallRest($method, ['FILTER' => $filter], $auth);
        if (empty($result['ok']) || !is_array($result['result'])) {
            continue;
        }
        foreach ($result['result'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($query !== '' && $method === 'user.get' && !prApiUserMatchesQuery($row, $query)) {
                continue;
            }
            prApiAddUserRow($row, $limit, $known);
            if (count($known) >= $limit) {
                break 2;
            }
        }
    }
}

try {
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
    $rows = [];

    prApiUsersByRestSearch($query, $limit, $rows, $user['auth_state'] ?? []);

    if (class_exists('CUser')) {
        if ($query === '') {
            prApiUsersByLocalScan($query, $limit, $rows);
        } else {
            if (preg_match('/^\d+$/', $query)) {
                prApiUsersByFilter(['ACTIVE' => 'Y', 'ID' => (int)$query], $limit, $rows);
            }
            foreach (['LAST_NAME', 'NAME', 'SECOND_NAME', 'EMAIL', 'LOGIN', 'WORK_POSITION', '%LAST_NAME', '%NAME', '%SECOND_NAME', '%EMAIL', '%LOGIN', '%WORK_POSITION'] as $field) {
                if (count($rows) >= $limit) {
                    break;
                }
                prApiUsersByFilter(['ACTIVE' => 'Y', $field => $query], $limit, $rows);
            }
            prApiUsersByLocalScan($query, $limit, $rows);
        }
    }

    prApiResponse(true, ['users' => array_values($rows)]);
} catch (Throwable $e) {
    prLog('users_api', ['event' => 'exception', 'message' => $e->getMessage()]);
    prApiResponse(false, ['errors' => [$e->getMessage()]], 400);
}
