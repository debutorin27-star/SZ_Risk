<?php

require_once __DIR__ . '/storage.php';

function prDocumentH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prDocumentMoney($value, string $currency = 'RUB'): string
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
}

function prRenderRegisteredDocumentHtml(array $request): string
{
    $items = array_values($request['ITEMS'] ?? []);
    $route = array_values($request['ROUTE'] ?? []);
    $timeline = array_values($request['TIMELINE'] ?? []);
    $currency = (string)($request['CURRENCY'] ?? PR_DEFAULT_CURRENCY);

    $rows = '';
    foreach ($items as $index => $item) {
        $qty = (float)($item['QUANTITY'] ?? 0);
        $price = (float)($item['ESTIMATED_PRICE'] ?? 0);
        $rows .= '<tr>'
            . '<td>' . ($index + 1) . '</td>'
            . '<td>' . prDocumentH($item['NAME'] ?? '') . '</td>'
            . '<td>' . prDocumentH(prItemCategories()[$item['CATEGORY'] ?? ''] ?? ($item['CATEGORY'] ?? '')) . '</td>'
            . '<td>' . prDocumentH($qty) . '</td>'
            . '<td>' . prDocumentH(prUnits()[$item['UNIT'] ?? ''] ?? ($item['UNIT'] ?? '')) . '</td>'
            . '<td>' . prDocumentH(prDocumentMoney($price, $currency)) . '</td>'
            . '<td>' . prDocumentH(prDocumentMoney($qty * $price, $currency)) . '</td>'
            . '<td>' . prDocumentH($item['JUSTIFICATION'] ?? '') . '</td>'
            . '</tr>';
    }

    $routeRows = '';
    foreach ($route as $index => $step) {
        $assignees = [];
        foreach (array_values($step['assignees'] ?? []) as $assignee) {
            $name = trim((string)($assignee['name'] ?? ''));
            $position = trim((string)($assignee['position'] ?? ''));
            $assignees[] = trim($name . ($position !== '' ? ' — ' . $position : ''));
        }
        $routeRows .= '<tr>'
            . '<td>' . ($index + 1) . '</td>'
            . '<td>' . prDocumentH($step['title'] ?? $step['code'] ?? '') . '</td>'
            . '<td>' . prDocumentH(prRoleLabels()[$step['role'] ?? ''] ?? ($step['role'] ?? '')) . '</td>'
            . '<td>' . prDocumentH(implode('; ', array_filter($assignees))) . '</td>'
            . '</tr>';
    }

    $timelineRows = '';
    foreach ($timeline as $event) {
        $timelineRows .= '<tr>'
            . '<td>' . prDocumentH($event['time'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['title'] ?? '') . '</td>'
            . '<td>' . prDocumentH(prRoleLabels()[$event['role'] ?? ''] ?? ($event['role'] ?? '')) . '</td>'
            . '<td>' . prDocumentH($event['user_name'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['user_position'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['status'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['comment'] ?? '') . '</td>'
            . '</tr>';
    }

    return '<!doctype html><html><head><meta charset="UTF-8"><style>
        body{font-family:DejaVu Sans,Arial,sans-serif;color:#111827;font-size:12px}
        h1{font-size:20px;margin:0 0 12px}
        h2{font-size:15px;margin:18px 0 8px}
        table{width:100%;border-collapse:collapse;margin-top:6px}
        th,td{border:1px solid #cbd5e1;padding:6px;text-align:left;vertical-align:top}
        th{background:#f1f5f9}
        .meta{display:grid;grid-template-columns:180px 1fr;gap:4px 12px;margin-bottom:10px}
        .label{font-weight:700;color:#475569}
    </style></head><body>'
        . '<h1>Заявка на закупку #' . prDocumentH($request['ID'] ?? '') . '</h1>'
        . '<div class="meta">'
        . '<div class="label">Регистрационный номер</div><div>' . prDocumentH($request['REG_NUMBER'] ?? '') . '</div>'
        . '<div class="label">Дата регистрации</div><div>' . prDocumentH($request['REG_DATE'] ?? '') . '</div>'
        . '<div class="label">Компания</div><div>' . prDocumentH($request['COMPANY_NAME'] ?? '') . '</div>'
        . '<div class="label">Площадка</div><div>' . prDocumentH($request['SITE_NAME'] ?? '') . '</div>'
        . '<div class="label">Подразделение</div><div>' . prDocumentH($request['DEPARTMENT_NAME'] ?? '') . '</div>'
        . '<div class="label">Инициатор</div><div>' . prDocumentH($request['INITIATOR_NAME'] ?? '') . '</div>'
        . '<div class="label">Должность</div><div>' . prDocumentH($request['INITIATOR_POSITION'] ?? '') . '</div>'
        . '<div class="label">Место обращения</div><div>' . prDocumentH($request['PLACE_TEXT'] ?? '') . '</div>'
        . '<div class="label">Тип заявки</div><div>' . prDocumentH(prRequestTypes()[$request['REQUEST_TYPE'] ?? ''] ?? ($request['REQUEST_TYPE'] ?? '')) . '</div>'
        . '<div class="label">Сумма</div><div>' . prDocumentH(prDocumentMoney($request['TOTAL_AMOUNT'] ?? 0, $currency)) . '</div>'
        . '<div class="label">Обоснование</div><div>' . nl2br(prDocumentH($request['JUSTIFICATION'] ?? '')) . '</div>'
        . '<div class="label">Примечание</div><div>' . nl2br(prDocumentH($request['COMMENT_TEXT'] ?? '')) . '</div>'
        . '</div>'
        . '<h2>Маршрут согласования</h2>'
        . '<table><thead><tr><th>#</th><th>Этап</th><th>Роль</th><th>Согласующий и должность</th></tr></thead><tbody>'
        . ($routeRows !== '' ? $routeRows : '<tr><td colspan="4">Маршрут отсутствует</td></tr>')
        . '</tbody></table>'
        . '<h2>Позиции заявки</h2>'
        . '<table><thead><tr><th>#</th><th>Наименование</th><th>Вид</th><th>Кол-во</th><th>Ед.</th><th>Цена</th><th>Сумма</th><th>Комментарий</th></tr></thead><tbody>'
        . ($rows !== '' ? $rows : '<tr><td colspan="8">Нет строк</td></tr>')
        . '</tbody></table>'
        . '<h2>История маршрута</h2>'
        . '<table><thead><tr><th>Дата</th><th>Событие</th><th>Роль</th><th>Согласующий</th><th>Должность</th><th>Статус</th><th>Комментарий</th></tr></thead><tbody>'
        . ($timelineRows !== '' ? $timelineRows : '<tr><td colspan="7">История отсутствует</td></tr>')
        . '</tbody></table>'
        . '</body></html>';
}

function prGenerateRegisteredDocument(int $requestId, int $actorUserId): ?int
{
    prEnsureTables();
    if (!class_exists('CFile')) {
        prAudit($actorUserId, 'registered_document_skipped', 'request', $requestId, ['reason' => 'CFile unavailable']);
        return null;
    }

    $request = prGetRequest($requestId);
    if (!$request) {
        return null;
    }

    $html = prRenderRegisteredDocumentHtml($request);
    $isPdf = class_exists('\\Dompdf\\Dompdf');
    $extension = $isPdf ? 'pdf' : 'html';
    $mime = $isPdf ? 'application/pdf' : 'text/html';
    $content = $html;

    if ($isPdf) {
        $dompdfClass = '\\Dompdf\\Dompdf';
        $dompdf = new $dompdfClass(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $content = (string)$dompdf->output();
    }

    $tmp = tempnam(sys_get_temp_dir(), 'pr_document_');
    if ($tmp === false) {
        prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'tempnam failed']);
        return null;
    }
    file_put_contents($tmp, $content);
    $contentSize = filesize($tmp) ?: strlen($content);

    $fileName = 'purchase_request_' . $requestId . '_registered.' . $extension;
    $fileId = CFile::SaveFile([
        'name' => $fileName,
        'type' => $mime,
        'tmp_name' => $tmp,
        'error' => 0,
        'size' => $contentSize,
    ], 'purchase_requests');
    @unlink($tmp);

    if (!$fileId) {
        prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'CFile::SaveFile failed']);
        return null;
    }

    prDbUpdate('b_pr_requests', [
        'GENERATED_DOCUMENT_FILE_ID' => (int)$fileId,
        'UPDATED_AT' => prNow(),
    ], 'ID = ' . $requestId);

    prDbInsert('b_pr_attachments', [
        'REQUEST_ID' => $requestId,
        'ITEM_ID' => null,
        'VERSION' => (int)($request['CURRENT_VERSION'] ?? 0),
        'FILE_ID' => (int)$fileId,
        'ORIGINAL_NAME' => $fileName,
        'FILE_SIZE' => $contentSize,
        'MIME_TYPE' => $mime,
        'AUTHOR_ID' => $actorUserId,
        'CREATED_AT' => prNow(),
    ]);

    prAudit($actorUserId, 'registered_document_generated', 'request', $requestId, [
        'file_id' => (int)$fileId,
        'format' => $extension,
    ]);

    return (int)$fileId;
}
