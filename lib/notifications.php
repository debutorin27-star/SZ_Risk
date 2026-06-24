<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../runtime.php';

function prNotifyUser(int $toUserId, int $requestId, string $stepTitle): bool
{
    if ($toUserId <= 0) {
        return false;
    }

    $link = prAbsoluteUrl('index.php', ['request_id' => $requestId]);
    $message = 'Вам пришёл бизнес-процесс: заявка на закупку #' . $requestId .
        ' требует реакции: ' . $stepTitle . '. Открыть: ' . $link;

    try {
        if (class_exists('\Bitrix\Main\Loader') && \Bitrix\Main\Loader::includeModule('im') && class_exists('CIMMessenger')) {
            $result = CIMMessenger::Add([
                'FROM_USER_ID' => PR_NOTIFY_FROM_USER_ID,
                'TO_USER_ID' => $toUserId,
                'MESSAGE' => $message,
            ]);
            prLog('notify', ['to' => $toUserId, 'request_id' => $requestId, 'result' => $result]);
            return (bool)$result;
        }
    } catch (Throwable $e) {
        prLog('notify', ['event' => 'exception', 'message' => $e->getMessage()]);
    }

    prLog('notify', ['event' => 'im_unavailable', 'to' => $toUserId, 'message' => $message]);
    return false;
}
