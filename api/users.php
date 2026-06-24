<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
prRequireAdmin($user);

function prApiUserFullName(array $row): string
{
    $name = trim(implode(' ', array_filter([
        (string)($row['LAST_NAME'] ?? ''),
        (string)($row['NAME'] ?? ''),
        (string)($row['SECOND_NAME'] ?? ''),
    ])));

    return $name !== '' ? $name : (string)($row['LOGIN'] ?? ('Пользователь #' . (int)($row['ID'] ?? 0)));
}

function prApiUserRow(array $row): array
{
    return [
        'id' => (int)($row['ID'] ?? 0),
        'name' => prApiUserFullName($row),
        'email' => (string)($row['EMAIL'] ?? ''),
        'login' => (string)($row['LOGIN'] ?? ''),
        'position' => (string)($row['WORK_POSITION'] ?? ''),
        'department' => prFormatDepartmentValue($row['UF_DEPARTMENT'] ?? ''),
    ];
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
        $id = (int)($row['ID'] ?? 0);
        if ($id <= 0 || isset($known[$id])) {
            continue;
        }
        $known[$id] = prApiUserRow($row);
    }
}

try {
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));
    $rows = [];

    if (class_exists('CUser')) {
        if ($query === '') {
            prApiUsersByFilter(['ACTIVE' => 'Y', 'ID' => (int)$user['id']], $limit, $rows);
        } else {
            if (preg_match('/^\d+$/', $query)) {
                prApiUsersByFilter(['ACTIVE' => 'Y', 'ID' => (int)$query], $limit, $rows);
            }
            foreach (['%LAST_NAME', '%NAME', '%SECOND_NAME', '%EMAIL', '%LOGIN', '%WORK_POSITION'] as $field) {
                if (count($rows) >= $limit) {
                    break;
                }
                prApiUsersByFilter(['ACTIVE' => 'Y', $field => $query], $limit, $rows);
            }
        }
    }

    prApiResponse(true, ['users' => array_values($rows)]);
} catch (Throwable $e) {
    prLog('users_api', ['event' => 'exception', 'message' => $e->getMessage()]);
    prApiResponse(false, ['errors' => [$e->getMessage()]], 400);
}
