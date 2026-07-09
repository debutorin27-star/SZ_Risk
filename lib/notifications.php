<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../runtime.php';
require_once __DIR__ . '/../auth.php';

function prNotifyTextValue($value): string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : 'не указано';
}

function prNotifyMoney($value, string $currency = PR_DEFAULT_CURRENCY): string
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
}

function prNotifyCompactItems(array $items, int $limit = 5, string $currency = PR_DEFAULT_CURRENCY): string
{
    $lines = [];
    foreach (array_slice(array_values($items), 0, $limit) as $index => $item) {
        $qty = (float)($item['QUANTITY'] ?? 0);
        $unit = prUnits()[(string)($item['UNIT'] ?? '')] ?? (string)($item['UNIT'] ?? '');
        $price = (float)($item['ESTIMATED_PRICE'] ?? 0);
        $amount = $qty * max(0, $price);
        $lines[] = ($index + 1) . '. ' . prNotifyTextValue($item['NAME'] ?? '') .
            ' — ' . rtrim(rtrim(number_format($qty, 4, ',', ' '), '0'), ',') . ' ' . $unit .
            ($amount > 0 ? ', ' . prNotifyMoney($amount, $currency) : '');
    }

    $left = count($items) - $limit;
    if ($left > 0) {
        $lines[] = '...и еще ' . $left . ' строк(и)';
    }

    return $lines ? implode("\n", $lines) : 'Строки не заполнены';
}

function prNotifyActiveItems(array $request): array
{
    return array_values(array_filter(array_values($request['ITEMS'] ?? []), static function (array $item): bool {
        return (string)($item['FINAL_STATUS'] ?? 'ACTIVE') === 'ACTIVE';
    }));
}

function prNotifyBuildPayload(array $request, array $step, int $taskId = 0): array
{
    $requestId = (int)($request['ID'] ?? 0);
    $requestLink = prAbsoluteUrl('index.php', ['request_id' => $requestId]);
    $approvalLink = $taskId > 0
        ? prAbsoluteUrl('index.php', ['view' => 'tasks', 'task_id' => $taskId, 'request_id' => $requestId])
        : $requestLink;
    $stepTitle = (string)($step['title'] ?? $step['code'] ?? 'Принять решение');
    $roleCode = (string)($step['role'] ?? '');
    $roleTitle = prRoleLabels()[$roleCode] ?? $roleCode;
    $requestType = prRequestTypes()[(string)($request['REQUEST_TYPE'] ?? '')] ?? (string)($request['REQUEST_TYPE'] ?? '');
    $currency = (string)($request['CURRENCY'] ?? PR_DEFAULT_CURRENCY);
    $items = prNotifyActiveItems($request);

    $title = 'Нужно принять решение по заявке на закупку #' . $requestId;
    $lines = [
        $title,
        '',
        'Этап: ' . $stepTitle . ($roleTitle !== '' ? ' (' . $roleTitle . ')' : ''),
        'Площадка: ' . prNotifyTextValue($request['SITE_NAME'] ?? ''),
        'Подразделение: ' . prNotifyTextValue($request['DEPARTMENT_NAME'] ?? ''),
        'Инициатор: ' . prNotifyTextValue($request['INITIATOR_NAME'] ?? '') . ((string)($request['INITIATOR_POSITION'] ?? '') !== '' ? ' — ' . (string)$request['INITIATOR_POSITION'] : ''),
        'Тип заявки: ' . prNotifyTextValue($requestType),
        'Сумма: ' . prNotifyMoney($request['TOTAL_AMOUNT'] ?? 0, $currency),
        '',
        'Состав закупки:',
        prNotifyCompactItems($items, 5, $currency),
        '',
        'Перейти к согласованию: ' . $approvalLink,
        'Карточка заявки: ' . $requestLink,
    ];

    $plain = implode("\n", $lines);
    $html = '[B]' . $title . "[/B]\n\n" .
        '[B]Этап:[/B] ' . $stepTitle . ($roleTitle !== '' ? ' (' . $roleTitle . ')' : '') . "\n" .
        '[B]Площадка:[/B] ' . prNotifyTextValue($request['SITE_NAME'] ?? '') . "\n" .
        '[B]Подразделение:[/B] ' . prNotifyTextValue($request['DEPARTMENT_NAME'] ?? '') . "\n" .
        '[B]Инициатор:[/B] ' . prNotifyTextValue($request['INITIATOR_NAME'] ?? '') . ((string)($request['INITIATOR_POSITION'] ?? '') !== '' ? ' — ' . (string)$request['INITIATOR_POSITION'] : '') . "\n" .
        '[B]Тип заявки:[/B] ' . prNotifyTextValue($requestType) . "\n" .
        '[B]Сумма:[/B] ' . prNotifyMoney($request['TOTAL_AMOUNT'] ?? 0, $currency) . "\n\n" .
        '[B]Состав закупки:[/B]' . "\n" .
        prNotifyCompactItems($items, 5, $currency) . "\n\n" .
        '[URL=' . $approvalLink . ']Перейти к согласованию[/URL]' . "\n" .
        '[URL=' . $requestLink . ']Открыть карточку заявки[/URL]';

    return [
        'request_id' => $requestId,
        'task_id' => $taskId,
        'title' => $title,
        'plain' => $plain,
        'bbcode' => $html,
        'link' => $approvalLink,
        'request_link' => $requestLink,
        'tag' => 'purchase_request_task_' . ($taskId > 0 ? $taskId : $requestId),
        'sub_tag' => 'PURCHASE_REQUEST|' . $requestId . '|TASK|' . $taskId . '|APP|' . PR_NOTIFY_MARKETPLACE_APP_ID,
    ];
}

function prNotifyRestSystem(int $toUserId, array $payload): bool
{
    if (!function_exists('prNormalizeAuthPayload') || !function_exists('prReadAuthPayload') || !function_exists('prCallRest')) {
        return false;
    }

    $auth = prNormalizeAuthPayload(prReadAuthPayload());
    if (($auth['AUTH_ID'] ?? '') === '' || ($auth['SERVER_ENDPOINT'] ?? '') === '') {
        return false;
    }

    $result = prCallRest('im.notify.system.add', [
        'USER_ID' => $toUserId,
        'MESSAGE' => $payload['bbcode'],
        'MESSAGE_OUT' => $payload['plain'],
        'TAG' => $payload['tag'],
        'SUB_TAG' => $payload['sub_tag'],
    ], $auth);

    prLog('notify', [
        'channel' => 'rest_system_notify',
        'app_id' => PR_NOTIFY_MARKETPLACE_APP_ID,
        'to' => $toUserId,
        'request_id' => $payload['request_id'],
        'task_id' => $payload['task_id'],
        'result' => $result,
    ]);

    return !empty($result['ok']) && !empty($result['result']);
}

function prNotifyImMessage(int $toUserId, array $payload): bool
{
    if (!class_exists('CIMMessenger')) {
        return false;
    }

    $result = CIMMessenger::Add([
        'FROM_USER_ID' => PR_NOTIFY_FROM_USER_ID,
        'TO_USER_ID' => $toUserId,
        'MESSAGE' => $payload['bbcode'],
    ]);

    prLog('notify', [
        'channel' => 'im_message',
        'to' => $toUserId,
        'request_id' => $payload['request_id'],
        'task_id' => $payload['task_id'],
        'result' => $result,
    ]);

    return (bool)$result;
}

function prNotifySystem(int $toUserId, array $payload): bool
{
    if (!class_exists('CIMNotify')) {
        return false;
    }

    $notifyType = defined('IM_NOTIFY_SYSTEM') ? IM_NOTIFY_SYSTEM : 2;
    $tag = (string)($payload['tag'] ?? ('purchase_request_task_' . (int)$payload['task_id']));
    if ((int)$payload['task_id'] <= 0) {
        $tag = 'purchase_request_' . (int)$payload['request_id'] . '_' . md5($payload['title']);
    }

    $result = CIMNotify::Add([
        'TO_USER_ID' => $toUserId,
        'FROM_USER_ID' => PR_NOTIFY_FROM_USER_ID,
        'NOTIFY_TYPE' => $notifyType,
        'NOTIFY_MODULE' => PR_APP_CODE,
        'NOTIFY_EVENT' => 'task_waiting_decision',
        'NOTIFY_TAG' => $tag,
        'NOTIFY_MESSAGE' => $payload['bbcode'],
        'NOTIFY_MESSAGE_OUT' => $payload['plain'],
    ]);

    prLog('notify', [
        'channel' => 'system_notify',
        'to' => $toUserId,
        'request_id' => $payload['request_id'],
        'task_id' => $payload['task_id'],
        'result' => $result,
    ]);

    return (bool)$result;
}

function prNotifyUser(int $toUserId, array $request, array $step, int $taskId = 0): bool
{
    if ($toUserId <= 0) {
        return false;
    }

    $payload = prNotifyBuildPayload($request, $step, $taskId);
    $sent = false;

    try {
        $sent = prNotifyRestSystem($toUserId, $payload) || $sent;
    } catch (Throwable $e) {
        prLog('notify', [
            'event' => 'rest_system_notify_exception',
            'to' => $toUserId,
            'request_id' => $payload['request_id'],
            'task_id' => $payload['task_id'],
            'message' => $e->getMessage(),
        ]);
    }

    $imIncluded = false;
    try {
        $imIncluded = class_exists('\Bitrix\Main\Loader') && \Bitrix\Main\Loader::includeModule('im');
    } catch (Throwable $e) {
        prLog('notify', [
            'event' => 'im_loader_exception',
            'to' => $toUserId,
            'request_id' => $payload['request_id'],
            'task_id' => $payload['task_id'],
            'message' => $e->getMessage(),
        ]);
    }

    if ($imIncluded) {
        try {
            $sent = prNotifySystem($toUserId, $payload) || $sent;
        } catch (Throwable $e) {
            prLog('notify', [
                'event' => 'system_notify_exception',
                'to' => $toUserId,
                'request_id' => $payload['request_id'],
                'task_id' => $payload['task_id'],
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $sent = prNotifyImMessage($toUserId, $payload) || $sent;
        } catch (Throwable $e) {
            prLog('notify', [
                'event' => 'im_message_exception',
                'to' => $toUserId,
                'request_id' => $payload['request_id'],
                'task_id' => $payload['task_id'],
                'message' => $e->getMessage(),
            ]);
        }
    }

    if (!$sent) {
        prLog('notify', [
            'event' => 'im_unavailable',
            'to' => $toUserId,
            'request_id' => $payload['request_id'],
            'task_id' => $payload['task_id'],
            'message' => $payload['plain'],
        ]);
    }

    return $sent;
}
