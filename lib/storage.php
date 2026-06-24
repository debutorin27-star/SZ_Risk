<?php

use Bitrix\Main\Application;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../runtime.php';

function prDb()
{
    return Application::getConnection();
}

function prSql($value): string
{
    return prDb()->getSqlHelper()->forSql((string)$value);
}

function prJsonEncode($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function prJsonDecode($value, $default = [])
{
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : $default;
}

function prNow(): string
{
    return date('Y-m-d H:i:s');
}

function prSqlValue($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . prSql((string)$value) . "'";
}

function prDbInsert(string $tableName, array $fields): int
{
    $columns = [];
    $values = [];
    foreach ($fields as $column => $value) {
        $columns[] = '`' . str_replace('`', '``', (string)$column) . '`';
        $values[] = prSqlValue($value);
    }

    prDb()->queryExecute(
        'INSERT INTO ' . $tableName . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')'
    );

    return (int)prDb()->getInsertedId();
}

function prDbUpdate(string $tableName, array $fields, string $whereSql): void
{
    $sets = [];
    foreach ($fields as $column => $value) {
        $sets[] = '`' . str_replace('`', '``', (string)$column) . '` = ' . prSqlValue($value);
    }

    prDb()->queryExecute('UPDATE ' . $tableName . ' SET ' . implode(', ', $sets) . ' WHERE ' . $whereSql);
}

function prEnsureTables(): void
{
    $db = prDb();

    if (!$db->isTableExists('b_pr_requests')) {
        $db->queryExecute("
            CREATE TABLE b_pr_requests (
                ID int NOT NULL AUTO_INCREMENT,
                CREATED_AT datetime NOT NULL,
                UPDATED_AT datetime NULL,
                STATUS varchar(32) NOT NULL DEFAULT 'DRAFT',
                CURRENT_VERSION int NOT NULL DEFAULT 0,
                INITIATOR_ID int NOT NULL,
                INITIATOR_NAME varchar(255) NOT NULL,
                COMPANY_KEY varchar(80) NOT NULL,
                COMPANY_NAME varchar(255) NOT NULL,
                SITE_KEY varchar(80) NOT NULL DEFAULT '',
                SITE_NAME varchar(255) NOT NULL DEFAULT '',
                DEPARTMENT_NAME varchar(255) NOT NULL DEFAULT '',
                PLACE_TEXT varchar(255) NOT NULL DEFAULT '',
                REQUEST_TYPE varchar(32) NOT NULL DEFAULT 'goods',
                TOTAL_AMOUNT decimal(18,2) NOT NULL DEFAULT 0,
                CURRENCY varchar(8) NOT NULL DEFAULT 'RUB',
                VAT_MODE varchar(32) NOT NULL DEFAULT 'unknown',
                REQUIRED_DATE date NULL,
                JUSTIFICATION text NULL,
                COMMENT_TEXT text NULL,
                REG_NUMBER varchar(128) NULL,
                REG_DATE date NULL,
                ROUTE_SNAPSHOT mediumtext NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_REQ_INITIATOR (INITIATOR_ID),
                KEY IX_PR_REQ_STATUS (STATUS),
                KEY IX_PR_REQ_SITE (COMPANY_KEY, SITE_KEY)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_request_items')) {
        $db->queryExecute("
            CREATE TABLE b_pr_request_items (
                ID int NOT NULL AUTO_INCREMENT,
                REQUEST_ID int NOT NULL,
                VERSION int NOT NULL DEFAULT 0,
                SORT int NOT NULL DEFAULT 100,
                CATEGORY varchar(32) NOT NULL DEFAULT 'goods',
                NAME varchar(500) NOT NULL,
                QUANTITY decimal(18,4) NOT NULL DEFAULT 0,
                UNIT varchar(64) NOT NULL DEFAULT '',
                ESTIMATED_PRICE decimal(18,2) NOT NULL DEFAULT 0,
                EQUIPMENT_TEXT varchar(500) NOT NULL DEFAULT '',
                JUSTIFICATION text NULL,
                LINK_TEXT text NULL,
                COMMENT_TEXT text NULL,
                WAREHOUSE_STATUS varchar(32) NOT NULL DEFAULT '',
                WAREHOUSE_QTY decimal(18,4) NULL,
                WAREHOUSE_COMMENT text NULL,
                FINAL_STATUS varchar(32) NOT NULL DEFAULT 'ACTIVE',
                PRIMARY KEY (ID),
                KEY IX_PR_ITEM_REQUEST (REQUEST_ID, VERSION),
                KEY IX_PR_ITEM_STATUS (FINAL_STATUS)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_attachments')) {
        $db->queryExecute("
            CREATE TABLE b_pr_attachments (
                ID int NOT NULL AUTO_INCREMENT,
                REQUEST_ID int NOT NULL,
                ITEM_ID int NULL,
                VERSION int NOT NULL DEFAULT 0,
                FILE_ID int NULL,
                ORIGINAL_NAME varchar(500) NOT NULL DEFAULT '',
                FILE_SIZE int NOT NULL DEFAULT 0,
                MIME_TYPE varchar(255) NOT NULL DEFAULT '',
                AUTHOR_ID int NOT NULL DEFAULT 0,
                CREATED_AT datetime NOT NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_ATT_REQUEST (REQUEST_ID, VERSION),
                KEY IX_PR_ATT_ITEM (ITEM_ID)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_tasks')) {
        $db->queryExecute("
            CREATE TABLE b_pr_tasks (
                ID int NOT NULL AUTO_INCREMENT,
                REQUEST_ID int NOT NULL,
                VERSION int NOT NULL,
                STEP_INDEX int NOT NULL DEFAULT 0,
                STEP_CODE varchar(80) NOT NULL,
                STEP_TITLE varchar(255) NOT NULL,
                ROLE_CODE varchar(80) NOT NULL,
                ASSIGNED_USER_ID int NOT NULL,
                STATUS varchar(32) NOT NULL DEFAULT 'OPEN',
                AVAILABLE_ITEM_IDS text NULL,
                DUE_AT datetime NULL,
                CREATED_AT datetime NOT NULL,
                COMPLETED_AT datetime NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_TASK_USER (ASSIGNED_USER_ID, STATUS),
                KEY IX_PR_TASK_REQUEST (REQUEST_ID, VERSION, STEP_CODE)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_decisions')) {
        $db->queryExecute("
            CREATE TABLE b_pr_decisions (
                ID int NOT NULL AUTO_INCREMENT,
                REQUEST_ID int NOT NULL,
                TASK_ID int NOT NULL,
                VERSION int NOT NULL,
                USER_ID int NOT NULL,
                ROLE_CODE varchar(80) NOT NULL,
                DECISION varchar(32) NOT NULL,
                ITEM_IDS text NULL,
                COMMENT_TEXT text NULL,
                CREATED_AT datetime NOT NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_DEC_REQUEST (REQUEST_ID, VERSION),
                KEY IX_PR_DEC_TASK (TASK_ID)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_role_assignments')) {
        $db->queryExecute("
            CREATE TABLE b_pr_role_assignments (
                ID int NOT NULL AUTO_INCREMENT,
                ROLE_CODE varchar(80) NOT NULL,
                USER_ID int NOT NULL,
                USER_NAME varchar(255) NOT NULL DEFAULT '',
                COMPANY_KEY varchar(80) NOT NULL DEFAULT '',
                SITE_KEY varchar(80) NOT NULL DEFAULT '',
                DEPARTMENT_NAME varchar(255) NOT NULL DEFAULT '',
                POSITION_NAME varchar(255) NOT NULL DEFAULT '',
                ACTIVE_FROM date NULL,
                ACTIVE_TO date NULL,
                IS_ACTIVE char(1) NOT NULL DEFAULT 'Y',
                CREATED_AT datetime NOT NULL,
                UPDATED_AT datetime NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_ROLE_LOOKUP (ROLE_CODE, COMPANY_KEY, SITE_KEY, IS_ACTIVE),
                KEY IX_PR_ROLE_USER (USER_ID)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_route_rules')) {
        $db->queryExecute("
            CREATE TABLE b_pr_route_rules (
                ID int NOT NULL AUTO_INCREMENT,
                SORT int NOT NULL DEFAULT 100,
                TITLE varchar(255) NOT NULL,
                COMPANY_KEY varchar(80) NOT NULL DEFAULT '',
                SITE_KEY varchar(80) NOT NULL DEFAULT '',
                REQUEST_TYPE varchar(32) NOT NULL DEFAULT '',
                MIN_AMOUNT decimal(18,2) NULL,
                MAX_AMOUNT decimal(18,2) NULL,
                INITIATOR_POSITION varchar(255) NOT NULL DEFAULT '',
                ITEM_CATEGORY varchar(32) NOT NULL DEFAULT '',
                STEPS_JSON mediumtext NOT NULL,
                IS_ACTIVE char(1) NOT NULL DEFAULT 'Y',
                CREATED_AT datetime NOT NULL,
                UPDATED_AT datetime NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_ROUTE_LOOKUP (COMPANY_KEY, SITE_KEY, REQUEST_TYPE, IS_ACTIVE)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!$db->isTableExists('b_pr_audit_events')) {
        $db->queryExecute("
            CREATE TABLE b_pr_audit_events (
                ID int NOT NULL AUTO_INCREMENT,
                CREATED_AT datetime NOT NULL,
                USER_ID int NOT NULL DEFAULT 0,
                ACTION varchar(120) NOT NULL,
                ENTITY_TYPE varchar(80) NOT NULL,
                ENTITY_ID int NOT NULL DEFAULT 0,
                DETAILS mediumtext NULL,
                IP varchar(64) NOT NULL DEFAULT '',
                USER_AGENT varchar(500) NOT NULL DEFAULT '',
                PRIMARY KEY (ID),
                KEY IX_PR_AUDIT_ENTITY (ENTITY_TYPE, ENTITY_ID),
                KEY IX_PR_AUDIT_USER (USER_ID)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    prEnsureDefaultRouteRule();
}

function prEnsureDefaultRouteRule(): void
{
    $db = prDb();
    $row = $db->query("SELECT ID FROM b_pr_route_rules LIMIT 1")->fetch();
    if ($row) {
        return;
    }

    prDbInsert('b_pr_route_rules', [
        'SORT' => 100,
        'TITLE' => 'Базовый маршрут',
        'COMPANY_KEY' => '',
        'SITE_KEY' => '',
        'REQUEST_TYPE' => '',
        'MIN_AMOUNT' => null,
        'MAX_AMOUNT' => null,
        'INITIATOR_POSITION' => '',
        'ITEM_CATEGORY' => '',
        'STEPS_JSON' => prJsonEncode(prDefaultRouteSteps()),
        'IS_ACTIVE' => 'Y',
        'CREATED_AT' => prNow(),
    ]);
}

function prAudit(int $userId, string $action, string $entityType, int $entityId, array $details = []): void
{
    prEnsureTables();
    prDbInsert('b_pr_audit_events', [
        'CREATED_AT' => prNow(),
        'USER_ID' => $userId,
        'ACTION' => $action,
        'ENTITY_TYPE' => $entityType,
        'ENTITY_ID' => $entityId,
        'DETAILS' => prJsonEncode($details),
        'IP' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'USER_AGENT' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);
}

function prFetchRoleAssignments(array $filter = []): array
{
    prEnsureTables();
    $where = ['1=1'];
    if (!empty($filter['role_code'])) {
        $where[] = "ROLE_CODE = '" . prSql($filter['role_code']) . "'";
    }
    if (isset($filter['active'])) {
        $where[] = "IS_ACTIVE = '" . (!empty($filter['active']) ? 'Y' : 'N') . "'";
    }

    $rows = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_role_assignments
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ROLE_CODE, COMPANY_KEY, SITE_KEY, USER_NAME, ID
    ");
    while ($row = $rs->fetch()) {
        $rows[] = $row;
    }
    return $rows;
}

function prSaveRoleAssignment(array $data, int $adminUserId): int
{
    prEnsureTables();

    $id = (int)($data['id'] ?? 0);
    $fields = [
        'ROLE_CODE' => (string)($data['role_code'] ?? ''),
        'USER_ID' => (int)($data['user_id'] ?? 0),
        'USER_NAME' => (string)($data['user_name'] ?? ''),
        'COMPANY_KEY' => (string)($data['company_key'] ?? ''),
        'SITE_KEY' => (string)($data['site_key'] ?? ''),
        'DEPARTMENT_NAME' => (string)($data['department_name'] ?? ''),
        'POSITION_NAME' => (string)($data['position_name'] ?? ''),
        'ACTIVE_FROM' => !empty($data['active_from']) ? (string)$data['active_from'] : null,
        'ACTIVE_TO' => !empty($data['active_to']) ? (string)$data['active_to'] : null,
        'IS_ACTIVE' => !empty($data['is_active']) && $data['is_active'] === 'N' ? 'N' : 'Y',
        'UPDATED_AT' => prNow(),
    ];

    if ($fields['ROLE_CODE'] === '' || !isset(prRoleLabels()[$fields['ROLE_CODE']])) {
        throw new RuntimeException('Выберите корректную роль.');
    }
    if ($fields['USER_ID'] <= 0) {
        throw new RuntimeException('Укажите ID пользователя Bitrix.');
    }

    if ($fields['USER_NAME'] === '') {
        $fields['USER_NAME'] = 'Пользователь #' . $fields['USER_ID'];
    }

    if ($id > 0) {
        prDbUpdate('b_pr_role_assignments', $fields, 'ID = ' . $id);
        prAudit($adminUserId, 'role_assignment_update', 'role_assignment', $id, $fields);
        return $id;
    }

    $fields['CREATED_AT'] = prNow();
    $id = prDbInsert('b_pr_role_assignments', $fields);
    prAudit($adminUserId, 'role_assignment_create', 'role_assignment', $id, $fields);
    return $id;
}

function prDeleteRoleAssignment(int $id, int $adminUserId): void
{
    prEnsureTables();
    if ($id <= 0) {
        return;
    }
    prDb()->queryExecute("DELETE FROM b_pr_role_assignments WHERE ID = " . $id);
    prAudit($adminUserId, 'role_assignment_delete', 'role_assignment', $id);
}

function prFetchRouteRules(): array
{
    prEnsureTables();
    $rows = [];
    $rs = prDb()->query("SELECT * FROM b_pr_route_rules ORDER BY SORT, ID");
    while ($row = $rs->fetch()) {
        $row['STEPS'] = prJsonDecode($row['STEPS_JSON'] ?? '[]');
        $rows[] = $row;
    }
    return $rows;
}

function prSaveRouteRule(array $data, int $adminUserId): int
{
    prEnsureTables();
    $id = (int)($data['id'] ?? 0);
    $steps = $data['steps'] ?? prDefaultRouteSteps();
    if (is_string($steps)) {
        $decoded = json_decode($steps, true);
        $steps = is_array($decoded) ? $decoded : [];
    }
    if (!$steps) {
        throw new RuntimeException('Маршрут должен содержать минимум один этап.');
    }

    $fields = [
        'SORT' => (int)($data['sort'] ?? 100),
        'TITLE' => (string)($data['title'] ?? 'Маршрут'),
        'COMPANY_KEY' => (string)($data['company_key'] ?? ''),
        'SITE_KEY' => (string)($data['site_key'] ?? ''),
        'REQUEST_TYPE' => (string)($data['request_type'] ?? ''),
        'MIN_AMOUNT' => isset($data['min_amount']) && $data['min_amount'] !== '' ? (float)$data['min_amount'] : null,
        'MAX_AMOUNT' => isset($data['max_amount']) && $data['max_amount'] !== '' ? (float)$data['max_amount'] : null,
        'INITIATOR_POSITION' => (string)($data['initiator_position'] ?? ''),
        'ITEM_CATEGORY' => (string)($data['item_category'] ?? ''),
        'STEPS_JSON' => prJsonEncode(array_values($steps)),
        'IS_ACTIVE' => !empty($data['is_active']) && $data['is_active'] === 'N' ? 'N' : 'Y',
        'UPDATED_AT' => prNow(),
    ];

    if ($id > 0) {
        prDbUpdate('b_pr_route_rules', $fields, 'ID = ' . $id);
        prAudit($adminUserId, 'route_rule_update', 'route_rule', $id, $fields);
        return $id;
    }

    $fields['CREATED_AT'] = prNow();
    $id = prDbInsert('b_pr_route_rules', $fields);
    prAudit($adminUserId, 'route_rule_create', 'route_rule', $id, $fields);
    return $id;
}

function prIsProcessAdmin(int $userId): bool
{
    prEnsureTables();

    if ($userId <= 0) {
        return false;
    }
    if (in_array($userId, PR_ADMIN_USER_IDS, true)) {
        return true;
    }
    global $USER;
    if (is_object($USER) && method_exists($USER, 'IsAdmin') && $USER->IsAdmin()) {
        return true;
    }
    if (PR_ADMIN_GROUP_IDS && class_exists('CUser')) {
        $groups = CUser::GetUserGroup($userId);
        foreach (PR_ADMIN_GROUP_IDS as $groupId) {
            if (in_array((int)$groupId, array_map('intval', $groups), true)) {
                return true;
            }
        }
    }

    $row = prDb()->query("
        SELECT ID
        FROM b_pr_role_assignments
        WHERE USER_ID = " . $userId . "
          AND ROLE_CODE = 'process_admin'
          AND IS_ACTIVE = 'Y'
          AND (ACTIVE_FROM IS NULL OR ACTIVE_FROM <= CURDATE())
          AND (ACTIVE_TO IS NULL OR ACTIVE_TO >= CURDATE())
        LIMIT 1
    ")->fetch();

    return (bool)$row;
}

function prFindRoleUsers(string $roleCode, string $companyKey = '', string $siteKey = ''): array
{
    prEnsureTables();
    $where = [
        "ROLE_CODE = '" . prSql($roleCode) . "'",
        "IS_ACTIVE = 'Y'",
        "(ACTIVE_FROM IS NULL OR ACTIVE_FROM <= CURDATE())",
        "(ACTIVE_TO IS NULL OR ACTIVE_TO >= CURDATE())",
        "(COMPANY_KEY = '' OR COMPANY_KEY = '" . prSql($companyKey) . "')",
        "(SITE_KEY = '' OR SITE_KEY = '" . prSql($siteKey) . "')",
    ];

    $rows = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_role_assignments
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            CASE WHEN COMPANY_KEY = '" . prSql($companyKey) . "' THEN 0 ELSE 1 END,
            CASE WHEN SITE_KEY = '" . prSql($siteKey) . "' THEN 0 ELSE 1 END,
            ID
    ");
    while ($row = $rs->fetch()) {
        $rows[(int)$row['USER_ID']] = $row;
    }

    return array_values($rows);
}

function prGetRequest(int $requestId): ?array
{
    prEnsureTables();
    $request = prDb()->query("SELECT * FROM b_pr_requests WHERE ID = " . $requestId)->fetch();
    if (!$request) {
        return null;
    }
    $request['ITEMS'] = prFetchRequestItems($requestId, (int)$request['CURRENT_VERSION']);
    $request['ROUTE'] = prJsonDecode($request['ROUTE_SNAPSHOT'] ?? '[]');
    $request['ATTACHMENTS'] = prFetchAttachments($requestId);
    return $request;
}

function prFetchAttachments(int $requestId): array
{
    prEnsureTables();
    $rows = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_attachments
        WHERE REQUEST_ID = " . $requestId . "
        ORDER BY ID
    ");
    while ($row = $rs->fetch()) {
        $fileId = (int)($row['FILE_ID'] ?? 0);
        $row['URL'] = $fileId > 0 && class_exists('CFile') ? (string)CFile::GetPath($fileId) : '';
        $rows[] = $row;
    }
    return $rows;
}

function prFileExtension(string $fileName): string
{
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    return function_exists('mb_strtolower') ? mb_strtolower((string)$extension, 'UTF-8') : strtolower((string)$extension);
}

function prSaveUploadedAttachments(int $requestId, int $version, int $userId, string $inputName = 'attachments', array &$errors = []): array
{
    prEnsureTables();
    if ($requestId <= 0 || empty($_FILES[$inputName])) {
        return [];
    }

    if (!class_exists('CFile')) {
        $errors[] = 'Класс CFile недоступен для сохранения вложений.';
        return [];
    }

    $files = $_FILES[$inputName];
    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']],
        ];
    }

    $saved = [];
    $allowed = prAllowedFileExtensions();
    foreach ($files['name'] as $i => $name) {
        $name = (string)$name;
        $errorCode = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Не удалось загрузить файл «' . $name . '». Код: ' . $errorCode . '.';
            continue;
        }

        $size = (int)($files['size'][$i] ?? 0);
        if ($size > PR_MAX_UPLOAD_FILE_SIZE) {
            $errors[] = 'Файл «' . $name . '» больше допустимого размера.';
            continue;
        }

        $extension = prFileExtension($name);
        if ($extension === '' || !in_array($extension, $allowed, true)) {
            $errors[] = 'Файл «' . $name . '» имеет недопустимый формат.';
            continue;
        }

        $tmpName = (string)($files['tmp_name'][$i] ?? '');
        if ($tmpName === '' || !(is_uploaded_file($tmpName) || is_file($tmpName))) {
            $errors[] = 'Не удалось обработать файл «' . $name . '».';
            continue;
        }

        $fileId = CFile::SaveFile([
            'name' => $name,
            'type' => (string)($files['type'][$i] ?? ''),
            'tmp_name' => $tmpName,
            'error' => $errorCode,
            'size' => $size,
        ], 'purchase_requests');

        if (!$fileId) {
            $errors[] = 'Не удалось сохранить файл «' . $name . '».';
            continue;
        }

        prDbInsert('b_pr_attachments', [
            'REQUEST_ID' => $requestId,
            'ITEM_ID' => null,
            'VERSION' => $version,
            'FILE_ID' => (int)$fileId,
            'ORIGINAL_NAME' => $name,
            'FILE_SIZE' => $size,
            'MIME_TYPE' => (string)($files['type'][$i] ?? ''),
            'AUTHOR_ID' => $userId,
            'CREATED_AT' => prNow(),
        ]);
        $saved[] = (int)$fileId;
    }

    if ($saved) {
        prAudit($userId, 'attachments_upload', 'request', $requestId, ['file_ids' => $saved]);
    }

    return $saved;
}

function prFetchRequestItems(int $requestId, int $version): array
{
    $items = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_request_items
        WHERE REQUEST_ID = " . $requestId . " AND VERSION = " . $version . "
        ORDER BY SORT, ID
    ");
    while ($row = $rs->fetch()) {
        $items[] = $row;
    }
    return $items;
}

function prSaveDraft(array $data, int $userId, string $userName): int
{
    prEnsureTables();
    $companies = prCompanies();
    $companyKey = (string)($data['company_key'] ?? '');
    $siteKey = (string)($data['site_key'] ?? '');
    $company = $companies[$companyKey] ?? null;
    if (!$company) {
        throw new RuntimeException('Выберите компанию.');
    }
    $sites = $company['sites'] ?? [];
    $siteName = $sites[$siteKey] ?? '';
    if (count($sites) > 1 && $siteName === '') {
        throw new RuntimeException('Выберите площадку.');
    }

    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if (!$items) {
        throw new RuntimeException('Добавьте хотя бы одну строку заявки.');
    }

    $validItems = [];
    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $qty = (float)($item['quantity'] ?? 0);
        if ($name !== '' && $qty > 0) {
            $validItems[] = $item;
        }
    }
    if (!$validItems) {
        throw new RuntimeException('Заполните наименование и количество хотя бы в одной строке заявки.');
    }

    $requestId = (int)($data['id'] ?? 0);
    $version = 0;
    $existing = null;
    if ($requestId > 0) {
        $existing = prGetRequest($requestId);
        if (!$existing || (int)$existing['INITIATOR_ID'] !== $userId || !in_array((string)$existing['STATUS'], ['DRAFT', 'REVISION'], true)) {
            throw new RuntimeException('Черновик недоступен для изменения.');
        }
        $version = (int)$existing['CURRENT_VERSION'];
        if ((string)$existing['STATUS'] === 'REVISION') {
            $version++;
        }
    }

    $totalAmount = 0.0;
    foreach ($validItems as $item) {
        $qty = (float)($item['quantity'] ?? 0);
        $price = (float)($item['estimated_price'] ?? 0);
        $totalAmount += max(0, $qty) * max(0, $price);
    }

    $fields = [
        'UPDATED_AT' => prNow(),
        'STATUS' => 'DRAFT',
        'CURRENT_VERSION' => $version,
        'INITIATOR_ID' => $userId,
        'INITIATOR_NAME' => $userName,
        'COMPANY_KEY' => $companyKey,
        'COMPANY_NAME' => (string)$company['name'],
        'SITE_KEY' => $siteKey,
        'SITE_NAME' => $siteName,
        'DEPARTMENT_NAME' => (string)($data['department_name'] ?? ''),
        'PLACE_TEXT' => (string)($data['place_text'] ?? ''),
        'REQUEST_TYPE' => (string)($data['request_type'] ?? 'goods'),
        'TOTAL_AMOUNT' => $totalAmount,
        'CURRENCY' => (string)($data['currency'] ?? PR_DEFAULT_CURRENCY),
        'VAT_MODE' => (string)($data['vat_mode'] ?? 'unknown'),
        'REQUIRED_DATE' => !empty($data['required_date']) ? (string)$data['required_date'] : null,
        'JUSTIFICATION' => (string)($data['justification'] ?? ''),
        'COMMENT_TEXT' => (string)($data['comment_text'] ?? ''),
    ];

    if ($requestId > 0) {
        prDbUpdate('b_pr_requests', $fields, 'ID = ' . $requestId);
    } else {
        $fields['CREATED_AT'] = prNow();
        $requestId = prDbInsert('b_pr_requests', $fields);
    }

    prDb()->queryExecute("DELETE FROM b_pr_request_items WHERE REQUEST_ID = " . $requestId . " AND VERSION = " . $version);
    $sort = 100;
    foreach ($validItems as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $qty = (float)($item['quantity'] ?? 0);
        prDbInsert('b_pr_request_items', [
            'REQUEST_ID' => $requestId,
            'VERSION' => $version,
            'SORT' => $sort,
            'CATEGORY' => (string)($item['category'] ?? 'goods'),
            'NAME' => $name,
            'QUANTITY' => $qty,
            'UNIT' => (string)($item['unit'] ?? 'pcs'),
            'ESTIMATED_PRICE' => (float)($item['estimated_price'] ?? 0),
            'EQUIPMENT_TEXT' => (string)($item['equipment_text'] ?? ''),
            'JUSTIFICATION' => (string)($item['justification'] ?? ''),
            'LINK_TEXT' => (string)($item['link_text'] ?? ''),
            'COMMENT_TEXT' => (string)($item['comment_text'] ?? ''),
            'FINAL_STATUS' => 'ACTIVE',
        ]);
        $sort += 100;
    }

    prAudit($userId, $existing ? 'request_draft_update' : 'request_draft_create', 'request', $requestId);
    return $requestId;
}

function prListRequests(int $userId, bool $isAdmin = false): array
{
    prEnsureTables();
    $where = $isAdmin ? '1=1' : 'INITIATOR_ID = ' . $userId;
    $rows = [];
    $rs = prDb()->query("
        SELECT ID, CREATED_AT, UPDATED_AT, STATUS, CURRENT_VERSION, INITIATOR_ID, INITIATOR_NAME,
               COMPANY_NAME, SITE_NAME, REQUEST_TYPE, TOTAL_AMOUNT, CURRENCY, REG_NUMBER
        FROM b_pr_requests
        WHERE " . $where . "
        ORDER BY ID DESC
        LIMIT 300
    ");
    while ($row = $rs->fetch()) {
        $rows[] = $row;
    }
    return $rows;
}

function prUserCanViewRequest(int $requestId, int $userId, bool $isAdmin = false): bool
{
    if ($isAdmin) {
        return true;
    }
    $row = prDb()->query("SELECT INITIATOR_ID FROM b_pr_requests WHERE ID = " . $requestId)->fetch();
    if ($row && (int)$row['INITIATOR_ID'] === $userId) {
        return true;
    }
    $task = prDb()->query("
        SELECT ID FROM b_pr_tasks
        WHERE REQUEST_ID = " . $requestId . " AND ASSIGNED_USER_ID = " . $userId . "
        LIMIT 1
    ")->fetch();
    return (bool)$task;
}

function prFetchUserTasks(int $userId): array
{
    prEnsureTables();
    $rows = [];
    $rs = prDb()->query("
        SELECT t.*, r.INITIATOR_NAME, r.COMPANY_NAME, r.SITE_NAME, r.TOTAL_AMOUNT, r.CURRENCY, r.STATUS AS REQUEST_STATUS
        FROM b_pr_tasks t
        INNER JOIN b_pr_requests r ON r.ID = t.REQUEST_ID
        WHERE t.ASSIGNED_USER_ID = " . $userId . " AND t.STATUS = 'OPEN'
        ORDER BY t.CREATED_AT DESC, t.ID DESC
        LIMIT 200
    ");
    while ($row = $rs->fetch()) {
        $row['AVAILABLE_ITEM_IDS_ARRAY'] = prJsonDecode($row['AVAILABLE_ITEM_IDS'] ?? '[]');
        $rows[] = $row;
    }
    return $rows;
}

function prFetchTask(int $taskId): ?array
{
    prEnsureTables();
    $row = prDb()->query("SELECT * FROM b_pr_tasks WHERE ID = " . $taskId)->fetch();
    if (!$row) {
        return null;
    }
    $row['AVAILABLE_ITEM_IDS_ARRAY'] = prJsonDecode($row['AVAILABLE_ITEM_IDS'] ?? '[]');
    return $row;
}
