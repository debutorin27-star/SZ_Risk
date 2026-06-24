<?php

require_once __DIR__ . '/runtime.php';

$appAuthPayload = [
    'DOMAIN' => $_REQUEST['DOMAIN'] ?? '',
    'PROTOCOL' => $_REQUEST['PROTOCOL'] ?? '',
    'LANG' => $_REQUEST['LANG'] ?? '',
    'APP_SID' => $_REQUEST['APP_SID'] ?? '',
    'AUTH_ID' => $_REQUEST['AUTH_ID'] ?? '',
    'AUTH_EXPIRES' => $_REQUEST['AUTH_EXPIRES'] ?? '',
    'REFRESH_ID' => $_REQUEST['REFRESH_ID'] ?? '',
    'SERVER_ENDPOINT' => $_REQUEST['SERVER_ENDPOINT'] ?? '',
    'APPLICATION_TOKEN' => $_REQUEST['APPLICATION_TOKEN'] ?? '',
    'APPLICATION_SCOPE' => $_REQUEST['APPLICATION_SCOPE'] ?? '',
    'member_id' => $_REQUEST['member_id'] ?? '',
    'status' => $_REQUEST['status'] ?? '',
    'PLACEMENT' => $_REQUEST['PLACEMENT'] ?? '',
    'PLACEMENT_OPTIONS' => $_REQUEST['PLACEMENT_OPTIONS'] ?? '',
];

$incomingContext = strtolower((string)($_REQUEST['auth_context'] ?? ''));
$incomingContextFile = prAuthContextDir() . '/' . $incomingContext . '.json';
if ($incomingContext !== '' && preg_match('/^[a-f0-9]{32}$/', $incomingContext) && is_file($incomingContextFile)) {
    $appAuthContext = $incomingContext;
    $existingPayload = json_decode((string)@file_get_contents($incomingContextFile), true);
    if (is_array($existingPayload)) {
        $appAuthPayload = array_merge($existingPayload, array_filter($appAuthPayload, static function ($value) {
            return $value !== '';
        }));
    }
} else {
    $appAuthContext = prCreateAuthContext($appAuthPayload);
}
$appAuthPayload['auth_context'] = $appAuthContext;

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('header_remove')) {
    @header_remove('X-Frame-Options');
    @header_remove('Content-Security-Policy');
    @header_remove('Content-Security-Policy-Report-Only');
}

prLog('index', [
    'event' => 'opened_no_bitrix_core',
    'request_keys' => array_keys($_REQUEST),
    'auth_context' => $appAuthContext,
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= prH(PR_APP_TITLE) ?></title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        :root{--bg:#f4f6f8;--panel:#fff;--line:#d9e0e8;--text:#152033;--muted:#64748b;--accent:#1769e0;--accent-dark:#0f4fb0;--danger:#b42318;--ok:#157347;--warn:#9a6700}
        html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;overflow-x:hidden}
        .shell{max-width:1320px;margin:0 auto;padding:12px}
        .top-status{position:sticky;top:0;z-index:20;margin:-12px -12px 12px;padding:10px 12px;background:#eaf3ff;color:#0f4f8f;border-bottom:1px solid #c6def8;font-size:13px}
        .top-status.error{background:#fff1f0;color:var(--danger);border-bottom-color:#ffd4cf}
        .top-status.success{background:#eaf7ef;color:var(--ok);border-bottom-color:#bfe6cc}
        .toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
        h1{font-size:22px;line-height:1.25;margin:0}
        .tabs{display:flex;gap:6px;flex-wrap:wrap}
        .tab{border:1px solid var(--line);background:#fff;color:#20304a;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer}
        .tab.active{border-color:var(--accent);background:var(--accent);color:#fff}
        .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;box-shadow:0 3px 12px rgba(15,23,42,.04)}
        .view{display:none}.view.active{display:block}
        .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
        .col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}
        label{display:block;font-weight:700;font-size:13px;margin-bottom:5px}
        input,select,textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:var(--text);font-size:15px;padding:9px}
        textarea{min-height:86px;resize:vertical}
        button{border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;font-size:14px;padding:10px 13px;cursor:pointer}
        button:hover{background:var(--accent-dark)}
        button.secondary{background:#475569}button.secondary:hover{background:#334155}
        button.danger{background:var(--danger)}button.danger:hover{background:#861d13}
        button.light{border:1px solid var(--line);background:#fff;color:#20304a}button.light:hover{background:#f8fafc}
        button[disabled]{opacity:.55;cursor:not-allowed}
        .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}
        .notice{border:1px solid #c6def8;background:#eaf3ff;color:#0f4f8f;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:14px}
        .notice.error{border-color:#ffd4cf;background:#fff1f0;color:var(--danger)}
        .notice.success{border-color:#bfe6cc;background:#eaf7ef;color:var(--ok)}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px;background:#fff}
        table{width:100%;border-collapse:collapse;min-width:920px}
        th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:14px}
        th{background:#f8fafc;color:#475569;font-weight:800}
        tr:last-child td{border-bottom:0}
        .muted{color:var(--muted);font-size:13px}
        .badge{display:inline-flex;align-items:center;border-radius:999px;background:#eef2f7;color:#334155;padding:4px 8px;font-size:12px;font-weight:800}
        .badge.open{background:#fff7ed;color:#9a3412}.badge.done{background:#ecfdf3;color:#166534}
        .subhead{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:2px 0 10px}
        .subhead h2{font-size:18px;margin:0}
        .item-editor{margin-top:12px}
        .item-row{display:grid;grid-template-columns:140px minmax(220px,2fr) 120px 120px 150px minmax(240px,2fr) 44px;gap:8px;align-items:start;margin-bottom:10px;border:1px solid var(--line);border-radius:8px;padding:10px;background:#fff}
        .field label{font-size:12px;margin-bottom:4px;color:#475569}
        .field-hint{margin-top:3px;color:var(--muted);font-size:12px;line-height:1.3}
        .attachments{margin-top:14px;border:1px solid var(--line);border-radius:8px;padding:12px;background:#f8fafc}
        .file-list{margin:8px 0 0;padding:0;list-style:none;display:grid;gap:6px}
        .file-list li{display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid var(--line);border-radius:7px;background:#fff;padding:7px 9px;font-size:14px}
        .file-list a{color:var(--accent);text-decoration:none}
        .route-preview{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:10px}
        .route-step{border:1px solid var(--line);border-radius:8px;padding:9px;background:#f8fafc}
        .task-list{display:grid;gap:10px}
        .task{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff}
        .task-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px}
        .admin-layout{display:grid;grid-template-columns:minmax(320px,440px) 1fr;gap:14px}
        .admin-box{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff}
        .stack{display:grid;gap:10px}
        .json-area{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;min-height:220px}
        @media(max-width:860px){
            .shell{padding:0}.top-status{margin:0}.toolbar{display:block;padding:12px 12px 0}.tabs{margin-top:10px}.panel{border-left:0;border-right:0;border-radius:0;box-shadow:none;padding:12px}
            .grid{display:block}.grid>div{margin-bottom:10px}.admin-layout{display:block}.admin-layout>div{margin-bottom:12px}
            .item-row{grid-template-columns:1fr;gap:8px;border:1px solid var(--line);border-radius:8px;padding:9px;background:#f8fafc}.item-row .wide{grid-column:auto}.item-row button{width:44px}
            .table-wrap{border:0;overflow:visible}table,tbody,tr,td{display:block;width:100%;box-sizing:border-box}table{min-width:0}thead{display:none}
            tr{border:1px solid var(--line);border-radius:8px;margin-bottom:10px;background:#fff}td{border-bottom:0;padding:7px 9px}td::before{content:attr(data-label);display:block;color:var(--muted);font-size:12px;font-weight:800;margin-bottom:2px}
            .task-head{display:block}.actions button{flex:1 1 auto}
        }
    </style>
</head>
<body>
<div class="shell">
    <div id="topStatus" class="top-status">Проверяем авторизацию Bitrix24...</div>
    <div class="toolbar">
        <h1><?= prH(PR_APP_TITLE) ?></h1>
        <div class="tabs">
            <button class="tab active" data-view="requestsView">Мои заявки</button>
            <button class="tab" data-view="tasksView">На согласовании</button>
            <button class="tab" data-view="newView">Новая заявка</button>
            <button class="tab" data-view="adminView">Администрирование</button>
        </div>
    </div>

    <div class="panel">
        <div id="noticeBox"></div>

        <section id="requestsView" class="view active">
            <div class="subhead">
                <h2>Реестр заявок</h2>
                <button type="button" class="light" id="refreshRequests">Обновить</button>
            </div>
            <div id="requestsContent" class="notice">Загружаем...</div>
        </section>

        <section id="tasksView" class="view">
            <div class="subhead">
                <h2>Задания</h2>
                <button type="button" class="light" id="refreshTasks">Обновить</button>
            </div>
            <div id="tasksContent" class="notice">Загружаем...</div>
        </section>

        <section id="newView" class="view">
            <div class="subhead">
                <h2>Карточка заявки</h2>
                <button type="button" class="light" id="resetRequest">Очистить</button>
            </div>
            <form id="requestForm">
                <input type="hidden" id="requestId">
                <div class="grid">
                    <div class="col-4"><label for="companyKey">Компания *</label><select id="companyKey" required></select></div>
                    <div class="col-4"><label for="siteKey">Площадка</label><select id="siteKey"></select></div>
                    <div class="col-4"><label for="requestType">Тип заявки *</label><select id="requestType" required></select></div>
                    <div class="col-4"><label for="departmentName">Подразделение</label><input id="departmentName" type="text"></div>
                    <div class="col-4"><label for="placeText">Место обращения *</label><input id="placeText" type="text" required></div>
                    <div class="col-4"><label for="requiredDate">Желаемый срок</label><input id="requiredDate" type="date"></div>
                    <div class="col-6"><label for="justification">Обоснование *</label><textarea id="justification" required></textarea></div>
                    <div class="col-6"><label for="commentText">Примечание</label><textarea id="commentText"></textarea></div>
                </div>

                <div class="item-editor">
                    <div class="subhead">
                        <h2>Строки заявки</h2>
                        <button type="button" class="light" id="addItem">Добавить строку</button>
                    </div>
                    <div id="itemsEditor"></div>
                </div>

                <div class="attachments">
                    <div class="subhead">
                        <h2>Счета и файлы</h2>
                    </div>
                    <label for="attachmentsInput">Прикрепить счета, КП, документы</label>
                    <input type="file" id="attachmentsInput" multiple accept=".<?= prH(implode(',.', prAllowedFileExtensions())) ?>">
                    <div class="field-hint">Файлы сохраняются в заявке. Разрешены: .<?= prH(implode(', .', prAllowedFileExtensions())) ?></div>
                    <ul id="selectedFilesList" class="file-list"></ul>
                    <div id="existingAttachments"></div>
                </div>

                <div id="routePreview" class="route-preview"></div>
                <div class="actions">
                    <button type="button" class="secondary" id="previewRoute">Показать маршрут</button>
                    <button type="button" class="secondary" id="saveDraft">Сохранить черновик</button>
                    <button type="submit" id="submitRequest">Отправить</button>
                </div>
            </form>
        </section>

        <section id="adminView" class="view">
            <div class="subhead">
                <h2>Администрирование процесса</h2>
                <button type="button" class="light" id="refreshAdmin">Обновить</button>
            </div>
            <div id="adminContent" class="notice">Данные будут загружены после проверки прав.</div>
        </section>
    </div>
</div>

<script>
const PR_APP_DIR = <?= json_encode(prAppDir(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const PR_AUTH_CONTEXT = <?= json_encode($appAuthContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const PR_AUTH_PAYLOAD = <?= json_encode($appAuthPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const initialRequestId = new URLSearchParams(location.search).get('request_id') || '';

let dict = {companies:{}, request_types:{}, item_categories:{}, units:{}, roles:{}, statuses:{}};
let currentItems = [];
let selectedFiles = [];
let adminCache = null;
let apiMode = 'proxy';
let runtimeAuthPayload = {...(PR_AUTH_PAYLOAD || {})};
let bx24AuthPromise = null;

const topStatus = document.getElementById('topStatus');
const noticeBox = document.getElementById('noticeBox');

function apiUrl(path, params = {}) {
    const directBootstrap = path === 'bootstrap.php';
    const useProxy = !directBootstrap && apiMode !== 'direct';
    const url = new URL(PR_APP_DIR + '/api/' + (useProxy ? 'proxy.php' : path), location.origin);
    if (useProxy) url.searchParams.set('target', path);
    url.searchParams.set('auth_context', PR_AUTH_CONTEXT || '');
    if (runtimeAuthPayload && (runtimeAuthPayload.AUTH_ID || runtimeAuthPayload.auth || runtimeAuthPayload.access_token)) {
        url.searchParams.set('app_auth_payload', JSON.stringify(runtimeAuthPayload));
    }
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    return url.toString();
}

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}

function setStatus(text, type = '') {
    topStatus.className = 'top-status ' + type;
    topStatus.textContent = text;
}

function showNotice(text, type = '') {
    noticeBox.innerHTML = text ? `<div class="notice ${type}">${escapeHtml(text)}</div>` : '';
}

async function api(path, options = {}, retriedDirect = false) {
    const response = await fetch(apiUrl(path, options.params || {}), {
        method: options.method || 'GET',
        headers: options.body ? {'Content-Type': 'application/json'} : {},
        body: options.body ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin',
        cache: 'no-store'
    });
    const text = await response.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        const start = text.slice(0, 600).replace(/\s+/g, ' ').trim();
        throw new Error('Сервер вернул некорректный ответ. HTTP ' + response.status + (start ? '. Начало ответа: ' + start : '. Ответ пустой.'));
    }
    if (!data.ok) {
        const message = (data.errors || ['Ошибка запроса']).join('\n');
        if (!retriedDirect && /авториз|auth|session/i.test(message)) {
            const gotAuth = await ensureBx24Auth(true);
            if (gotAuth) return api(path, options, true);
        }
        if (!retriedDirect && apiMode !== 'direct' && /авториз|auth|session/i.test(message)) {
            apiMode = 'direct';
            return api(path, options, true);
        }
        throw new Error(message);
    }
    return data;
}

function mergeRuntimeAuthPayload(auth) {
    if (!auth || typeof auth !== 'object') return false;

    const payload = {
        ...runtimeAuthPayload,
        ...auth,
        AUTH_ID: auth.AUTH_ID || auth.auth || auth.access_token || auth.ACCESS_TOKEN || runtimeAuthPayload.AUTH_ID || '',
        REFRESH_ID: auth.REFRESH_ID || auth.refresh_token || runtimeAuthPayload.REFRESH_ID || '',
        DOMAIN: auth.DOMAIN || auth.domain || runtimeAuthPayload.DOMAIN || '',
        SERVER_ENDPOINT: auth.SERVER_ENDPOINT || auth.server_endpoint || auth.client_endpoint || auth.CLIENT_ENDPOINT || runtimeAuthPayload.SERVER_ENDPOINT || '',
        member_id: auth.member_id || runtimeAuthPayload.member_id || ''
    };

    if (!payload.DOMAIN && payload.SERVER_ENDPOINT) {
        try {
            payload.DOMAIN = new URL(payload.SERVER_ENDPOINT).host;
        } catch (e) {}
    }

    runtimeAuthPayload = payload;
    return Boolean(payload.AUTH_ID);
}

function ensureBx24Auth(force = false) {
    if (!force && mergeRuntimeAuthPayload(runtimeAuthPayload)) return Promise.resolve(true);
    if (bx24AuthPromise && !force) return bx24AuthPromise;

    bx24AuthPromise = new Promise(resolve => {
        if (!window.BX24 || typeof BX24.init !== 'function') {
            resolve(false);
            return;
        }

        let settled = false;
        const finish = auth => {
            if (settled) return;
            settled = true;
            resolve(mergeRuntimeAuthPayload(auth || {}));
        };

        setTimeout(() => finish(null), 3500);

        try {
            BX24.init(function () {
                let auth = {};
                try {
                    auth = typeof BX24.getAuth === 'function' ? (BX24.getAuth() || {}) : {};
                } catch (e) {}

                if (auth && (auth.access_token || auth.AUTH_ID || auth.auth)) {
                    finish(auth);
                    return;
                }

                if (typeof BX24.refreshAuth === 'function') {
                    try {
                        BX24.refreshAuth(function (freshAuth) {
                            finish(freshAuth || auth);
                        });
                        return;
                    } catch (e) {}
                }

                finish(auth);
            });
        } catch (e) {
            finish(null);
        }
    });

    return bx24AuthPromise;
}

async function uploadAttachments(requestId) {
    if (!selectedFiles.length) return null;

    const fd = new FormData();
    fd.append('action', 'upload_attachments');
    fd.append('request_id', String(requestId));
    fd.append('auth_context', PR_AUTH_CONTEXT || '');
    selectedFiles.forEach(file => fd.append('attachments[]', file, file.name));

    const response = await fetch(apiUrl('requests.php'), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        cache: 'no-store'
    });
    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch (e) { throw new Error('Сервер вернул некорректный ответ при загрузке файлов.'); }
    if (!data.ok) throw new Error((data.errors || ['Не удалось загрузить файлы']).join('\n'));

    selectedFiles = [];
    document.getElementById('attachmentsInput').value = '';
    renderSelectedFiles();
    renderExistingAttachments(data.request?.ATTACHMENTS || []);
    return data;
}

function optionHtml(map, selected = '') {
    return Object.entries(map || {}).map(([key, value]) => {
        const label = typeof value === 'object' ? value.name : value;
        return `<option value="${escapeHtml(key)}"${String(key) === String(selected) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');
}

function bindTabs() {
    document.querySelectorAll('.tab').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.view').forEach(x => x.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.dataset.view).classList.add('active');
            if (button.dataset.view === 'requestsView') loadRequests();
            if (button.dataset.view === 'tasksView') loadTasks();
            if (button.dataset.view === 'adminView') loadAdmin();
        });
    });
}

function fillDictionaries() {
    document.getElementById('companyKey').innerHTML = '<option value="">Выберите компанию</option>' + optionHtml(dict.companies);
    const companyKeys = Object.keys(dict.companies || {});
    if (companyKeys.length === 1) {
        document.getElementById('companyKey').value = companyKeys[0];
    }
    document.getElementById('requestType').innerHTML = optionHtml(dict.request_types, 'goods');
    renderSiteOptions();
    if (!currentItems.length) addItemRow();
}

function renderSiteOptions(selected = '') {
    const company = dict.companies[document.getElementById('companyKey').value] || null;
    const sites = company && company.sites ? company.sites : {};
    const site = document.getElementById('siteKey');
    site.innerHTML = '<option value="">Без площадки</option>' + optionHtml(sites, selected);
    if (selected) {
        site.value = selected;
    }
}

function itemTemplate(item = {}) {
    const index = currentItems.length;
    currentItems.push({
        category: item.category || 'goods',
        name: item.name || '',
        quantity: item.quantity || 1,
        unit: item.unit || 'pcs',
        estimated_price: item.estimated_price || 0,
        justification: item.justification || ''
    });
    return index;
}

function addItemRow(item = {}) {
    itemTemplate(item);
    renderItems();
}

function renderItems() {
    const html = currentItems.map((item, index) => `
        <div class="item-row" data-index="${index}">
            <div class="field">
                <label>Вид строки</label>
                <select data-field="category">${optionHtml(dict.item_categories, item.category)}</select>
            </div>
            <div class="field wide">
                <label>Наименование</label>
                <input data-field="name" placeholder="Например: насос, ремонт, услуга" value="${escapeHtml(item.name)}">
            </div>
            <div class="field">
                <label>Количество</label>
                <input data-field="quantity" type="number" min="0.0001" step="0.0001" value="${escapeHtml(item.quantity)}">
                <div class="field-hint">Можно дробное: 1, 2.5</div>
            </div>
            <div class="field">
                <label>Ед. изм.</label>
                <select data-field="unit">${optionHtml(dict.units, item.unit)}</select>
            </div>
            <div class="field">
                <label>Цена за ед., руб.</label>
                <input data-field="estimated_price" type="number" min="0" step="0.01" value="${escapeHtml(item.estimated_price)}">
                <div class="field-hint">Если неизвестно, оставьте 0</div>
            </div>
            <div class="field wide">
                <label>Обоснование / комментарий инициатора</label>
                <input data-field="justification" placeholder="Зачем требуется позиция" value="${escapeHtml(item.justification)}">
            </div>
            <button type="button" class="danger" data-remove="${index}">×</button>
        </div>
    `).join('');
    document.getElementById('itemsEditor').innerHTML = html || '<div class="notice">Добавьте строку заявки.</div>';
}

function setSelectedFiles(files, replace = false) {
    if (replace) selectedFiles = [];
    Array.prototype.forEach.call(files || [], file => {
        const key = file.name + '_' + file.size + '_' + file.lastModified;
        const known = selectedFiles.some(existing => existing.name + '_' + existing.size + '_' + existing.lastModified === key);
        if (!known) selectedFiles.push(file);
    });
    renderSelectedFiles();
}

function renderSelectedFiles() {
    document.getElementById('selectedFilesList').innerHTML = selectedFiles.map((file, index) => `
        <li><span>${escapeHtml(file.name)} · ${Math.max(1, Math.ceil(file.size / 1024))} КБ</span><button type="button" class="danger" data-remove-file="${index}">×</button></li>
    `).join('');
}

function renderExistingAttachments(attachments = []) {
    const box = document.getElementById('existingAttachments');
    if (!attachments.length) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = '<div class="field-hint">Уже прикреплено:</div><ul class="file-list">' + attachments.map(file => {
        const label = file.ORIGINAL_NAME || ('Файл #' + file.FILE_ID);
        const url = file.URL || '#';
        return `<li><a href="${escapeHtml(url)}" target="_blank">${escapeHtml(label)}</a><span>${Math.max(1, Math.ceil(Number(file.FILE_SIZE || 0) / 1024))} КБ</span></li>`;
    }).join('') + '</ul>';
}

function collectRequest() {
    syncItemsFromDom();
    return {
        id: Number(document.getElementById('requestId').value || 0),
        company_key: document.getElementById('companyKey').value,
        site_key: document.getElementById('siteKey').value,
        request_type: document.getElementById('requestType').value,
        department_name: document.getElementById('departmentName').value,
        place_text: document.getElementById('placeText').value,
        required_date: document.getElementById('requiredDate').value,
        justification: document.getElementById('justification').value,
        comment_text: document.getElementById('commentText').value,
        items: currentItems
    };
}

function syncItemsFromDom() {
    document.querySelectorAll('.item-row').forEach(row => {
        const index = Number(row.dataset.index);
        if (!currentItems[index]) currentItems[index] = {};
        row.querySelectorAll('[data-field]').forEach(field => currentItems[index][field.dataset.field] = field.value);
    });
}

function renderRoute(route) {
    document.getElementById('routePreview').innerHTML = (route || []).map((step, index) => `
        <div class="route-step">
            <div class="badge">${index + 1}</div>
            <b>${escapeHtml(step.title || step.code || '')}</b>
            <div class="muted">${escapeHtml((dict.roles || {})[step.role] || step.role || '')}</div>
        </div>
    `).join('');
}

async function saveDraft() {
    const data = await api('requests.php', {method:'POST', body:{action:'save_draft', request: collectRequest()}});
    document.getElementById('requestId').value = data.id;
    await uploadAttachments(data.id);
    showNotice('Черновик сохранён.', 'success');
    await loadRequests();
    return data.id;
}

async function submitRequest() {
    const draft = await api('requests.php', {method:'POST', body:{action:'save_draft', request: collectRequest()}});
    document.getElementById('requestId').value = draft.id;
    await uploadAttachments(draft.id);
    const data = await api('requests.php', {method:'POST', body:{action:'submit', request: {id: draft.id}}});
    document.getElementById('requestId').value = data.id;
    showNotice('Заявка отправлена по маршруту.', 'success');
    renderRoute(data.request.ROUTE || []);
    await loadRequests();
    await loadTasks();
}

async function loadRequests() {
    const box = document.getElementById('requestsContent');
    box.className = 'notice';
    box.textContent = 'Загружаем...';
    const data = await api('requests.php', {params:{action:'list'}});
    if (!data.rows.length) {
        box.className = 'notice';
        box.textContent = 'Заявок пока нет.';
        return;
    }
    box.className = '';
    box.innerHTML = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Статус</th><th>Инициатор</th><th>Компания</th><th>Площадка</th><th>Сумма</th><th>Рег. номер</th><th></th>
    </tr></thead><tbody>${data.rows.map(row => `
        <tr>
            <td data-label="ID">#${escapeHtml(row.ID)}</td>
            <td data-label="Статус"><span class="badge">${escapeHtml(dict.statuses[row.STATUS] || row.STATUS)}</span></td>
            <td data-label="Инициатор">${escapeHtml(row.INITIATOR_NAME || '')}</td>
            <td data-label="Компания">${escapeHtml(row.COMPANY_NAME || '')}</td>
            <td data-label="Площадка">${escapeHtml(row.SITE_NAME || '')}</td>
            <td data-label="Сумма">${escapeHtml(row.TOTAL_AMOUNT || '0')} ${escapeHtml(row.CURRENCY || '')}</td>
            <td data-label="Рег. номер">${escapeHtml(row.REG_NUMBER || '')}</td>
            <td data-label=""><button type="button" class="light" data-open-request="${escapeHtml(row.ID)}">Открыть</button></td>
        </tr>`).join('')}</tbody></table></div>`;
}

async function openRequest(id) {
    const data = await api('requests.php', {params:{action:'view', id}});
    const r = data.request;
    document.getElementById('requestId').value = r.ID || '';
    document.getElementById('companyKey').value = r.COMPANY_KEY || '';
    renderSiteOptions(r.SITE_KEY || '');
    document.getElementById('siteKey').value = r.SITE_KEY || '';
    document.getElementById('requestType').value = r.REQUEST_TYPE || 'goods';
    document.getElementById('departmentName').value = r.DEPARTMENT_NAME || '';
    document.getElementById('placeText').value = r.PLACE_TEXT || '';
    document.getElementById('requiredDate').value = (r.REQUIRED_DATE || '').slice(0, 10);
    document.getElementById('justification').value = r.JUSTIFICATION || '';
    document.getElementById('commentText').value = r.COMMENT_TEXT || '';
    currentItems = (r.ITEMS || []).map(item => ({
        category: item.CATEGORY || 'goods',
        name: item.NAME || '',
        quantity: item.QUANTITY || 1,
        unit: item.UNIT || 'pcs',
        estimated_price: item.ESTIMATED_PRICE || 0,
        justification: item.JUSTIFICATION || ''
    }));
    renderItems();
    renderRoute(r.ROUTE || []);
    renderExistingAttachments(r.ATTACHMENTS || []);
    document.querySelector('[data-view="newView"]').click();
}

async function loadTasks() {
    const box = document.getElementById('tasksContent');
    box.className = 'notice';
    box.textContent = 'Загружаем...';
    const data = await api('tasks.php', {params:{action:'list'}});
    if (!data.tasks.length) {
        box.className = 'notice';
        box.textContent = 'Нет заданий, ожидающих решения.';
        return;
    }
    box.className = 'task-list';
    box.innerHTML = data.tasks.map(task => `
        <div class="task" data-task="${escapeHtml(task.ID)}" data-role="${escapeHtml(task.ROLE_CODE)}">
            <div class="task-head">
                <div>
                    <b>Заявка #${escapeHtml(task.REQUEST_ID)}: ${escapeHtml(task.STEP_TITLE)}</b>
                    <div class="muted">${escapeHtml(task.COMPANY_NAME || '')} ${escapeHtml(task.SITE_NAME || '')} · ${escapeHtml(task.TOTAL_AMOUNT || '0')} ${escapeHtml(task.CURRENCY || '')}</div>
                </div>
                <span class="badge open">${escapeHtml((dict.roles || {})[task.ROLE_CODE] || task.ROLE_CODE)}</span>
            </div>
            ${task.ROLE_CODE === 'warehouse' ? `
                <div class="grid">
                    <div class="col-3"><label>Наличие</label><select data-task-field="warehouse_status"><option value="full">Есть полностью</option><option value="partial">Есть частично</option><option value="none">Нет</option><option value="na">Не применимо</option></select></div>
                    <div class="col-3"><label>Количество</label><input data-task-field="warehouse_qty" type="number" min="0" step="0.0001"></div>
                    <div class="col-6"><label>Комментарий склада</label><input data-task-field="warehouse_comment" type="text"></div>
                </div>` : ''}
            ${task.ROLE_CODE === 'registrar' ? `
                <div class="grid">
                    <div class="col-6"><label>Регистрационный номер</label><input data-task-field="reg_number" type="text"></div>
                    <div class="col-3"><label>Дата регистрации</label><input data-task-field="reg_date" type="date"></div>
                </div>` : ''}
            <label>Комментарий</label>
            <textarea data-task-field="comment"></textarea>
            <div class="actions">
                <button type="button" data-decision="approve">Согласовать</button>
                <button type="button" class="secondary" data-decision="revision">Вернуть</button>
                <button type="button" class="danger" data-decision="reject">Отклонить</button>
                <button type="button" class="light" data-open-request="${escapeHtml(task.REQUEST_ID)}">Открыть заявку</button>
            </div>
        </div>`).join('');
}

async function sendDecision(taskEl, decision) {
    const field = name => taskEl.querySelector(`[data-task-field="${name}"]`)?.value || '';
    await api('tasks.php', {method:'POST', body:{
        action:'decision',
        task_id: Number(taskEl.dataset.task),
        decision,
        comment: field('comment'),
        warehouse: {status: field('warehouse_status'), qty: field('warehouse_qty'), comment: field('warehouse_comment')},
        registration: {reg_number: field('reg_number'), reg_date: field('reg_date')}
    }});
    showNotice('Решение сохранено.', 'success');
    await loadTasks();
    await loadRequests();
}

async function loadAdmin() {
    const box = document.getElementById('adminContent');
    box.className = 'notice';
    box.textContent = 'Загружаем настройки...';
    const data = await api('admin.php');
    adminCache = data;
    renderAdmin(data);
}

function renderAdmin(data) {
    const box = document.getElementById('adminContent');
    box.className = '';
    const roleOptions = optionHtml(data.roles);
    const companyOptions = '<option value="">Все компании</option>' + optionHtml(data.companies);
    const routes = data.routes || [];
    box.innerHTML = `
        <div class="admin-layout">
            <div class="stack">
                <div class="admin-box">
                    <h2>Назначение роли</h2>
                    <label>Роль</label><select id="admRole">${roleOptions}</select>
                    <label>ID пользователя Bitrix</label><input id="admUserId" type="number" min="1">
                    <label>ФИО</label><input id="admUserName" type="text">
                    <label>Компания</label><select id="admCompany">${companyOptions}</select>
                    <label>Площадка</label><input id="admSite" type="text">
                    <label>Подразделение</label><input id="admDepartment" type="text">
                    <label>Должность</label><input id="admPosition" type="text">
                    <div class="actions"><button type="button" id="saveAssignment">Сохранить назначение</button></div>
                </div>
                <div class="admin-box">
                    <h2>Маршрут</h2>
                    <label>Название</label><input id="routeTitle" value="${escapeHtml(routes[0]?.TITLE || 'Базовый маршрут')}">
                    <label>Шаги JSON</label><textarea id="routeSteps" class="json-area">${escapeHtml(JSON.stringify(routes[0]?.STEPS || data.default_steps || [], null, 2))}</textarea>
                    <div class="actions"><button type="button" id="saveRoute">Сохранить маршрут</button></div>
                </div>
            </div>
            <div>
                <h2>Текущие назначения</h2>
                <div class="table-wrap"><table><thead><tr><th>Роль</th><th>Пользователь</th><th>Компания</th><th>Площадка</th><th>Должность</th><th></th></tr></thead><tbody>
                    ${(data.assignments || []).map(row => `<tr>
                        <td data-label="Роль">${escapeHtml(data.roles[row.ROLE_CODE] || row.ROLE_CODE)}</td>
                        <td data-label="Пользователь">#${escapeHtml(row.USER_ID)} ${escapeHtml(row.USER_NAME || '')}</td>
                        <td data-label="Компания">${escapeHtml(row.COMPANY_KEY || 'Все')}</td>
                        <td data-label="Площадка">${escapeHtml(row.SITE_KEY || 'Все')}</td>
                        <td data-label="Должность">${escapeHtml(row.POSITION_NAME || '')}</td>
                        <td data-label=""><button type="button" class="danger" data-delete-assignment="${escapeHtml(row.ID)}">Удалить</button></td>
                    </tr>`).join('')}
                </tbody></table></div>
            </div>
        </div>`;
}

function bindEvents() {
    bindTabs();
    document.getElementById('companyKey').addEventListener('change', () => renderSiteOptions());
    document.getElementById('addItem').addEventListener('click', () => addItemRow());
    document.getElementById('itemsEditor').addEventListener('input', syncItemsFromDom);
    document.getElementById('itemsEditor').addEventListener('change', syncItemsFromDom);
    document.getElementById('itemsEditor').addEventListener('click', e => {
        const remove = e.target.closest('[data-remove]');
        if (!remove) return;
        currentItems.splice(Number(remove.dataset.remove), 1);
        renderItems();
    });
    document.getElementById('attachmentsInput').addEventListener('change', e => setSelectedFiles(e.target.files, true));
    document.getElementById('selectedFilesList').addEventListener('click', e => {
        const remove = e.target.closest('[data-remove-file]');
        if (!remove) return;
        selectedFiles.splice(Number(remove.dataset.removeFile), 1);
        renderSelectedFiles();
    });
    document.getElementById('previewRoute').addEventListener('click', async () => {
        try {
            const data = await api('requests.php', {method:'POST', body:{action:'route_preview', request: collectRequest()}});
            document.getElementById('requestId').value = data.id;
            renderRoute(data.route || []);
        } catch (err) {
            showNotice(err.message, 'error');
        }
    });
    document.getElementById('saveDraft').addEventListener('click', () => saveDraft().catch(err => showNotice(err.message, 'error')));
    document.getElementById('requestForm').addEventListener('submit', e => { e.preventDefault(); submitRequest().catch(err => showNotice(err.message, 'error')); });
    document.getElementById('resetRequest').addEventListener('click', () => { document.getElementById('requestForm').reset(); document.getElementById('requestId').value = ''; document.getElementById('attachmentsInput').value = ''; currentItems = []; selectedFiles = []; renderSiteOptions(); addItemRow(); renderSelectedFiles(); renderExistingAttachments([]); renderRoute([]); });
    document.getElementById('refreshRequests').addEventListener('click', () => loadRequests().catch(err => showNotice(err.message, 'error')));
    document.getElementById('refreshTasks').addEventListener('click', () => loadTasks().catch(err => showNotice(err.message, 'error')));
    document.getElementById('refreshAdmin').addEventListener('click', () => loadAdmin().catch(err => showNotice(err.message, 'error')));
    document.body.addEventListener('click', e => {
        const open = e.target.closest('[data-open-request]');
        if (open) openRequest(open.dataset.openRequest).catch(err => showNotice(err.message, 'error'));
        const decision = e.target.closest('[data-decision]');
        if (decision) sendDecision(decision.closest('.task'), decision.dataset.decision).catch(err => showNotice(err.message, 'error'));
        const del = e.target.closest('[data-delete-assignment]');
        if (del) api('admin.php', {method:'POST', body:{action:'delete_assignment', id:Number(del.dataset.deleteAssignment)}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        if (e.target.id === 'saveAssignment') {
            api('admin.php', {method:'POST', body:{action:'save_assignment', assignment:{
                role_code: document.getElementById('admRole').value,
                user_id: Number(document.getElementById('admUserId').value || 0),
                user_name: document.getElementById('admUserName').value,
                company_key: document.getElementById('admCompany').value,
                site_key: document.getElementById('admSite').value,
                department_name: document.getElementById('admDepartment').value,
                position_name: document.getElementById('admPosition').value
            }}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'saveRoute') {
            let steps;
            try { steps = JSON.parse(document.getElementById('routeSteps').value); } catch (err) { showNotice('Некорректный JSON маршрута.', 'error'); return; }
            api('admin.php', {method:'POST', body:{action:'save_route', route:{id: adminCache?.routes?.[0]?.ID || 0, title: document.getElementById('routeTitle').value, steps}}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        }
    });
}

async function init() {
    bindEvents();
    const hasBx24Auth = await ensureBx24Auth(false);
    const data = await api('bootstrap.php');
    apiMode = data.api_mode || (String(data.user?.source || '').indexOf('bitrix_session') !== -1 ? 'direct' : 'proxy');
    dict = data.dictionaries || dict;
    fillDictionaries();
    setStatus('Авторизация подтверждена. Пользователь #' + data.user.id + (hasBx24Auth ? ' · BX24 auth' : ''), 'success');
    await Promise.all([loadRequests(), loadTasks()]);
    if (initialRequestId) await openRequest(initialRequestId);
}

init().catch(error => {
    setStatus('Ошибка запуска', 'error');
    showNotice(error.message, 'error');
});
</script>
</body>
</html>
