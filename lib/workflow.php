<?php

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/document.php';

function prResolveRoute(array $request): array
{
    prEnsureTables();
    $companyKey = (string)($request['COMPANY_KEY'] ?? '');
    $siteKey = (string)($request['SITE_KEY'] ?? '');
    $requestType = (string)($request['REQUEST_TYPE'] ?? '');
    $amount = (float)($request['TOTAL_AMOUNT'] ?? 0);
    $position = trim((string)($request['INITIATOR_POSITION'] ?? '') . ' ' . (string)($request['DEPARTMENT_NAME'] ?? ''));
    $itemCategories = [];
    foreach (is_array($request['ITEMS'] ?? null) ? $request['ITEMS'] : [] as $item) {
        $category = (string)($item['CATEGORY'] ?? '');
        if ($category !== '') {
            $itemCategories[$category] = $category;
        }
    }

    $rs = prDb()->query("
        SELECT *
        FROM b_pr_route_rules
        WHERE IS_ACTIVE = 'Y'
          AND (COMPANY_KEY = '' OR COMPANY_KEY = '" . prSql($companyKey) . "')
          AND (SITE_KEY = '' OR SITE_KEY = '" . prSql($siteKey) . "')
          AND (REQUEST_TYPE = '' OR REQUEST_TYPE = '" . prSql($requestType) . "')
          AND (MIN_AMOUNT IS NULL OR MIN_AMOUNT <= " . $amount . ")
          AND (MAX_AMOUNT IS NULL OR MAX_AMOUNT >= " . $amount . ")
        ORDER BY
          SORT,
          CASE WHEN COMPANY_KEY = '" . prSql($companyKey) . "' THEN 0 ELSE 1 END,
          CASE WHEN SITE_KEY = '" . prSql($siteKey) . "' THEN 0 ELSE 1 END,
          CASE WHEN REQUEST_TYPE = '" . prSql($requestType) . "' THEN 0 ELSE 1 END,
          CASE WHEN INITIATOR_POSITION <> '' THEN 0 ELSE 1 END,
          CASE WHEN ITEM_CATEGORY <> '' THEN 0 ELSE 1 END,
          ID
    ");
    $rule = null;
    while ($candidate = $rs->fetch()) {
        $candidatePosition = trim((string)($candidate['INITIATOR_POSITION'] ?? ''));
        if ($candidatePosition !== '' && !prTextContains($position, $candidatePosition)) {
            continue;
        }

        $candidateCategory = trim((string)($candidate['ITEM_CATEGORY'] ?? ''));
        if ($candidateCategory !== '' && !isset($itemCategories[$candidateCategory])) {
            continue;
        }

        $rule = $candidate;
        break;
    }
    if (!$rule) {
        return prEnrichRouteSteps(prApplySpecialRouteRules(prDefaultRouteSteps(), $request), $request);
    }

    $steps = prJsonDecode($rule['STEPS_JSON'] ?? '[]');
    return prEnrichRouteSteps(prApplySpecialRouteRules($steps ?: prDefaultRouteSteps(), $request), $request);
}

function prTextContains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
    return stripos($haystack, $needle) !== false;
}

function prRouteHasRole(array $steps, string $roleCode): bool
{
    foreach ($steps as $step) {
        if ((string)($step['role'] ?? '') === $roleCode) {
            return true;
        }
    }
    return false;
}

function prInsertRouteStepBefore(array $steps, array $step, array $beforeCodes): array
{
    $insertAt = count($steps);
    foreach ($steps as $index => $existingStep) {
        $code = (string)($existingStep['code'] ?? '');
        $role = (string)($existingStep['role'] ?? '');
        if (in_array($code, $beforeCodes, true) || in_array($role, $beforeCodes, true)) {
            $insertAt = $index;
            break;
        }
    }

    array_splice($steps, $insertAt, 0, [$step]);
    return $steps;
}

function prExcludedWorkflowRoles(): array
{
    return [
        'president' => true,
        'registrar' => true,
    ];
}

function prWorkflowStep(string $code, string $role, string $title, string $status): array
{
    return ['code' => $code, 'role' => $role, 'title' => $title, 'status' => $status];
}

function prRouteStepByRole(array $steps, string $roleCode, array $fallback): array
{
    foreach ($steps as $step) {
        if (is_array($step) && (string)($step['role'] ?? '') === $roleCode) {
            return $step;
        }
    }
    return $fallback;
}

function prSanitizeRouteSteps(array $steps): array
{
    $excluded = prExcludedWorkflowRoles();
    $sanitized = [];
    foreach (array_values($steps) as $step) {
        if (!is_array($step)) {
            continue;
        }
        $roleCode = (string)($step['role'] ?? '');
        if ($roleCode === '' || isset($excluded[$roleCode])) {
            continue;
        }
        $sanitized[] = $step;
    }
    return $sanitized;
}

function prNormalizeRouteFinalSteps(array $steps, bool $includeWarehouse): array
{
    $steps = prSanitizeRouteSteps($steps);
    $warehouse = prRouteStepByRole($steps, 'warehouse', prWorkflowStep('warehouse', 'warehouse', 'Проверка склада', 'WAREHOUSE'));
    $supply = prRouteStepByRole($steps, 'supply', prWorkflowStep('supply', 'supply', 'Задача снабжению', 'SUPPLY'));
    $acceptance = prWorkflowStep('initiator_acceptance', 'initiator', 'Приемка выполнения инициатором', 'ACCEPTANCE');

    $normalized = [];
    foreach ($steps as $step) {
        $roleCode = (string)($step['role'] ?? '');
        $stepCode = (string)($step['code'] ?? '');
        if ($roleCode === 'warehouse' || $roleCode === 'supply' || ($roleCode === 'initiator' && $stepCode === 'initiator_acceptance')) {
            continue;
        }
        $normalized[] = $step;
    }

    if ($includeWarehouse) {
        $normalized[] = $warehouse;
    }
    $normalized[] = $supply;
    $normalized[] = $acceptance;

    return $normalized;
}

function prApplySpecialRouteRules(array $steps, array $request): array
{
    $requestType = (string)($request['REQUEST_TYPE'] ?? '');
    $amount = (float)($request['TOTAL_AMOUNT'] ?? 0);

    if ($requestType === 'raw_materials') {
        return [
            prWorkflowStep('profile_approval', 'profile_approver', 'Профильное согласование', 'APPROVAL'),
            prWorkflowStep('production_chief', 'production_chief', 'Согласование начальником производства', 'APPROVAL'),
            prWorkflowStep('warehouse', 'warehouse', 'Проверка склада', 'WAREHOUSE'),
            prWorkflowStep('supply', 'supply', 'Задача снабжению', 'SUPPLY'),
            prWorkflowStep('initiator_acceptance', 'initiator', 'Приемка выполнения инициатором', 'ACCEPTANCE'),
        ];
    }

    if ($requestType === 'computers') {
        $route = [
            prWorkflowStep('automation_head', 'automation_head', 'Согласование начальником отдела автоматизации', 'APPROVAL'),
        ];
        if ($amount > PR_COMPUTERS_EXPENSE_CONTROL_LIMIT) {
            $route[] = prWorkflowStep('expense_control', 'expense_control', 'Контроль расходов', 'APPROVAL');
        }
        $route[] = prWorkflowStep('supply', 'supply', 'Задача снабжению', 'SUPPLY');
        $route[] = prWorkflowStep('initiator_acceptance', 'initiator', 'Приемка выполнения инициатором', 'ACCEPTANCE');
        return $route;
    }

    return prNormalizeRouteFinalSteps($steps, true);
}

function prActiveItemIds(int $requestId, int $version): array
{
    $ids = [];
    $rs = prDb()->query("
        SELECT ID FROM b_pr_request_items
        WHERE REQUEST_ID = " . $requestId . "
          AND VERSION = " . $version . "
          AND FINAL_STATUS = 'ACTIVE'
        ORDER BY SORT, ID
    ");
    while ($row = $rs->fetch()) {
        $ids[] = (int)$row['ID'];
    }
    return $ids;
}

function prCreateStepTasks(array $request, int $stepIndex, array $step, int $actorUserId): array
{
    $requestId = (int)$request['ID'];
    $version = (int)$request['CURRENT_VERSION'];
    $roleCode = (string)($step['role'] ?? '');
    $stepTitle = (string)($step['title'] ?? $roleCode);
    if (isset(prExcludedWorkflowRoles()[$roleCode])) {
        prAudit($actorUserId, 'workflow_excluded_role_skipped', 'request', $requestId, ['role' => $roleCode, 'step' => $step]);
        return [];
    }

    if ($roleCode === 'initiator') {
        $initiator = prUserProfileSummary(
            (int)$request['INITIATOR_ID'],
            (string)($request['INITIATOR_NAME'] ?? ''),
            (string)($request['INITIATOR_POSITION'] ?? ''),
            (string)($request['DEPARTMENT_NAME'] ?? '')
        );
        $assignees = $initiator['id'] > 0 ? [[
            'USER_ID' => $initiator['id'],
            'USER_NAME' => $initiator['name'],
            'POSITION_NAME' => $initiator['position'],
            'DEPARTMENT_NAME' => $initiator['department'],
        ]] : [];
    } else {
        $assignees = prFindRoleUsers($roleCode, (string)$request['COMPANY_KEY'], (string)$request['SITE_KEY']);
    }
    $itemIds = prActiveItemIds($requestId, $version);

    if (!$assignees && PR_ADMIN_USER_IDS) {
        foreach (PR_ADMIN_USER_IDS as $adminId) {
            $assignees[] = ['USER_ID' => (int)$adminId, 'USER_NAME' => 'Администратор #' . (int)$adminId];
        }
        prAudit($actorUserId, 'workflow_missing_assignee_fallback', 'request', $requestId, ['role' => $roleCode, 'step' => $step]);
    }

    $created = [];
    foreach ($assignees as $assignee) {
        $toUserId = (int)$assignee['USER_ID'];
        if ($toUserId <= 0) {
            continue;
        }
        $configuredSubstituteUserId = (int)($assignee['SUBSTITUTE_USER_ID'] ?? 0);
        $substituteIsActive = $configuredSubstituteUserId > 0 && $configuredSubstituteUserId !== $toUserId && prIsUserAbsentNow($toUserId);
        $substituteUserId = $configuredSubstituteUserId > 0 && $configuredSubstituteUserId !== $toUserId ? $configuredSubstituteUserId : 0;
        prDbInsert('b_pr_tasks', [
            'REQUEST_ID' => $requestId,
            'VERSION' => $version,
            'STEP_INDEX' => $stepIndex,
            'STEP_CODE' => (string)($step['code'] ?? $roleCode),
            'STEP_TITLE' => $stepTitle,
            'ROLE_CODE' => $roleCode,
            'ASSIGNED_USER_ID' => $toUserId,
            'ASSIGNED_USER_NAME' => (string)($assignee['USER_NAME'] ?? ''),
            'SUBSTITUTE_USER_ID' => $substituteUserId,
            'SUBSTITUTE_USER_NAME' => $substituteUserId > 0 ? (string)($assignee['SUBSTITUTE_USER_NAME'] ?? '') : '',
            'STATUS' => 'OPEN',
            'AVAILABLE_ITEM_IDS' => prJsonEncode($itemIds),
            'CREATED_AT' => prNow(),
        ]);
        $taskId = (int)prDb()->getInsertedId();
        $created[] = $taskId;
        prNotifyUser($toUserId, $request, $step, $taskId);
        if ($substituteIsActive) {
            prNotifyUser($substituteUserId, $request, $step, $taskId);
        }
    }

    prAudit($actorUserId, 'workflow_step_tasks_created', 'request', $requestId, [
        'step_index' => $stepIndex,
        'step' => $step,
        'tasks' => $created,
    ]);

    return $created;
}

function prSubmitRequest(int $requestId, int $userId): void
{
    prEnsureTables();
    $request = prGetRequest($requestId);
    if (!$request) {
        throw new RuntimeException('Заявка не найдена.');
    }
    if ((int)$request['INITIATOR_ID'] !== $userId) {
        throw new RuntimeException('Отправить может только инициатор.');
    }
    if (!in_array((string)$request['STATUS'], ['DRAFT', 'REVISION'], true)) {
        throw new RuntimeException('Заявка уже отправлена.');
    }

    $currentVersion = (int)$request['CURRENT_VERSION'];
    $newVersion = $currentVersion > 0 ? $currentVersion : 1;
    if ($currentVersion <= 0) {
        prDb()->queryExecute("
            UPDATE b_pr_request_items
            SET VERSION = " . $newVersion . "
            WHERE REQUEST_ID = " . $requestId . " AND VERSION = 0
        ");
        prDb()->queryExecute("
            UPDATE b_pr_attachments
            SET VERSION = " . $newVersion . "
            WHERE REQUEST_ID = " . $requestId . " AND VERSION = 0
        ");
    }

    $request['CURRENT_VERSION'] = $newVersion;
    $request['ITEMS'] = prFetchRequestItems($requestId, $newVersion);
    $route = prResolveRoute($request);
    $firstStep = $route[0] ?? null;
    if (!$firstStep) {
        throw new RuntimeException('Не удалось определить маршрут.');
    }

    prDb()->queryExecute("
        UPDATE b_pr_requests
        SET CURRENT_VERSION = " . $newVersion . ",
            STATUS = '" . prSql((string)($firstStep['status'] ?? 'APPROVAL')) . "',
            ROUTE_SNAPSHOT = '" . prSql(prJsonEncode($route)) . "',
            UPDATED_AT = NOW()
        WHERE ID = " . $requestId
    );

    $request = prGetRequest($requestId);
    prCreateStepTasks($request, 0, $firstStep, $userId);
    prAudit($userId, 'request_submit', 'request', $requestId, ['version' => $newVersion, 'route' => $route]);
}

function prStepHasOpenTasks(int $requestId, int $version, int $stepIndex): bool
{
    $row = prDb()->query("
        SELECT ID FROM b_pr_tasks
        WHERE REQUEST_ID = " . $requestId . "
          AND VERSION = " . $version . "
          AND STEP_INDEX = " . $stepIndex . "
          AND STATUS = 'OPEN'
        LIMIT 1
    ")->fetch();
    return (bool)$row;
}

function prRegistrationNumberForRequest(int $requestId): string
{
    return 'ЗП-' . date('Y') . '-' . str_pad((string)$requestId, 5, '0', STR_PAD_LEFT);
}

function prEnsureRequestRegistration(int $requestId, int $actorUserId): void
{
    $request = prGetRequest($requestId);
    if (!$request) {
        return;
    }

    $existingNumber = trim((string)($request['REG_NUMBER'] ?? ''));
    $regNumber = $existingNumber !== '' ? $existingNumber : prRegistrationNumberForRequest($requestId);

    prDb()->queryExecute("
        UPDATE b_pr_requests
        SET REG_NUMBER = '" . prSql($regNumber) . "',
            REG_DATE = IF(REG_DATE IS NULL, CURDATE(), REG_DATE),
            UPDATED_AT = NOW()
        WHERE ID = " . $requestId
    );

    if ($existingNumber === '') {
        prAudit($actorUserId, 'request_auto_registered', 'request', $requestId, ['reg_number' => $regNumber]);
    }

    $request = prGetRequest($requestId);
    if ($request && empty($request['GENERATED_DOCUMENT_FILE_ID'])) {
        prGenerateRegisteredDocument($requestId, $actorUserId);
    }
}

function prAdvanceWorkflowIfReady(int $requestId, int $version, int $stepIndex, int $actorUserId): void
{
    if (prStepHasOpenTasks($requestId, $version, $stepIndex)) {
        return;
    }

    $request = prGetRequest($requestId);
    if (!$request) {
        return;
    }
    $route = prJsonDecode($request['ROUTE_SNAPSHOT'] ?? '[]');
    $nextIndex = $stepIndex + 1;
    $nextStep = null;
    $excluded = prExcludedWorkflowRoles();
    while (isset($route[$nextIndex])) {
        $candidate = $route[$nextIndex];
        if (!is_array($candidate)) {
            $nextIndex++;
            continue;
        }
        $roleCode = (string)($candidate['role'] ?? '');
        if (isset($excluded[$roleCode])) {
            prAudit($actorUserId, 'workflow_excluded_step_skipped', 'request', $requestId, [
                'step_index' => $nextIndex,
                'step' => $candidate,
            ]);
            $nextIndex++;
            continue;
        }
        $nextStep = $candidate;
        break;
    }

    if (!$nextStep) {
        prEnsureRequestRegistration($requestId, $actorUserId);
        prDb()->queryExecute("UPDATE b_pr_requests SET STATUS = 'DONE', UPDATED_AT = NOW() WHERE ID = " . $requestId);
        prAudit($actorUserId, 'workflow_done', 'request', $requestId);
        return;
    }

    if ((string)($nextStep['role'] ?? '') === 'supply') {
        prEnsureRequestRegistration($requestId, $actorUserId);
    }

    prDb()->queryExecute("
        UPDATE b_pr_requests
        SET STATUS = '" . prSql((string)($nextStep['status'] ?? 'APPROVAL')) . "',
            UPDATED_AT = NOW()
        WHERE ID = " . $requestId
    );

    $request = prGetRequest($requestId);
    prCreateStepTasks($request, $nextIndex, $nextStep, $actorUserId);
}

function prSkipOpenExcludedWorkflowTasks(int $actorUserId): void
{
    $roles = array_keys(prExcludedWorkflowRoles());
    if (!$roles) {
        return;
    }

    $quotedRoles = array_map(static function (string $role): string {
        return "'" . prSql($role) . "'";
    }, $roles);

    $tasks = [];
    $rs = prDb()->query("
        SELECT ID, REQUEST_ID, VERSION, STEP_INDEX, ROLE_CODE
        FROM b_pr_tasks
        WHERE STATUS = 'OPEN'
          AND ROLE_CODE IN (" . implode(',', $quotedRoles) . ")
        ORDER BY REQUEST_ID, VERSION, STEP_INDEX, ID
        LIMIT 200
    ");
    while ($row = $rs->fetch()) {
        $tasks[] = $row;
    }

    foreach ($tasks as $task) {
        $taskId = (int)$task['ID'];
        $requestId = (int)$task['REQUEST_ID'];
        $version = (int)$task['VERSION'];
        $stepIndex = (int)$task['STEP_INDEX'];

        prDb()->queryExecute("
            UPDATE b_pr_tasks
            SET STATUS = 'DONE',
                COMPLETED_AT = NOW()
            WHERE ID = " . $taskId . "
              AND STATUS = 'OPEN'
        ");
        prAudit($actorUserId, 'workflow_excluded_task_closed', 'task', $taskId, [
            'request_id' => $requestId,
            'role' => (string)($task['ROLE_CODE'] ?? ''),
        ]);
        prAdvanceWorkflowIfReady($requestId, $version, $stepIndex, $actorUserId);
    }
}

function prApplyTaskDecision(
    int $taskId,
    int $userId,
    string $decision,
    string $comment,
    array $itemIds = [],
    array $warehouse = [],
    array $supply = [],
    array $itemDecisions = []
): void
{
    prEnsureTables();
    $task = prFetchTask($taskId);
    $canAct = $task
        && (
            (int)$task['ASSIGNED_USER_ID'] === $userId
            || ((int)($task['SUBSTITUTE_USER_ID'] ?? 0) === $userId && prIsUserAbsentNow((int)$task['ASSIGNED_USER_ID']))
        )
        && (string)$task['STATUS'] === 'OPEN';
    if (!$canAct) {
        throw new RuntimeException('Задание недоступно.');
    }

    if (in_array($decision, ['reject', 'revision'], true) && trim($comment) === '') {
        throw new RuntimeException('Комментарий обязателен при отклонении или возврате.');
    }

    $requestId = (int)$task['REQUEST_ID'];
    $version = (int)$task['VERSION'];
    $roleCode = (string)$task['ROLE_CODE'];
    $availableIds = array_map('intval', $task['AVAILABLE_ITEM_IDS_ARRAY'] ?? []);
    $activeAvailableIds = array_values(array_intersect($availableIds, prActiveItemIds($requestId, $version)));
    $decisionAvailableIds = $activeAvailableIds ?: $availableIds;
    $itemIds = array_values(array_intersect(array_map('intval', $itemIds ?: $decisionAvailableIds), $decisionAvailableIds));
    if (!$itemIds) {
        $itemIds = $decisionAvailableIds;
    }

    if ($roleCode === 'warehouse') {
        $warehouseItems = is_array($warehouse['items'] ?? null) ? $warehouse['items'] : [];
        if ($warehouseItems) {
            foreach ($decisionAvailableIds as $itemId) {
                $warehouseItem = $warehouseItems[(string)$itemId] ?? $warehouseItems[$itemId] ?? [];
                if (!is_array($warehouseItem)) {
                    $warehouseItem = [];
                }
                $status = (string)($warehouseItem['status'] ?? '');
                $qty = isset($warehouseItem['qty']) && $warehouseItem['qty'] !== '' ? (float)$warehouseItem['qty'] : null;
                $warehouseComment = (string)($warehouseItem['comment'] ?? '');
                if ($decision === 'approve' && $status === '') {
                    throw new RuntimeException('Укажите наличие по каждой позиции.');
                }
                if ($status === '') {
                    continue;
                }
                prDb()->queryExecute("
                    UPDATE b_pr_request_items
                    SET WAREHOUSE_STATUS = '" . prSql($status) . "',
                        WAREHOUSE_QTY = " . ($qty === null ? 'NULL' : (string)$qty) . ",
                        WAREHOUSE_COMMENT = '" . prSql($warehouseComment) . "'
                    WHERE ID = " . (int)$itemId . "
                      AND REQUEST_ID = " . $requestId . "
                      AND VERSION = " . $version
                );
            }
        } else {
            $status = (string)($warehouse['status'] ?? '');
            $qty = isset($warehouse['qty']) && $warehouse['qty'] !== '' ? (float)$warehouse['qty'] : null;
            $warehouseComment = (string)($warehouse['comment'] ?? '');
            if ($status !== '') {
                foreach ($itemIds as $itemId) {
                    prDb()->queryExecute("
                        UPDATE b_pr_request_items
                        SET WAREHOUSE_STATUS = '" . prSql($status) . "',
                            WAREHOUSE_QTY = " . ($qty === null ? 'NULL' : (string)$qty) . ",
                            WAREHOUSE_COMMENT = '" . prSql($warehouseComment) . "'
                        WHERE ID = " . (int)$itemId . "
                          AND REQUEST_ID = " . $requestId . "
                          AND VERSION = " . $version
                    );
                }
            }
        }
    }

    if ($roleCode !== 'warehouse' && $roleCode !== 'supply' && $roleCode !== 'initiator' && $decision === 'approve' && $itemDecisions) {
        $rejectedItemIds = [];
        foreach ($itemDecisions as $itemId => $itemDecision) {
            $itemId = (int)$itemId;
            if (!in_array($itemId, $decisionAvailableIds, true)) {
                continue;
            }
            $decisionValue = is_array($itemDecision) ? (string)($itemDecision['decision'] ?? '') : (string)$itemDecision;
            if ($decisionValue === 'reject') {
                $rejectedItemIds[] = $itemId;
            }
        }
        foreach ($rejectedItemIds as $itemId) {
            prDb()->queryExecute("
                UPDATE b_pr_request_items
                SET FINAL_STATUS = 'REJECTED'
                WHERE ID = " . (int)$itemId . " AND REQUEST_ID = " . $requestId . " AND VERSION = " . $version
            );
        }
    }

    if ($decision === 'reject') {
        foreach ($itemIds as $itemId) {
            prDb()->queryExecute("
                UPDATE b_pr_request_items
                SET FINAL_STATUS = 'REJECTED'
                WHERE ID = " . (int)$itemId . " AND REQUEST_ID = " . $requestId . " AND VERSION = " . $version
            );
        }
    }

    $decisionComment = $comment;
    if ($roleCode === 'supply' && $decision === 'approve') {
        $checklist = is_array($supply['checklist'] ?? null) ? $supply['checklist'] : [];
        $lines = [];
        foreach (prSupplyChecklistLabels() as $key => $label) {
            if (empty($checklist[$key])) {
                throw new RuntimeException('Заполните чек-лист снабжения полностью.');
            }
            $lines[] = '- ' . $label;
        }
        $decisionComment = trim($decisionComment . "\n\nЧек-лист снабжения:\n" . implode("\n", $lines));
    }

    $actorProfile = prUserProfileSummary($userId);

    prDbInsert('b_pr_decisions', [
        'REQUEST_ID' => $requestId,
        'TASK_ID' => $taskId,
        'VERSION' => $version,
        'USER_ID' => $userId,
        'USER_NAME' => $actorProfile['name'],
        'USER_POSITION' => $actorProfile['position'],
        'USER_DEPARTMENT' => $actorProfile['department'],
        'ROLE_CODE' => $roleCode,
        'DECISION' => $decision,
        'ITEM_IDS' => prJsonEncode($itemIds),
        'COMMENT_TEXT' => $decisionComment,
        'CREATED_AT' => prNow(),
    ]);

    prDb()->queryExecute("
        UPDATE b_pr_tasks
        SET STATUS = 'DONE',
            COMPLETED_AT = NOW()
        WHERE ID = " . $taskId
    );

    prAudit($userId, 'task_decision_' . $decision, 'task', $taskId, [
        'request_id' => $requestId,
        'item_ids' => $itemIds,
        'comment' => $decisionComment,
        'item_decisions' => $itemDecisions,
    ]);

    if ($decision === 'revision') {
        prDb()->queryExecute("UPDATE b_pr_requests SET STATUS = 'REVISION', UPDATED_AT = NOW() WHERE ID = " . $requestId);
        return;
    }

    $activeIds = prActiveItemIds($requestId, $version);
    if (!$activeIds) {
        prDb()->queryExecute("UPDATE b_pr_requests SET STATUS = 'REJECTED', UPDATED_AT = NOW() WHERE ID = " . $requestId);
        return;
    }

    prAdvanceWorkflowIfReady($requestId, $version, (int)$task['STEP_INDEX'], $userId);
}
