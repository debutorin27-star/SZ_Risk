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
    $position = (string)($request['INITIATOR_POSITION'] ?? '');
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

function prApplySpecialRouteRules(array $steps, array $request): array
{
    return $steps;
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
    $assignees = prFindRoleUsers($roleCode, (string)$request['COMPANY_KEY'], (string)$request['SITE_KEY']);
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
    $nextStep = $route[$nextIndex] ?? null;

    if (!$nextStep) {
        prDb()->queryExecute("UPDATE b_pr_requests SET STATUS = 'DONE', UPDATED_AT = NOW() WHERE ID = " . $requestId);
        prAudit($actorUserId, 'workflow_done', 'request', $requestId);
        return;
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

function prApplyTaskDecision(int $taskId, int $userId, string $decision, string $comment, array $itemIds = [], array $warehouse = [], array $registration = []): void
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

    if ((string)$task['ROLE_CODE'] === 'registrar' && $decision === 'revision') {
        throw new RuntimeException('Регистратор не может вернуть заявку на доработку.');
    }

    if (in_array($decision, ['reject', 'revision'], true) && trim($comment) === '') {
        throw new RuntimeException('Комментарий обязателен при отклонении или возврате.');
    }

    $requestId = (int)$task['REQUEST_ID'];
    $version = (int)$task['VERSION'];
    $availableIds = array_map('intval', $task['AVAILABLE_ITEM_IDS_ARRAY'] ?? []);
    $itemIds = array_values(array_intersect(array_map('intval', $itemIds ?: $availableIds), $availableIds));
    if (!$itemIds) {
        $itemIds = $availableIds;
    }

    if ((string)$task['ROLE_CODE'] === 'warehouse') {
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

    if ($decision === 'reject') {
        foreach ($itemIds as $itemId) {
            prDb()->queryExecute("
                UPDATE b_pr_request_items
                SET FINAL_STATUS = 'REJECTED'
                WHERE ID = " . (int)$itemId . " AND REQUEST_ID = " . $requestId . " AND VERSION = " . $version
            );
        }
    }

    $registeredByThisDecision = false;
    if ((string)$task['ROLE_CODE'] === 'registrar' && $decision === 'approve' && trim((string)($registration['reg_number'] ?? '')) === '') {
        throw new RuntimeException('Укажите регистрационный номер.');
    }

    if ((string)$task['ROLE_CODE'] === 'registrar' && $decision === 'approve' && !empty($registration['reg_number'])) {
        prDb()->queryExecute("
            UPDATE b_pr_requests
            SET REG_NUMBER = '" . prSql((string)$registration['reg_number']) . "',
                REG_DATE = " . (!empty($registration['reg_date']) ? "'" . prSql((string)$registration['reg_date']) . "'" : 'CURDATE()') . ",
                STATUS = 'REGISTERED',
                UPDATED_AT = NOW()
            WHERE ID = " . $requestId
        );
        $registeredByThisDecision = true;
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
        'ROLE_CODE' => (string)$task['ROLE_CODE'],
        'DECISION' => $decision,
        'ITEM_IDS' => prJsonEncode($itemIds),
        'COMMENT_TEXT' => $comment,
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
        'comment' => $comment,
    ]);

    if ($registeredByThisDecision) {
        prGenerateRegisteredDocument($requestId, $userId);
    }

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
