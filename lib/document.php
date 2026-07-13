<?php

require_once __DIR__ . '/storage.php';

function prDocumentH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prDocumentMoney($value, string $currency = 'RUB'): string
{
    $number = (float)$value;
    $decimals = abs($number - round($number)) > 0.00001 ? 2 : 0;
    return number_format($number, $decimals, ',', ' ') . ' ' . $currency;
}

function prDocumentQty($value): string
{
    return rtrim(rtrim(number_format((float)$value, 4, ',', ' '), '0'), ',');
}

function prDocumentDate($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('d.m.Y', $time) : $value;
}

function prDocumentDateTime($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('d.m.Y H:i', $time) : $value;
}

function prDocumentSubject(array $request): string
{
    $type = (string)($request['REQUEST_TYPE'] ?? '');
    $subjects = [
        'goods' => 'товар',
        'work' => 'работу',
        'service' => 'услугу',
        'mixed' => 'закупку',
        'raw_materials' => 'сырье',
        'computers' => 'компьютеры и орг технику',
        'stationery' => 'канцелярию',
    ];

    return $subjects[$type] ?? (prRequestTypes()[$type] ?? 'закупку');
}

function prDocumentStatusLabel(string $status): string
{
    $labels = [
        'OPEN' => 'Открыто',
        'DONE' => 'Выполнено',
        'approve' => 'Согласовано',
        'reject' => 'Отклонено',
        'revision' => 'Возвращено на доработку',
    ];

    return $labels[$status] ?? (prStatusLabels()[$status] ?? $status);
}

function prDocumentEventTitle(array $event): string
{
    if ((string)($event['type'] ?? '') === 'decision') {
        return 'Решение';
    }

    return (string)($event['title'] ?? '');
}

function prDocumentApprovalDate(array $request): string
{
    if ((string)($request['STATUS'] ?? '') === 'DONE') {
        return prDocumentDate($request['UPDATED_AT'] ?? '');
    }

    $latest = '';
    foreach (array_values($request['TIMELINE'] ?? []) as $event) {
        if ((string)($event['type'] ?? '') === 'decision' && (string)($event['time'] ?? '') > $latest) {
            $latest = (string)$event['time'];
        }
    }
    return prDocumentDate($latest);
}

function prDocumentActiveItems(array $request): array
{
    return array_values(array_filter(array_values($request['ITEMS'] ?? []), static function (array $item): bool {
        return (string)($item['FINAL_STATUS'] ?? 'ACTIVE') === 'ACTIVE';
    }));
}

function prDocumentEnsurePdfEngine(): bool
{
    if (class_exists('\\Dompdf\\Dompdf')) {
        return true;
    }

    static $checked = false;
    if (!$checked) {
        $checked = true;
        $autoloadFiles = [
            __DIR__ . '/../vendor/autoload.php',
            rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/local/purchase_requests/vendor/autoload.php',
            rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/local/vendor/autoload.php',
        ];
        foreach ($autoloadFiles as $autoloadFile) {
            if ($autoloadFile !== '' && is_file($autoloadFile)) {
                require_once $autoloadFile;
                break;
            }
        }
    }

    return class_exists('\\Dompdf\\Dompdf');
}

function prRenderRegisteredDocumentHtml(array $request): string
{
    $items = prDocumentActiveItems($request);
    $timeline = array_values($request['TIMELINE'] ?? []);
    $attachments = array_values($request['ATTACHMENTS'] ?? []);
    $currency = (string)($request['CURRENCY'] ?? PR_DEFAULT_CURRENCY);
    $subject = prDocumentSubject($request);

    $rows = '';
    foreach ($items as $index => $item) {
        $qty = (float)($item['QUANTITY'] ?? 0);
        $price = (float)($item['ESTIMATED_PRICE'] ?? 0);
        $rows .= '<tr>'
            . '<td class="center">' . ($index + 1) . '</td>'
            . '<td>' . prDocumentH($item['NAME'] ?? '') . '</td>'
            . '<td>' . prDocumentH(prItemCategories()[$item['CATEGORY'] ?? ''] ?? ($item['CATEGORY'] ?? '')) . '</td>'
            . '<td>' . prDocumentH($item['EQUIPMENT_TEXT'] ?? '') . '</td>'
            . '<td class="right">' . prDocumentH(prDocumentQty($qty)) . '</td>'
            . '<td>' . prDocumentH(prUnits()[$item['UNIT'] ?? ''] ?? ($item['UNIT'] ?? '')) . '</td>'
            . '<td class="right">' . prDocumentH(prDocumentMoney($price, $currency)) . '</td>'
            . '<td class="right">' . prDocumentH(prDocumentMoney($qty * $price, $currency)) . '</td>'
            . '<td>' . prDocumentH($item['JUSTIFICATION'] ?? '') . '</td>'
            . '</tr>';
    }

    $timelineRows = '';
    foreach ($timeline as $event) {
        if ((string)($event['type'] ?? '') !== 'decision') {
            continue;
        }
        $timelineRows .= '<tr>'
            . '<td>' . prDocumentH(prDocumentDateTime($event['time'] ?? '')) . '</td>'
            . '<td>' . prDocumentH(prDocumentEventTitle($event)) . '</td>'
            . '<td>' . prDocumentH(prRoleLabels()[$event['role'] ?? ''] ?? ($event['role'] ?? '')) . '</td>'
            . '<td>' . prDocumentH(prDocumentStatusLabel((string)($event['status'] ?? ''))) . '</td>'
            . '<td>' . prDocumentH($event['user_name'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['user_department'] ?? '') . '</td>'
            . '<td>' . prDocumentH($event['user_position'] ?? '') . '</td>'
            . '<td class="center">' . prDocumentH($event['user_id'] ?? '') . '</td>'
            . '<td>' . nl2br(prDocumentH($event['comment'] ?? '')) . '</td>'
            . '</tr>';
    }

    $attachmentRows = '';
    foreach ($attachments as $attachment) {
        if (prDocumentAttachmentIsGenerated($request, $attachment)) {
            continue;
        }
        $attachmentRows .= '<tr>'
            . '<td>' . prDocumentH($attachment['ORIGINAL_NAME'] ?? '') . '</td>'
            . '<td class="right">' . prDocumentH(number_format(max(0, (int)($attachment['FILE_SIZE'] ?? 0)) / 1024, 0, ',', ' ')) . ' КБ</td>'
            . '</tr>';
    }

    return '<!doctype html><html><head><meta charset="UTF-8"><style>
        @page{margin:18mm 12mm}
        body{font-family:DejaVu Sans,Arial,sans-serif;color:#111827;font-size:11px;line-height:1.35}
        h1{font-size:18px;text-align:center;margin:0 0 8px;font-weight:700}
        h2{font-size:13px;text-align:center;margin:18px 0 8px}
        table{width:100%;border-collapse:collapse;margin-top:6px}
        th,td{border:1px solid #9ca3af;padding:5px;text-align:left;vertical-align:top}
        th{background:#f1f5f9;font-weight:700;text-align:center}
        .center{text-align:center}.right{text-align:right}
        .reg{text-align:center;margin:0 0 16px}
        .meta-table{margin:10px 0 14px}
        .meta-table td{border:0;padding:0;width:50%}
        .meta-table td:first-child{padding-right:12px}
        .meta-table td:last-child{padding-left:12px;text-align:right}
        .meta-line{margin:3px 0}
        .label{font-weight:700;color:#374151}
        .comment{margin-top:10px}
    </style></head><body>'
        . '<h1>Служебная записка на ' . prDocumentH($subject) . '</h1>'
        . '<div class="reg">'
        . '<div><span class="label">Регистрационный номер:</span> ' . prDocumentH($request['REG_NUMBER'] ?? '') . '</div>'
        . '<div><span class="label">Дата регистрации:</span> ' . prDocumentH(prDocumentDate($request['REG_DATE'] ?? '')) . '</div>'
        . '</div>'
        . '<table class="meta-table"><tr><td>'
        . '<div class="meta-line"><span class="label">Инициатор:</span> ' . prDocumentH($request['INITIATOR_NAME'] ?? '') . '</div>'
        . '<div class="meta-line"><span class="label">Отдел:</span> ' . prDocumentH($request['DEPARTMENT_NAME'] ?? '') . '</div>'
        . '<div class="meta-line"><span class="label">Должность:</span> ' . prDocumentH($request['INITIATOR_POSITION'] ?? '') . '</div>'
        . '<div class="meta-line"><span class="label">ID пользователя:</span> ' . prDocumentH($request['INITIATOR_ID'] ?? '') . '</div>'
        . '</td><td>'
        . '<div class="meta-line"><span class="label">Площадка:</span> ' . prDocumentH($request['SITE_NAME'] ?? '') . '</div>'
        . '<div class="meta-line"><span class="label">Дата утверждения:</span> ' . prDocumentH(prDocumentApprovalDate($request)) . '</div>'
        . '<div class="meta-line"><span class="label">Компания:</span> ' . prDocumentH($request['COMPANY_NAME'] ?? '') . '</div>'
        . '</td></tr></table>'
        . '<h2>Таблица заявки</h2>'
        . '<table><thead><tr><th>#</th><th>Наименование</th><th>Вид</th><th>Место установки</th><th>Кол-во</th><th>Ед.</th><th>Предполагаемая цена за ед.</th><th>Сумма</th><th>Комментарий</th></tr></thead><tbody>'
        . ($rows !== '' ? $rows : '<tr><td colspan="9" class="center">Нет строк</td></tr>')
        . '</tbody></table>'
        . '<div class="comment"><span class="label">Обоснование:</span><br>' . nl2br(prDocumentH($request['JUSTIFICATION'] ?? '')) . '</div>'
        . '<div class="comment"><span class="label">Примечание:</span><br>' . nl2br(prDocumentH($request['COMMENT_TEXT'] ?? '')) . '</div>'
        . '<h2>Маршрут согласования</h2>'
        . '<table><thead><tr><th>Дата и время</th><th>Действие</th><th>Роль</th><th>Статус</th><th>ФИО</th><th>Отдел</th><th>Должность</th><th>ID</th><th>Комментарий</th></tr></thead><tbody>'
        . ($timelineRows !== '' ? $timelineRows : '<tr><td colspan="9" class="center">История отсутствует</td></tr>')
        . '</tbody></table>'
        . ($attachmentRows !== '' ? '<h2>Приложенные файлы</h2><table><thead><tr><th>Файл</th><th>Размер</th></tr></thead><tbody>' . $attachmentRows . '</tbody></table>' : '')
        . '</body></html>';
}

function prDocumentAttachmentIsGenerated(array $request, array $attachment): bool
{
    $fileId = (int)($attachment['FILE_ID'] ?? 0);
    if ($fileId > 0 && $fileId === (int)($request['GENERATED_DOCUMENT_FILE_ID'] ?? 0)) {
        return true;
    }

    $name = (string)($attachment['ORIGINAL_NAME'] ?? '');
    return preg_match('/^purchase_request_' . (int)($request['ID'] ?? 0) . '_registered\./', $name) === 1;
}

function prDocumentCommandExists(string $command): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }

    $result = @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
    return trim((string)$result) !== '';
}

function prDocumentTempDir(): string
{
    $dir = sys_get_temp_dir() . '/purchase_request_document_' . uniqid('', true);
    @mkdir($dir, 0775, true);
    return $dir;
}

function prDocumentRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            prDocumentRemoveDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function prDocumentLocalFilePath(int $fileId): string
{
    if ($fileId <= 0 || !class_exists('CFile')) {
        return '';
    }

    $file = CFile::GetFileArray($fileId);
    if (!is_array($file)) {
        return '';
    }

    $src = (string)($file['SRC'] ?? '');
    if ($src !== '') {
        $path = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $src;
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function prDocumentRenderPdfToFile(string $html, string $targetFile): bool
{
    if (!prDocumentEnsurePdfEngine()) {
        return false;
    }

    $dompdfClass = '\\Dompdf\\Dompdf';
    $dompdf = new $dompdfClass(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return file_put_contents($targetFile, (string)$dompdf->output()) !== false;
}

function prDocumentImageToPdf(string $imageFile, string $targetFile): bool
{
    $data = @file_get_contents($imageFile);
    if ($data === false) {
        return false;
    }
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type($imageFile) : 'image/jpeg';
    $html = '<!doctype html><html><head><meta charset="UTF-8"><style>@page{margin:10mm}body{text-align:center;margin:0}img{max-width:100%;max-height:260mm}</style></head><body><img src="data:' . prDocumentH($mime) . ';base64,' . base64_encode($data) . '"></body></html>';

    return prDocumentRenderPdfToFile($html, $targetFile);
}

function prDocumentTextToPdf(string $textFile, string $targetFile): bool
{
    $data = @file_get_contents($textFile);
    if ($data === false) {
        return false;
    }

    if (strlen($data) > 1000000) {
        $data = substr($data, 0, 1000000) . "\n\n... файл обрезан при формировании PDF-приложения";
    }

    $html = '<!doctype html><html><head><meta charset="UTF-8"><style>@page{margin:14mm}body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;line-height:1.35}pre{white-space:pre-wrap;word-break:break-word}</style></head><body><pre>'
        . prDocumentH($data)
        . '</pre></body></html>';

    return prDocumentRenderPdfToFile($html, $targetFile);
}

function prDocumentOfficeToPdf(string $file, string $targetDir): string
{
    if (!function_exists('shell_exec')) {
        return '';
    }

    $command = '';
    foreach (['libreoffice', 'soffice'] as $candidate) {
        if (prDocumentCommandExists($candidate)) {
            $command = $candidate;
            break;
        }
    }
    if ($command === '') {
        return '';
    }

    @shell_exec($command . ' --headless --convert-to pdf --outdir ' . escapeshellarg($targetDir) . ' ' . escapeshellarg($file) . ' 2>/dev/null');
    $candidate = $targetDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.pdf';

    return is_file($candidate) ? $candidate : '';
}

function prDocumentAttachmentPdfFiles(array $request, string $workDir): array
{
    $pdfFiles = [];
    $index = 1;
    foreach (array_values($request['ATTACHMENTS'] ?? []) as $attachment) {
        if (prDocumentAttachmentIsGenerated($request, $attachment)) {
            continue;
        }

        $filePath = prDocumentLocalFilePath((int)($attachment['FILE_ID'] ?? 0));
        if ($filePath === '') {
            continue;
        }

        $extension = prFileExtension((string)($attachment['ORIGINAL_NAME'] ?? $filePath));
        if ($extension === 'pdf') {
            $pdfFiles[] = $filePath;
            continue;
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $target = $workDir . '/attachment_' . $index++ . '.pdf';
            if (prDocumentImageToPdf($filePath, $target)) {
                $pdfFiles[] = $target;
            }
            continue;
        }

        if (in_array($extension, ['txt', 'csv'], true)) {
            $target = $workDir . '/attachment_' . $index++ . '.pdf';
            if (prDocumentTextToPdf($filePath, $target)) {
                $pdfFiles[] = $target;
            }
            continue;
        }

        if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx'], true)) {
            $converted = prDocumentOfficeToPdf($filePath, $workDir);
            if ($converted !== '') {
                $pdfFiles[] = $converted;
            }
        }
    }

    return $pdfFiles;
}

function prDocumentMergePdfFiles(array $files, string $targetFile): bool
{
    $files = array_values(array_filter($files, 'is_file'));
    if (!$files) {
        return false;
    }
    if (count($files) === 1) {
        return copy($files[0], $targetFile);
    }
    if (!prDocumentCommandExists('pdfunite') || !function_exists('shell_exec')) {
        return false;
    }

    $command = 'pdfunite';
    foreach ($files as $file) {
        $command .= ' ' . escapeshellarg($file);
    }
    $command .= ' ' . escapeshellarg($targetFile) . ' 2>/dev/null';
    @shell_exec($command);

    return is_file($targetFile) && filesize($targetFile) > 0;
}

function prGenerateRegisteredDocument(int $requestId, int $actorUserId, bool $requirePdf = false): ?int
{
    prEnsureTables();
    if (!class_exists('CFile')) {
        prAudit($actorUserId, 'registered_document_skipped', 'request', $requestId, ['reason' => 'CFile unavailable']);
        if ($requirePdf) {
            throw new RuntimeException('Не удалось сформировать PDF: файловый модуль Bitrix недоступен.');
        }
        return null;
    }

    $request = prGetRequest($requestId);
    if (!$request) {
        return null;
    }

    $html = prRenderRegisteredDocumentHtml($request);
    $workDir = prDocumentTempDir();
    $mainPdf = $workDir . '/main.pdf';
    $resultFile = $workDir . '/result.pdf';
    $extension = 'html';
    $mime = 'text/html';

    if (prDocumentRenderPdfToFile($html, $mainPdf)) {
        $extension = 'pdf';
        $mime = 'application/pdf';
        $attachmentPdfFiles = prDocumentAttachmentPdfFiles($request, $workDir);
        $pdfFiles = array_merge([$mainPdf], $attachmentPdfFiles);
        if (!prDocumentMergePdfFiles($pdfFiles, $resultFile)) {
            if ($requirePdf && count($attachmentPdfFiles) > 0) {
                prDocumentRemoveDir($workDir);
                prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'PDF merge failed']);
                throw new RuntimeException('Не удалось скрепить PDF документа с приложенными файлами.');
            }
            $resultFile = $mainPdf;
        }
    } else {
        if ($requirePdf) {
            prDocumentRemoveDir($workDir);
            prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'PDF engine unavailable']);
            throw new RuntimeException('Не удалось сформировать PDF. Проверьте dompdf и PHP-расширения.');
        }
        $resultFile = $workDir . '/result.html';
        file_put_contents($resultFile, $html);
    }

    if (!is_file($resultFile)) {
        prDocumentRemoveDir($workDir);
        prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'document file was not created']);
        if ($requirePdf) {
            throw new RuntimeException('Не удалось сформировать PDF: файл документа не создан.');
        }
        return null;
    }

    $contentSize = filesize($resultFile) ?: 0;
    $fileName = 'purchase_request_' . $requestId . '_registered.' . $extension;
    $fileId = CFile::SaveFile([
        'name' => $fileName,
        'type' => $mime,
        'tmp_name' => $resultFile,
        'error' => 0,
        'size' => $contentSize,
    ], 'purchase_requests');

    if (!$fileId) {
        prDocumentRemoveDir($workDir);
        prAudit($actorUserId, 'registered_document_failed', 'request', $requestId, ['reason' => 'CFile::SaveFile failed']);
        if ($requirePdf) {
            throw new RuntimeException('Не удалось сохранить PDF в Bitrix.');
        }
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

    prDocumentRemoveDir($workDir);
    prAudit($actorUserId, 'registered_document_generated', 'request', $requestId, [
        'file_id' => (int)$fileId,
        'format' => $extension,
    ]);

    return (int)$fileId;
}
