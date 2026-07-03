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
                INITIATOR_POSITION varchar(255) NOT NULL DEFAULT '',
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
                GENERATED_DOCUMENT_FILE_ID int NULL,
                ROUTE_SNAPSHOT mediumtext NULL,
                PRIMARY KEY (ID),
                KEY IX_PR_REQ_INITIATOR (INITIATOR_ID),
                KEY IX_PR_REQ_STATUS (STATUS),
                KEY IX_PR_REQ_SITE (COMPANY_KEY, SITE_KEY)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    prEnsureColumn('b_pr_requests', 'INITIATOR_POSITION', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_requests', 'GENERATED_DOCUMENT_FILE_ID', 'int NULL');

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
    prEnsureColumn('b_pr_tasks', 'ASSIGNED_USER_NAME', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_tasks', 'SUBSTITUTE_USER_ID', 'int NOT NULL DEFAULT 0');
    prEnsureColumn('b_pr_tasks', 'SUBSTITUTE_USER_NAME', "varchar(255) NOT NULL DEFAULT ''");

    if (!$db->isTableExists('b_pr_decisions')) {
        $db->queryExecute("
            CREATE TABLE b_pr_decisions (
                ID int NOT NULL AUTO_INCREMENT,
                REQUEST_ID int NOT NULL,
                TASK_ID int NOT NULL,
                VERSION int NOT NULL,
                USER_ID int NOT NULL,
                USER_NAME varchar(255) NOT NULL DEFAULT '',
                USER_POSITION varchar(255) NOT NULL DEFAULT '',
                USER_DEPARTMENT varchar(255) NOT NULL DEFAULT '',
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

    prEnsureColumn('b_pr_decisions', 'USER_NAME', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_decisions', 'USER_POSITION', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_decisions', 'USER_DEPARTMENT', "varchar(255) NOT NULL DEFAULT ''");

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
    prEnsureColumn('b_pr_role_assignments', 'SUBSTITUTE_USER_ID', 'int NOT NULL DEFAULT 0');
    prEnsureColumn('b_pr_role_assignments', 'SUBSTITUTE_USER_NAME', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_role_assignments', 'SUBSTITUTE_POSITION_NAME', "varchar(255) NOT NULL DEFAULT ''");
    prEnsureColumn('b_pr_role_assignments', 'SUBSTITUTE_DEPARTMENT_NAME', "varchar(255) NOT NULL DEFAULT ''");

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

function prEnsureColumn(string $tableName, string $columnName, string $definition): void
{
    $row = prDb()->query("SHOW COLUMNS FROM " . $tableName . " LIKE '" . prSql($columnName) . "'")->fetch();
    if ($row) {
        return;
    }
    prDb()->queryExecute("ALTER TABLE " . $tableName . " ADD `" . str_replace('`', '``', $columnName) . "` " . $definition);
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
        'SUBSTITUTE_USER_ID' => (int)($data['substitute_user_id'] ?? 0),
        'SUBSTITUTE_USER_NAME' => (string)($data['substitute_user_name'] ?? ''),
        'SUBSTITUTE_POSITION_NAME' => (string)($data['substitute_position_name'] ?? ''),
        'SUBSTITUTE_DEPARTMENT_NAME' => (string)($data['substitute_department_name'] ?? ''),
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
    if ($fields['SUBSTITUTE_USER_ID'] <= 0) {
        $fields['SUBSTITUTE_USER_ID'] = 0;
        $fields['SUBSTITUTE_USER_NAME'] = '';
        $fields['SUBSTITUTE_POSITION_NAME'] = '';
        $fields['SUBSTITUTE_DEPARTMENT_NAME'] = '';
    } elseif ($fields['SUBSTITUTE_USER_NAME'] === '') {
        $substituteProfile = prUserProfileSummary((int)$fields['SUBSTITUTE_USER_ID']);
        $fields['SUBSTITUTE_USER_NAME'] = $substituteProfile['name'];
        if ($fields['SUBSTITUTE_POSITION_NAME'] === '') {
            $fields['SUBSTITUTE_POSITION_NAME'] = $substituteProfile['position'];
        }
        if ($fields['SUBSTITUTE_DEPARTMENT_NAME'] === '') {
            $fields['SUBSTITUTE_DEPARTMENT_NAME'] = $substituteProfile['department'];
        }
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

function prStorageFormatDepartmentValue($department): string
{
    if (function_exists('prFormatDepartmentValue')) {
        return prFormatDepartmentValue($department);
    }
    if (is_array($department)) {
        return implode(', ', array_filter(array_map('strval', $department)));
    }
    return (string)$department;
}

function prUserProfileSummary(int $userId, string $fallbackName = '', string $fallbackPosition = '', string $fallbackDepartment = ''): array
{
    $profile = [
        'id' => $userId,
        'name' => $fallbackName !== '' ? $fallbackName : ($userId > 0 ? 'Пользователь #' . $userId : ''),
        'position' => $fallbackPosition,
        'department' => $fallbackDepartment,
    ];

    if ($userId > 0 && class_exists('CUser')) {
        $rsUser = CUser::GetByID($userId);
        $user = $rsUser ? $rsUser->Fetch() : false;
        if ($user) {
            $fio = trim(implode(' ', array_filter([
                (string)($user['LAST_NAME'] ?? ''),
                (string)($user['NAME'] ?? ''),
                (string)($user['SECOND_NAME'] ?? ''),
            ])));
            if ($fio !== '') {
                $profile['name'] = $fio;
            } elseif (!empty($user['LOGIN'])) {
                $profile['name'] = (string)$user['LOGIN'];
            }

            if (!empty($user['WORK_POSITION'])) {
                $profile['position'] = (string)$user['WORK_POSITION'];
            }

            $department = prStorageFormatDepartmentValue($user['UF_DEPARTMENT'] ?? '');
            if ($department !== '') {
                $profile['department'] = $department;
            }
        }
    }

    return $profile;
}

function prAssignmentProfile(array $assignment): array
{
    return prUserProfileSummary(
        (int)($assignment['USER_ID'] ?? 0),
        (string)($assignment['USER_NAME'] ?? ''),
        (string)($assignment['POSITION_NAME'] ?? ''),
        (string)($assignment['DEPARTMENT_NAME'] ?? '')
    );
}

function prAssignmentSubstituteProfile(array $assignment): array
{
    return prUserProfileSummary(
        (int)($assignment['SUBSTITUTE_USER_ID'] ?? 0),
        (string)($assignment['SUBSTITUTE_USER_NAME'] ?? ''),
        (string)($assignment['SUBSTITUTE_POSITION_NAME'] ?? ''),
        (string)($assignment['SUBSTITUTE_DEPARTMENT_NAME'] ?? '')
    );
}

function prFetchRouteRules(): array
{
    prEnsureTables();
    $rows = [];
    $rs = prDb()->query("SELECT * FROM b_pr_route_rules ORDER BY SORT, ID");
    while ($row = $rs->fetch()) {
        $row['STEPS'] = array_values(array_filter(prJsonDecode($row['STEPS_JSON'] ?? '[]'), static function ($step): bool {
            return is_array($step) && !in_array((string)($step['role'] ?? ''), ['president', 'registrar'], true);
        }));
        $rows[] = $row;
    }
    return $rows;
}

function prDeleteRouteRule(int $id, int $adminUserId): void
{
    prEnsureTables();
    if ($id <= 0) {
        return;
    }
    prDb()->queryExecute("DELETE FROM b_pr_route_rules WHERE ID = " . $id);
    prAudit($adminUserId, 'route_rule_delete', 'route_rule', $id);
}

function prInstallRoutePresets(int $adminUserId): array
{
    prEnsureTables();
    $installed = [];
    foreach (prRoutePresets() as $preset) {
        $title = (string)($preset['title'] ?? '');
        if ($title === '') {
            continue;
        }
        $exists = prDb()->query("SELECT ID FROM b_pr_route_rules WHERE TITLE = '" . prSql($title) . "' LIMIT 1")->fetch();
        if ($exists) {
            continue;
        }

        $installed[] = prSaveRouteRule([
            'sort' => (int)($preset['sort'] ?? 100),
            'title' => $title,
            'company_key' => (string)($preset['company_key'] ?? ''),
            'site_key' => (string)($preset['site_key'] ?? ''),
            'request_type' => (string)($preset['request_type'] ?? ''),
            'min_amount' => $preset['min_amount'] ?? '',
            'max_amount' => $preset['max_amount'] ?? '',
            'initiator_position' => (string)($preset['initiator_position'] ?? ''),
            'item_category' => (string)($preset['item_category'] ?? ''),
            'steps' => $preset['steps'] ?? prDefaultRouteSteps(),
            'is_active' => 'Y',
        ], $adminUserId);
    }

    prAudit($adminUserId, 'route_presets_install', 'route_rule', 0, ['installed_ids' => $installed]);
    return $installed;
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
    $steps = array_values(array_filter(array_values($steps), static function ($step): bool {
        if (!is_array($step)) {
            return false;
        }
        return !in_array((string)($step['role'] ?? ''), ['president', 'registrar'], true);
    }));
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

function prIsBitrixAdminUser(int $userId): bool
{
    if ($userId <= 0 || !class_exists('CUser')) {
        return false;
    }

    try {
        $groups = array_map('intval', CUser::GetUserGroup($userId));
        return in_array(1, $groups, true);
    } catch (Throwable $e) {
        prLog('auth', ['event' => 'bitrix_admin_check_failed', 'user_id' => $userId, 'message' => $e->getMessage()]);
        return false;
    }
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
    if (prIsBitrixAdminUser($userId)) {
        return true;
    }
    global $USER;
    if (
        is_object($USER)
        && method_exists($USER, 'IsAdmin')
        && method_exists($USER, 'GetID')
        && (int)$USER->GetID() === $userId
        && $USER->IsAdmin()
    ) {
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

    $rs = prDb()->query("
        SELECT ID, USER_ID, SUBSTITUTE_USER_ID
        FROM b_pr_role_assignments
        WHERE (USER_ID = " . $userId . " OR SUBSTITUTE_USER_ID = " . $userId . ")
          AND ROLE_CODE = 'process_admin'
          AND IS_ACTIVE = 'Y'
          AND (ACTIVE_FROM IS NULL OR ACTIVE_FROM <= CURDATE())
          AND (ACTIVE_TO IS NULL OR ACTIVE_TO >= CURDATE())
    ");
    while ($row = $rs->fetch()) {
        if ((int)$row['USER_ID'] === $userId) {
            return true;
        }
        if ((int)$row['SUBSTITUTE_USER_ID'] === $userId && prIsUserAbsentNow((int)$row['USER_ID'])) {
            return true;
        }
    }

    return false;
}

function prFetchActiveUserRoleAssignments(int $userId, string $roleCode): array
{
    prEnsureTables();
    if ($userId <= 0 || $roleCode === '') {
        return [];
    }

    $rows = [];
    $rs = prDb()->query("
        SELECT *
        FROM b_pr_role_assignments
        WHERE (USER_ID = " . $userId . " OR SUBSTITUTE_USER_ID = " . $userId . ")
          AND ROLE_CODE = '" . prSql($roleCode) . "'
          AND IS_ACTIVE = 'Y'
          AND (ACTIVE_FROM IS NULL OR ACTIVE_FROM <= CURDATE())
          AND (ACTIVE_TO IS NULL OR ACTIVE_TO >= CURDATE())
        ORDER BY SITE_KEY, ID
    ");
    while ($row = $rs->fetch()) {
        if ((int)$row['USER_ID'] !== $userId && !prIsUserAbsentNow((int)$row['USER_ID'])) {
            continue;
        }
        $rows[] = $row;
    }
    return $rows;
}

function prIsObserver(int $userId): bool
{
    return (bool)prFetchActiveUserRoleAssignments($userId, 'observer');
}

function prCanViewAllRequests(int $userId): bool
{
    return prIsProcessAdmin($userId) || prIsObserver($userId);
}

function prObserverScopes(int $userId): array
{
    $scopes = [];
    foreach (prFetchActiveUserRoleAssignments($userId, 'observer') as $assignment) {
        $key = (string)($assignment['COMPANY_KEY'] ?? '') . '|' . (string)($assignment['SITE_KEY'] ?? '');
        $scopes[$key] = [
            'company_key' => (string)($assignment['COMPANY_KEY'] ?? ''),
            'site_key' => (string)($assignment['SITE_KEY'] ?? ''),
        ];
    }
    return array_values($scopes);
}

function prCanObserveRequestScope(int $userId, string $companyKey, string $siteKey): bool
{
    if (prIsProcessAdmin($userId)) {
        return true;
    }
    foreach (prObserverScopes($userId) as $scope) {
        $scopeCompany = (string)($scope['company_key'] ?? '');
        $scopeSite = (string)($scope['site_key'] ?? '');
        $companyMatches = $scopeCompany === '' || $scopeCompany === $companyKey;
        $siteMatches = $scopeSite === '' || $scopeSite === $siteKey;
        if ($companyMatches && $siteMatches) {
            return true;
        }
    }
    return false;
}

function prDateRangeContainsNow($from, $to): bool
{
    $now = time();
    $fromText = trim((string)$from);
    $toText = trim((string)$to);
    if ($fromText !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromText) || preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $fromText))) {
        $fromText .= ' 00:00:00';
    }
    if ($toText !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toText) || preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $toText))) {
        $toText .= ' 23:59:59';
    }
    $fromTime = $fromText !== '' ? strtotime($fromText) : 0;
    $toTime = $toText !== '' ? strtotime($toText) : 0;
    if ($fromTime > 0 && $fromTime > $now) {
        return false;
    }
    if ($toTime > 0 && $toTime < $now) {
        return false;
    }
    return $fromTime > 0 || $toTime > 0;
}

function prUserAbsenceRowsFromIntranet(int $userId): array
{
    if ($userId <= 0 || !class_exists('CModule') || !CModule::IncludeModule('intranet') || !class_exists('CIntranetUtils')) {
        return [];
    }

    try {
        if (method_exists('CIntranetUtils', 'GetAbsenceData')) {
            $today = date('d.m.Y');
            $rows = CIntranetUtils::GetAbsenceData([
                'USERS' => [$userId],
                'DATE_START' => $today,
                'DATE_FINISH' => $today,
                'PER_USER' => false,
            ]);
            return is_array($rows) ? $rows : [];
        }
    } catch (Throwable $e) {
        prLog('absence', ['event' => 'intranet_absence_failed', 'user_id' => $userId, 'message' => $e->getMessage()]);
    }

    return [];
}

function prIsUserAbsentNow(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    foreach (prUserAbsenceRowsFromIntranet($userId) as $row) {
        $rowUserId = (int)($row['USER_ID'] ?? $row['ID'] ?? $userId);
        if ($rowUserId > 0 && $rowUserId !== $userId) {
            continue;
        }
        if (prDateRangeContainsNow(
            $row['DATE_ACTIVE_FROM'] ?? $row['DATE_FROM'] ?? $row['DATE_START'] ?? '',
            $row['DATE_ACTIVE_TO'] ?? $row['DATE_TO'] ?? $row['DATE_FINISH'] ?? ''
        )) {
            return $cache[$userId] = true;
        }
    }

    try {
        $db = prDb();
        if ($db->isTableExists('b_intranet_absence')) {
            $row = $db->query("
                SELECT ID
                FROM b_intranet_absence
                WHERE USER_ID = " . $userId . "
                  AND (ACTIVE IS NULL OR ACTIVE = 'Y')
                  AND (DATE_ACTIVE_FROM IS NULL OR DATE(DATE_ACTIVE_FROM) <= CURDATE())
                  AND (DATE_ACTIVE_TO IS NULL OR DATE(DATE_ACTIVE_TO) >= CURDATE())
                LIMIT 1
            ")->fetch();
            return $cache[$userId] = (bool)$row;
        }
    } catch (Throwable $e) {
        prLog('absence', ['event' => 'table_absence_failed', 'user_id' => $userId, 'message' => $e->getMessage()]);
    }

    return $cache[$userId] = false;
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
    $request['ROUTE'] = prEnrichRouteSteps(prJsonDecode($request['ROUTE_SNAPSHOT'] ?? '[]'), $request);
    $request['ATTACHMENTS'] = prFetchAttachments($requestId);
    $request['TIMELINE'] = prFetchRequestTimeline($requestId);
    return $request;
}

function prFetchTaskAssigneesForStep(int $requestId, int $version, int $stepIndex): array
{
    if ($requestId <= 0 || $version <= 0) {
        return [];
    }

    $rows = [];
    $rs = prDb()->query("
        SELECT DISTINCT ASSIGNED_USER_ID, ASSIGNED_USER_NAME, SUBSTITUTE_USER_ID, SUBSTITUTE_USER_NAME
        FROM b_pr_tasks
        WHERE REQUEST_ID = " . $requestId . "
          AND VERSION = " . $version . "
          AND STEP_INDEX = " . $stepIndex . "
        ORDER BY ASSIGNED_USER_ID
    ");
    while ($row = $rs->fetch()) {
        $profile = prUserProfileSummary((int)$row['ASSIGNED_USER_ID'], (string)($row['ASSIGNED_USER_NAME'] ?? ''));
        if ($profile['id'] > 0) {
            $substitute = prUserProfileSummary((int)($row['SUBSTITUTE_USER_ID'] ?? 0), (string)($row['SUBSTITUTE_USER_NAME'] ?? ''));
            if ($substitute['id'] > 0) {
                $profile['substitute'] = $substitute;
            }
            $rows[] = $profile;
        }
    }

    return $rows;
}

function prRouteAssigneesForStep(array $request, int $stepIndex, array $step): array
{
    $taskAssignees = prFetchTaskAssigneesForStep(
        (int)($request['ID'] ?? 0),
        (int)($request['CURRENT_VERSION'] ?? 0),
        $stepIndex
    );
    if ($taskAssignees) {
        return $taskAssignees;
    }

    $roleCode = (string)($step['role'] ?? '');
    if ($roleCode === '') {
        return [];
    }
    if ($roleCode === 'initiator') {
        $profile = prUserProfileSummary(
            (int)($request['INITIATOR_ID'] ?? 0),
            (string)($request['INITIATOR_NAME'] ?? ''),
            (string)($request['INITIATOR_POSITION'] ?? ''),
            (string)($request['DEPARTMENT_NAME'] ?? '')
        );
        return $profile['id'] > 0 ? [$profile] : [];
    }

    $assignees = [];
    foreach (prFindRoleUsers($roleCode, (string)($request['COMPANY_KEY'] ?? ''), (string)($request['SITE_KEY'] ?? '')) as $assignment) {
        $profile = prAssignmentProfile($assignment);
        if ($profile['id'] > 0) {
            $substitute = prAssignmentSubstituteProfile($assignment);
            if ($substitute['id'] > 0) {
                $profile['substitute'] = $substitute;
            }
            $assignees[] = $profile;
        }
    }

    return $assignees;
}

function prEnrichRouteSteps(array $route, array $request): array
{
    $enriched = [];
    foreach (array_values($route) as $index => $step) {
        if (!is_array($step)) {
            continue;
        }
        $step['assignees'] = prRouteAssigneesForStep($request, $index, $step);
        $enriched[] = $step;
    }
    return $enriched;
}

function prFetchRequestTimeline(int $requestId): array
{
    prEnsureTables();
    $events = [];

    $rsTasks = prDb()->query("
        SELECT ID, STEP_INDEX, STEP_CODE, STEP_TITLE, ROLE_CODE, ASSIGNED_USER_ID, ASSIGNED_USER_NAME,
               SUBSTITUTE_USER_ID, SUBSTITUTE_USER_NAME, STATUS, CREATED_AT, COMPLETED_AT
        FROM b_pr_tasks
        WHERE REQUEST_ID = " . $requestId . "
        ORDER BY STEP_INDEX, ID
    ");
    while ($row = $rsTasks->fetch()) {
        $profile = prUserProfileSummary((int)$row['ASSIGNED_USER_ID'], (string)($row['ASSIGNED_USER_NAME'] ?? ''));
        $substitute = prUserProfileSummary((int)($row['SUBSTITUTE_USER_ID'] ?? 0), (string)($row['SUBSTITUTE_USER_NAME'] ?? ''));
        $events[] = [
            'type' => 'task',
            'time' => (string)($row['CREATED_AT'] ?? ''),
            'title' => (string)$row['STEP_TITLE'],
            'status' => (string)$row['STATUS'],
            'role' => (string)$row['ROLE_CODE'],
            'user_id' => $profile['id'],
            'user_name' => $profile['name'],
            'user_position' => $profile['position'],
            'user_department' => $profile['department'],
            'substitute_user_id' => $substitute['id'],
            'substitute_user_name' => $substitute['name'],
            'task_id' => (int)$row['ID'],
        ];
    }

    $rsDecisions = prDb()->query("
        SELECT TASK_ID, USER_ID, USER_NAME, USER_POSITION, USER_DEPARTMENT, ROLE_CODE, DECISION, COMMENT_TEXT, CREATED_AT
        FROM b_pr_decisions
        WHERE REQUEST_ID = " . $requestId . "
        ORDER BY CREATED_AT, ID
    ");
    while ($row = $rsDecisions->fetch()) {
        $profile = prUserProfileSummary(
            (int)$row['USER_ID'],
            (string)($row['USER_NAME'] ?? ''),
            (string)($row['USER_POSITION'] ?? ''),
            (string)($row['USER_DEPARTMENT'] ?? '')
        );
        $events[] = [
            'type' => 'decision',
            'time' => (string)($row['CREATED_AT'] ?? ''),
            'title' => 'Решение: ' . (string)$row['DECISION'],
            'status' => (string)$row['DECISION'],
            'role' => (string)$row['ROLE_CODE'],
            'user_id' => $profile['id'],
            'user_name' => $profile['name'],
            'user_position' => $profile['position'],
            'user_department' => $profile['department'],
            'task_id' => (int)$row['TASK_ID'],
            'comment' => (string)($row['COMMENT_TEXT'] ?? ''),
        ];
    }

    usort($events, static function (array $a, array $b): int {
        return strcmp((string)$a['time'], (string)$b['time']);
    });

    return $events;
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
        'INITIATOR_POSITION' => (string)($data['initiator_position'] ?? ''),
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
    $where = 'INITIATOR_ID = ' . $userId;
    $rows = [];
    $rs = prDb()->query("
        SELECT ID, CREATED_AT, UPDATED_AT, STATUS, CURRENT_VERSION, INITIATOR_ID, INITIATOR_NAME,
               COMPANY_KEY, COMPANY_NAME, SITE_NAME, REQUEST_TYPE, TOTAL_AMOUNT, CURRENCY, REG_NUMBER
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

function prFilterDateValue($value): string
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function prListAllRequests(int $userId, array $filter = []): array
{
    prEnsureTables();
    if (!prCanViewAllRequests($userId)) {
        return [];
    }

    $where = ['1=1'];

    if (!prIsProcessAdmin($userId)) {
        $scopes = prObserverScopes($userId);
        if (!$scopes) {
            return [];
        }
        $scopeWhere = [];
        foreach ($scopes as $scope) {
            $companyKey = (string)($scope['company_key'] ?? '');
            $siteKey = (string)($scope['site_key'] ?? '');
            if ($companyKey === '' && $siteKey === '') {
                $scopeWhere = [];
                break;
            }
            $parts = [];
            if ($companyKey !== '') {
                $parts[] = "COMPANY_KEY = '" . prSql($companyKey) . "'";
            }
            if ($siteKey !== '') {
                $parts[] = "SITE_KEY = '" . prSql($siteKey) . "'";
            }
            if ($parts) {
                $scopeWhere[] = '(' . implode(' AND ', $parts) . ')';
            }
        }
        if ($scopeWhere) {
            $where[] = '(' . implode(' OR ', $scopeWhere) . ')';
        }
    }

    $q = trim((string)($filter['q'] ?? ''));
    if ($q !== '') {
        $like = "'%" . prSql($q) . "%'";
        $search = [
            "INITIATOR_NAME LIKE " . $like,
            "COMPANY_NAME LIKE " . $like,
            "SITE_NAME LIKE " . $like,
            "DEPARTMENT_NAME LIKE " . $like,
            "REG_NUMBER LIKE " . $like,
            "JUSTIFICATION LIKE " . $like,
        ];
        if (preg_match('/^\d+$/', $q)) {
            $search[] = 'ID = ' . (int)$q;
        }
        $where[] = '(' . implode(' OR ', $search) . ')';
    }

    foreach (['company_key' => 'COMPANY_KEY', 'status' => 'STATUS', 'site_key' => 'SITE_KEY', 'request_type' => 'REQUEST_TYPE'] as $inputKey => $column) {
        $value = trim((string)($filter[$inputKey] ?? ''));
        if ($value !== '') {
            $where[] = $column . " = '" . prSql($value) . "'";
        }
    }

    $dateFrom = prFilterDateValue($filter['date_from'] ?? '');
    if ($dateFrom !== '') {
        $where[] = "CREATED_AT >= '" . prSql($dateFrom) . " 00:00:00'";
    }
    $dateTo = prFilterDateValue($filter['date_to'] ?? '');
    if ($dateTo !== '') {
        $where[] = "CREATED_AT <= '" . prSql($dateTo) . " 23:59:59'";
    }

    $rows = [];
    $rs = prDb()->query("
        SELECT ID, CREATED_AT, UPDATED_AT, STATUS, CURRENT_VERSION, INITIATOR_ID, INITIATOR_NAME,
               COMPANY_KEY, COMPANY_NAME, SITE_KEY, SITE_NAME, DEPARTMENT_NAME, REQUEST_TYPE, TOTAL_AMOUNT, CURRENCY, REG_NUMBER
        FROM b_pr_requests
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ID DESC
        LIMIT 500
    ");
    while ($row = $rs->fetch()) {
        $rows[] = $row;
    }
    return $rows;
}

function prUserCanViewRequest(int $requestId, int $userId, bool $isAdmin = false): bool
{
    $row = prDb()->query("SELECT INITIATOR_ID, COMPANY_KEY, SITE_KEY FROM b_pr_requests WHERE ID = " . $requestId)->fetch();
    if (!$row) {
        return false;
    }
    if ($isAdmin && prCanObserveRequestScope($userId, (string)($row['COMPANY_KEY'] ?? ''), (string)($row['SITE_KEY'] ?? ''))) {
        return true;
    }
    if ($row && (int)$row['INITIATOR_ID'] === $userId) {
        return true;
    }
    $rsTasks = prDb()->query("
        SELECT ID, ASSIGNED_USER_ID, SUBSTITUTE_USER_ID FROM b_pr_tasks
        WHERE REQUEST_ID = " . $requestId . "
          AND (ASSIGNED_USER_ID = " . $userId . " OR SUBSTITUTE_USER_ID = " . $userId . ")
    ");
    while ($task = $rsTasks->fetch()) {
        if ((int)$task['ASSIGNED_USER_ID'] === $userId) {
            return true;
        }
        if ((int)$task['SUBSTITUTE_USER_ID'] === $userId && prIsUserAbsentNow((int)$task['ASSIGNED_USER_ID'])) {
            return true;
        }
    }
    return false;
}

function prFetchUserTasks(int $userId): array
{
    prEnsureTables();
    $rows = [];
    $rs = prDb()->query("
        SELECT t.*, r.INITIATOR_NAME, r.COMPANY_NAME, r.SITE_NAME, r.DEPARTMENT_NAME, r.INITIATOR_POSITION,
               r.PLACE_TEXT, r.REQUEST_TYPE, r.JUSTIFICATION, r.TOTAL_AMOUNT, r.CURRENCY, r.STATUS AS REQUEST_STATUS
        FROM b_pr_tasks t
        INNER JOIN b_pr_requests r ON r.ID = t.REQUEST_ID
        WHERE (t.ASSIGNED_USER_ID = " . $userId . " OR t.SUBSTITUTE_USER_ID = " . $userId . ")
          AND t.STATUS = 'OPEN'
        ORDER BY t.CREATED_AT DESC, t.ID DESC
        LIMIT 200
    ");
    while ($row = $rs->fetch()) {
        $isSubstitute = (int)($row['SUBSTITUTE_USER_ID'] ?? 0) === $userId && (int)$row['ASSIGNED_USER_ID'] !== $userId;
        if ($isSubstitute && !prIsUserAbsentNow((int)$row['ASSIGNED_USER_ID'])) {
            continue;
        }
        $row['IS_SUBSTITUTE'] = $isSubstitute ? 'Y' : 'N';
        $row['AVAILABLE_ITEM_IDS_ARRAY'] = prJsonDecode($row['AVAILABLE_ITEM_IDS'] ?? '[]');
        $items = prFetchRequestItems((int)$row['REQUEST_ID'], (int)$row['VERSION']);
        $items = array_values(array_filter($items, static function (array $item): bool {
            return (string)($item['FINAL_STATUS'] ?? 'ACTIVE') === 'ACTIVE';
        }));
        $allowed = array_map('intval', $row['AVAILABLE_ITEM_IDS_ARRAY']);
        if ($allowed) {
            $items = array_values(array_filter($items, static function (array $item) use ($allowed): bool {
                return in_array((int)$item['ID'], $allowed, true);
            }));
        }
        $row['ITEMS'] = $items;
        $row['ATTACHMENTS'] = prFetchAttachments((int)$row['REQUEST_ID']);
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
