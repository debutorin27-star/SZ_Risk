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
        :root{--bg:#f4f6f8;--panel:#fff;--line:#d9e0e8;--line-strong:#c6d0dc;--text:#152033;--muted:#64748b;--soft:#f8fafc;--accent:#1769e0;--accent-dark:#0f4fb0;--danger:#b42318;--ok:#157347;--warn:#9a6700}
        html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;line-height:1.35;overflow-x:hidden}
        .shell{max-width:none;margin:0 auto;padding:12px}
        .top-status{position:sticky;top:0;z-index:20;margin:-12px -12px 12px;padding:10px 14px;background:#eaf3ff;color:#0f4f8f;border-bottom:1px solid #c6def8;font-size:13px}
        .top-status.error{background:#fff1f0;color:var(--danger);border-bottom-color:#ffd4cf}
        .top-status.success{background:#eaf7ef;color:var(--ok);border-bottom-color:#bfe6cc}
        .toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:12px}
        h1{font-size:22px;line-height:1.25;margin:0}
        h2{line-height:1.25}
        .tabs{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
        .tab{border:1px solid var(--line);background:#fff;color:#20304a;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer;min-height:38px}
        .tab.active{border-color:var(--accent);background:var(--accent);color:#fff}
        .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;box-shadow:0 2px 8px rgba(15,23,42,.035)}
        .view{display:none}.view.active{display:block}
        .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
        .col-2{grid-column:span 2}.col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}
        label{display:block;font-weight:700;font-size:13px;margin-bottom:5px}
        input,select,textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:var(--text);font-size:15px;padding:9px;min-height:40px}
        input:focus,select:focus,textarea:focus,button:focus{outline:2px solid rgba(23,105,224,.22);outline-offset:1px;border-color:var(--accent)}
        textarea{min-height:86px;resize:vertical}
        button{border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;font-size:14px;padding:10px 13px;min-height:38px;cursor:pointer;white-space:nowrap}
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
        th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:14px}
        th{background:var(--soft);color:#475569;font-weight:800;white-space:nowrap}
        td{overflow-wrap:anywhere}
        tr:last-child td{border-bottom:0}
        .muted{color:var(--muted);font-size:13px}
        .entity-title{display:block;font-weight:800;color:var(--text)}
        .entity-subtitle{display:block;color:var(--muted);font-size:13px;margin-top:2px}
        .amount{font-weight:800;white-space:nowrap}
        .badge{display:inline-flex;align-items:center;border-radius:999px;background:#eef2f7;color:#334155;padding:4px 8px;font-size:12px;font-weight:800;line-height:1.2;white-space:nowrap}
        .badge.open,.badge.approval,.badge.warehouse,.badge.supply,.badge.execution,.badge.acceptance{background:#fff7ed;color:#9a3412}
        .badge.done,.badge.approved,.badge.registered{background:#ecfdf3;color:#166534}
        .badge.rejected,.badge.cancelled{background:#fff1f0;color:var(--danger)}
        .badge.revision{background:#fff8db;color:#8a5a00}
        .subhead{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:2px 0 10px}
        .subhead h2{font-size:18px;margin:0}
        .form-band{border:1px solid var(--line);border-radius:8px;background:var(--soft);padding:12px;margin:12px 0}
        .form-band>.subhead{margin-top:0}
        .item-editor{margin-top:12px;border:1px solid var(--line);border-radius:8px;background:var(--soft);padding:12px}
        .item-editor .subhead{margin-top:0}
        .item-row{display:grid;grid-template-columns:130px minmax(210px,1.5fr) minmax(180px,1fr) 110px 110px 170px minmax(230px,1.4fr) 44px;gap:8px;align-items:start;margin-bottom:10px;border:1px solid var(--line);border-radius:8px;padding:10px;background:#fff}
        .field label{font-size:12px;margin-bottom:4px;color:#475569}
        .field-hint{margin-top:3px;color:var(--muted);font-size:12px;line-height:1.3}
        .attachments{margin-top:14px;border:1px solid var(--line);border-radius:8px;padding:12px;background:#f8fafc}
        .file-list{margin:8px 0 0;padding:0;list-style:none;display:grid;gap:6px}
        .file-list li{display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid var(--line);border-radius:7px;background:#fff;padding:7px 9px;font-size:14px;min-width:0}
        .file-list li span,.file-list li a{min-width:0;overflow-wrap:anywhere}
        .file-list a{color:var(--accent);text-decoration:none}
        .route-preview{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:10px}
        .route-step{border:1px solid var(--line);border-radius:8px;padding:9px;background:#fff}
        .route-step-head{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px}
        .route-step-no{display:inline-flex;align-items:center;justify-content:center;flex:0 0 24px;width:24px;height:24px;border-radius:999px;background:#eef5ff;color:#1554b0;font-size:12px;font-weight:900}
        .route-step-title{font-weight:800;line-height:1.25}
        .route-step-users{margin-top:6px;color:var(--muted);font-size:13px;line-height:1.35}
        .timeline{display:grid;gap:8px;margin-top:12px}
        .timeline-item{display:grid;grid-template-columns:110px 1fr;gap:10px;border-left:3px solid var(--line);padding:8px 10px;background:#fff;border-radius:7px}
        .timeline-item.done{border-left-color:var(--ok)}.timeline-item.open{border-left-color:var(--accent)}
        .task-items{margin:10px 0;border:1px solid var(--line);border-radius:8px;background:var(--soft);padding:10px}
        .task-items>b,.check-list>b{display:block;margin-bottom:6px}
        .task-items ul{margin:6px 0 0;padding-left:18px}
        .task-table-wrap{overflow:auto;margin-top:8px}
        .task-table{min-width:980px;background:#fff}
        .task-table input,.task-table select{font-size:14px;padding:7px}
        .task-table .small-input{min-width:90px}
        .check-list{display:grid;gap:8px;margin:10px 0;border:1px solid var(--line);border-radius:8px;background:#f8fafc;padding:10px}
        .check-row{display:flex;align-items:flex-start;gap:8px;margin:0;font-size:14px;font-weight:600;line-height:1.35}
        .check-row input{width:auto}
        .user-search-results{display:grid;gap:6px;margin-top:6px}
        .user-search-results button{display:block;width:100%;text-align:left;background:#fff;color:var(--text);border:1px solid var(--line);font-weight:600}
        .task-list{display:grid;gap:10px}
        .task{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff;box-shadow:0 1px 4px rgba(15,23,42,.035)}
        .task-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px}
        .task-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
        .task-meta span{border:1px solid var(--line);border-radius:999px;background:#fff;color:#475569;padding:3px 8px;font-size:12px;font-weight:700}
        .task-comment{margin-top:10px}
        .task-actions{border-top:1px solid var(--line);padding-top:10px;margin-top:10px}
        .admin-layout{display:grid;grid-template-columns:minmax(380px,540px) minmax(0,1fr);gap:12px}
        .admin-box{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff}
        .admin-box h2+label,.admin-box h2+.grid{margin-top:4px}
        .admin-box h2:not(:first-child){margin-top:14px;padding-top:12px;border-top:1px solid var(--line)}
        .admin-box h2{font-size:16px;margin:0 0 10px}
        .stack{display:grid;gap:10px}
        .json-area{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;min-height:220px}
        @media(max-width:860px){
            .shell{padding:0}.top-status{margin:0}.toolbar{display:block;padding:12px 12px 0}.tabs{margin-top:10px}.panel{border-left:0;border-right:0;border-radius:0;box-shadow:none;padding:12px}
            .grid{display:block}.grid>div{margin-bottom:10px}.admin-layout{display:block}.admin-layout>div{margin-bottom:12px}
            .item-row{grid-template-columns:1fr;gap:8px;border:1px solid var(--line);border-radius:8px;padding:9px;background:#fff}.item-row .wide{grid-column:auto}.item-row button{width:44px}
            .table-wrap{border:0;overflow:visible}table,tbody,tr,td{display:block;width:100%;box-sizing:border-box}table{min-width:0}thead{display:none}
            tr{border:1px solid var(--line);border-radius:8px;margin-bottom:10px;background:#fff}td{border-bottom:0;padding:7px 9px}td::before{content:attr(data-label);display:block;color:var(--muted);font-size:12px;font-weight:800;margin-bottom:2px}
            .task-head{display:block}.actions button{flex:1 1 auto}.task-meta{margin-bottom:8px}.subhead{align-items:flex-start}.file-list li{align-items:flex-start}
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
            <button class="tab" data-view="allRequestsView" id="allRequestsTab" style="display:none">Все заявки</button>
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

        <section id="allRequestsView" class="view">
            <div class="subhead">
                <h2>Все заявки</h2>
                <button type="button" class="light" id="refreshAllRequests">Обновить</button>
            </div>
            <form id="allFilters" class="admin-box">
                <div class="grid">
                    <div class="col-4"><label for="allQ">Поиск</label><input id="allQ" type="search" placeholder="ID, инициатор, подразделение, рег. номер"></div>
                    <div class="col-2"><label for="allCompany">Компания</label><select id="allCompany"></select></div>
                    <div class="col-2"><label for="allStatus">Статус</label><select id="allStatus"></select></div>
                    <div class="col-2"><label for="allSite">Площадка</label><select id="allSite"></select></div>
                    <div class="col-2"><label for="allType">Тип</label><select id="allType"></select></div>
                    <div class="col-3"><label for="allDateFrom">Создана с</label><input id="allDateFrom" type="date"></div>
                    <div class="col-3"><label for="allDateTo">Создана по</label><input id="allDateTo" type="date"></div>
                </div>
                <div class="actions">
                    <button type="submit">Найти</button>
                    <button type="button" class="light" id="clearAllFilters">Сбросить</button>
                </div>
            </form>
            <div id="allRequestsContent" class="notice">Задайте фильтр или обновите список.</div>
        </section>

        <section id="newView" class="view">
            <div class="subhead">
                <h2>Карточка заявки</h2>
                <button type="button" class="light" id="resetRequest">Очистить</button>
            </div>
            <form id="requestForm">
                <input type="hidden" id="requestId">
                <div class="form-band">
                <div class="grid">
                    <div class="col-4"><label for="companyKey">Компания *</label><select id="companyKey" required></select></div>
                    <div class="col-4"><label for="siteKey">Площадка *</label><select id="siteKey" required></select></div>
                    <div class="col-4"><label for="requestType">Тип заявки *</label><select id="requestType" required></select></div>
                    <div class="col-4"><label for="initiatorProfile">Инициатор / профиль</label><select id="initiatorProfile"></select></div>
                    <div class="col-4"><label for="departmentName">Подразделение</label><select id="departmentName"></select></div>
                    <div class="col-4"><label for="positionName">Должность</label><input id="positionName" type="text"></div>
                    <div class="col-4"><label for="placeText">Место обращения *</label><input id="placeText" type="text" required></div>
                    <div class="col-4"><label for="requiredDate">Желаемый срок</label><input id="requiredDate" type="date"></div>
                    <div class="col-6"><label for="justification">Обоснование *</label><textarea id="justification" required></textarea></div>
                    <div class="col-6"><label for="commentText">Примечание</label><textarea id="commentText"></textarea></div>
                </div>
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
                <div id="routeTimeline"></div>
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

let dict = {companies:{}, sites:{}, sites_by_company:{}, initiator_profiles:{}, initiator_departments:[], request_types:{}, item_categories:{}, units:{}, roles:{}, statuses:{}, supply_checklist:{}};
let currentItems = [];
let selectedFiles = [];
let adminCache = null;
let apiMode = 'proxy';
let runtimeAuthPayload = {...(PR_AUTH_PAYLOAD || {})};
let bx24AuthPromise = null;
let currentUser = {};

const topStatus = document.getElementById('topStatus');
const noticeBox = document.getElementById('noticeBox');

function apiUrl(path, params = {}) {
    const directBootstrap = path === 'bootstrap.php';
    const useProxy = apiUsesProxy(path);
    const url = new URL(PR_APP_DIR + '/api/' + (useProxy ? 'proxy.php' : path), location.origin);
    if (useProxy) url.searchParams.set('target', path);
    url.searchParams.set('auth_context', PR_AUTH_CONTEXT || '');
    if (runtimeAuthPayload && (runtimeAuthPayload.AUTH_ID || runtimeAuthPayload.auth || runtimeAuthPayload.access_token)) {
        url.searchParams.set('app_auth_payload', JSON.stringify(runtimeAuthPayload));
    }
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    return url.toString();
}

function apiUsesProxy(path) {
    return path !== 'bootstrap.php' && apiMode !== 'direct';
}

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}

function inputDateValue(value) {
    if (!value) return '';
    if (typeof value === 'string') return value.slice(0, 10);
    if (value instanceof Date && !Number.isNaN(value.getTime())) return value.toISOString().slice(0, 10);
    if (typeof value === 'object') {
        const nested = value.date || value.DATE || value.value || value.VALUE || value.timestamp || '';
        if (nested) return inputDateValue(nested);
    }
    return String(value).slice(0, 10);
}

function statusBadgeClass(status = '') {
    const normalized = String(status || '').toLowerCase();
    const map = {
        draft: '',
        approval: 'approval',
        warehouse: 'warehouse',
        approved: 'approved',
        registration: 'approval',
        registered: 'registered',
        supply: 'supply',
        execution: 'execution',
        acceptance: 'acceptance',
        in_progress: 'execution',
        rejected: 'rejected',
        revision: 'revision',
        cancelled: 'cancelled',
        done: 'done'
    };
    return map[normalized] || '';
}

function statusBadgeHtml(status = '') {
    const label = (dict.statuses || {})[status] || status || 'Статус не указан';
    const cls = statusBadgeClass(status);
    return `<span class="badge ${escapeHtml(cls)}">${escapeHtml(label)}</span>`;
}

function formatMoney(value, currency = 'RUB') {
    const number = Number(value || 0);
    return `${number.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${currency || ''}`.trim();
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
        credentials: apiUsesProxy(path) ? 'omit' : 'same-origin',
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
        credentials: apiUsesProxy('requests.php') ? 'omit' : 'same-origin',
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

function firstCompanyKey() {
    return Object.keys(dict.companies || {})[0] || 'egida_plus';
}

function sitesForCompany(companyKey = '') {
    if (companyKey && dict.sites_by_company && dict.sites_by_company[companyKey]) {
        return dict.sites_by_company[companyKey] || {};
    }
    return dict.sites || {};
}

function siteNameByKey(siteKey = '') {
    const allSites = dict.sites || {};
    return allSites[siteKey] || siteKey || '';
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
            if (button.dataset.view === 'allRequestsView') loadAllRequests();
            if (button.dataset.view === 'adminView') loadAdmin();
        });
    });
}

function fillDictionaries() {
    const companyKey = document.getElementById('companyKey');
    companyKey.innerHTML = optionHtml(dict.companies, firstCompanyKey());
    companyKey.value = companyKey.value || firstCompanyKey();
    document.getElementById('requestType').innerHTML = optionHtml(dict.request_types, 'goods');
    renderSiteOptions('');
    renderDepartmentOptions(currentUser.department || '');
    renderInitiatorProfiles();
    renderAllRequestFilters();
    if (currentUser.position) document.getElementById('positionName').value = currentUser.position;
    if (!currentItems.length) addItemRow();
}

function renderAllRequestFilters() {
    const status = document.getElementById('allStatus');
    if (!status) return;
    document.getElementById('allCompany').innerHTML = optionsWithEmpty(dict.companies || {}, 'Все компании');
    status.innerHTML = optionsWithEmpty(dict.statuses || {}, 'Все статусы');
    renderAllSiteFilterOptions();
    document.getElementById('allType').innerHTML = optionsWithEmpty(dict.request_types || {}, 'Все типы');
}

function renderAllSiteFilterOptions(selected = '') {
    const companyKey = document.getElementById('allCompany')?.value || '';
    const sites = companyKey ? sitesForCompany(companyKey) : (dict.sites || {});
    const site = document.getElementById('allSite');
    if (!site) return;
    site.innerHTML = optionsWithEmpty(sites, 'Все площадки', selected);
}

function renderSiteOptions(selected = '') {
    const site = document.getElementById('siteKey');
    site.innerHTML = '<option value="">Выберите площадку</option>' + optionHtml(sitesForCompany(document.getElementById('companyKey')?.value || firstCompanyKey()), selected);
    if (selected) site.value = selected;
    renderDepartmentOptions(document.getElementById('departmentName').value || '');
    renderInitiatorProfiles();
}

function renderDepartmentOptions(selected = '') {
    const select = document.getElementById('departmentName');
    const siteKey = document.getElementById('siteKey')?.value || '';
    const profiles = (dict.initiator_profiles || {})[siteKey] || [];
    const bySite = profiles.map(item => item.department || '').filter(Boolean);
    const departments = bySite.length ? Array.from(new Set(bySite)).sort() : (Array.isArray(dict.initiator_departments) ? dict.initiator_departments : []);
    const known = selected && departments.indexOf(selected) === -1 ? [selected].concat(departments) : departments;
    select.innerHTML = '<option value="">Выберите подразделение</option>' + known.map(value => `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(value)}</option>`).join('');
}

function renderInitiatorProfiles(selected = '') {
    const siteKey = document.getElementById('siteKey').value;
    const profiles = (dict.initiator_profiles || {})[siteKey] || [];
    const profile = document.getElementById('initiatorProfile');
    profile.innerHTML = '<option value="">Выберите из списка инициаторов</option>' + profiles.map((item, index) => {
        const label = item.label || [item.department, item.position].filter(Boolean).join(' / ');
        return `<option value="${index}"${String(index) === String(selected) ? ' selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');
}

function applyInitiatorProfile() {
    const siteKey = document.getElementById('siteKey').value;
    const profiles = (dict.initiator_profiles || {})[siteKey] || [];
    const item = profiles[Number(document.getElementById('initiatorProfile').value)] || null;
    if (!item) return;
    renderDepartmentOptions(item.department || '');
    document.getElementById('departmentName').value = item.department || '';
    document.getElementById('positionName').value = item.position || '';
}

function itemTemplate(item = {}) {
    const index = currentItems.length;
    currentItems.push({
        category: item.category || 'goods',
        name: item.name || '',
        equipment_text: item.equipment_text || '',
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
                <label>Место установки</label>
                <input data-field="equipment_text" placeholder="ABLG1 Машина резки на вспенивании" value="${escapeHtml(item.equipment_text)}">
                <div class="field-hint">ABLG1 Машина резки на вспенивании</div>
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
                <label>Предполагаемая цена за ед., руб.</label>
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

function attachmentListHtml(attachments = []) {
    if (!attachments.length) return '';
    return '<ul class="file-list">' + attachments.map(file => {
        const label = file.ORIGINAL_NAME || ('Файл #' + file.FILE_ID);
        const url = file.URL || '#';
        return `<li><a href="${escapeHtml(url)}" target="_blank">${escapeHtml(label)}</a><span>${Math.max(1, Math.ceil(Number(file.FILE_SIZE || 0) / 1024))} КБ</span></li>`;
    }).join('') + '</ul>';
}

function renderExistingAttachments(attachments = []) {
    const box = document.getElementById('existingAttachments');
    if (!attachments.length) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = '<div class="field-hint">Уже прикреплено:</div>' + attachmentListHtml(attachments);
}

function collectRequest() {
    syncItemsFromDom();
    return {
        id: Number(document.getElementById('requestId').value || 0),
        company_key: document.getElementById('companyKey').value,
        site_key: document.getElementById('siteKey').value,
        request_type: document.getElementById('requestType').value,
        department_name: document.getElementById('departmentName').value,
        initiator_position: document.getElementById('positionName').value,
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
            <div class="route-step-head">
                <span class="route-step-no">${index + 1}</span>
                <div>
                    <div class="route-step-title">${escapeHtml(step.title || step.code || '')}</div>
                    <div class="muted">${escapeHtml((dict.roles || {})[step.role] || step.role || '')}</div>
                </div>
            </div>
            ${(step.assignees || []).length ? `<div class="route-step-users">${(step.assignees || []).map(user => {
                const primary = escapeHtml([user.name, user.position].filter(Boolean).join(' · '));
                const substitute = user.substitute && user.substitute.id ? `<br>Замещающий при отсутствии: ${escapeHtml([user.substitute.name, user.substitute.position].filter(Boolean).join(' · '))}` : '';
                return primary + substitute;
            }).join('<br>')}</div>` : '<div class="route-step-users">Согласующий не назначен</div>'}
        </div>
    `).join('');
}

function decisionLabel(status) {
    const labels = {OPEN:'Открыто', DONE:'Выполнено', approve:'Согласовано', revision:'Возврат', reject:'Отклонено'};
    return labels[status] || status || '';
}

function renderTimeline(timeline) {
    const box = document.getElementById('routeTimeline');
    if (!timeline || !timeline.length) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = `
        <div class="subhead"><h2>История маршрута</h2></div>
        <div class="timeline">
            ${timeline.map(event => {
                const cls = event.status === 'OPEN' ? 'open' : (event.type === 'decision' || event.status === 'DONE' ? 'done' : '');
                return `<div class="timeline-item ${cls}">
                    <div class="muted">${escapeHtml(event.time || '')}</div>
                    <div>
                        <b>${escapeHtml(event.title || '')}</b>
                        <div class="muted">${escapeHtml((dict.roles || {})[event.role] || event.role || '')} · ${escapeHtml(decisionLabel(event.status))}</div>
                        <div class="muted">${escapeHtml([event.user_name || (event.user_id ? 'Пользователь #' + event.user_id : ''), event.user_position || ''].filter(Boolean).join(' · '))}</div>
                        ${event.substitute_user_id ? `<div class="muted">Замещающий: ${escapeHtml(event.substitute_user_name || ('Пользователь #' + event.substitute_user_id))}</div>` : ''}
                        ${event.comment ? `<div>${escapeHtml(event.comment)}</div>` : ''}
                    </div>
                </div>`;
            }).join('')}
        </div>`;
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
    currentUser.can_view_all = !!data.can_view_all;
    currentUser.is_admin = !!data.is_admin;
    currentUser.is_observer = !!data.is_observer;
    const allTab = document.getElementById('allRequestsTab');
    if (allTab) allTab.style.display = currentUser.can_view_all ? '' : 'none';
    if (!data.rows.length) {
        box.className = 'notice';
        box.textContent = 'Заявок пока нет.';
        return;
    }
    box.className = '';
    box.innerHTML = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Статус</th><th>Компания</th><th>Тип</th><th>Инициатор</th><th>Площадка</th><th>Сумма</th><th>Рег. номер</th><th></th>
    </tr></thead><tbody>${data.rows.map(row => `
        <tr>
            <td data-label="ID">#${escapeHtml(row.ID)}</td>
            <td data-label="Статус">${statusBadgeHtml(row.STATUS)}</td>
            <td data-label="Компания">${escapeHtml(row.COMPANY_NAME || '')}</td>
            <td data-label="Тип">${escapeHtml((dict.request_types || {})[row.REQUEST_TYPE] || row.REQUEST_TYPE || '')}</td>
            <td data-label="Инициатор">${escapeHtml(row.INITIATOR_NAME || '')}</td>
            <td data-label="Площадка">${escapeHtml(row.SITE_NAME || '')}</td>
            <td data-label="Сумма"><span class="amount">${escapeHtml(formatMoney(row.TOTAL_AMOUNT, row.CURRENCY))}</span></td>
            <td data-label="Рег. номер">${escapeHtml(row.REG_NUMBER || '')}</td>
            <td data-label=""><button type="button" class="light" data-open-request="${escapeHtml(row.ID)}">Открыть</button></td>
        </tr>`).join('')}</tbody></table></div>`;
}

function collectAllFilters() {
    const params = {action:'all_list'};
    const map = {
        q: 'allQ',
        company_key: 'allCompany',
        status: 'allStatus',
        site_key: 'allSite',
        request_type: 'allType',
        date_from: 'allDateFrom',
        date_to: 'allDateTo'
    };
    Object.entries(map).forEach(([key, id]) => {
        const value = document.getElementById(id)?.value || '';
        if (value !== '') params[key] = value;
    });
    return params;
}

async function loadAllRequests() {
    const box = document.getElementById('allRequestsContent');
    box.className = 'notice';
    box.textContent = 'Загружаем общий реестр...';
    const data = await api('requests.php', {params: collectAllFilters()});
    if (!data.rows.length) {
        box.className = 'notice';
        box.textContent = 'Заявки по заданным условиям не найдены.';
        return;
    }
    box.className = '';
    box.innerHTML = `<div class="table-wrap"><table><thead><tr>
        <th>ID</th><th>Создана</th><th>Статус</th><th>Компания</th><th>Тип</th><th>Инициатор</th><th>Подразделение</th><th>Площадка</th><th>Сумма</th><th>Рег. номер</th><th></th>
    </tr></thead><tbody>${data.rows.map(row => `
        <tr>
            <td data-label="ID">#${escapeHtml(row.ID)}</td>
            <td data-label="Создана">${escapeHtml(String(row.CREATED_AT || '').slice(0, 16))}</td>
            <td data-label="Статус">${statusBadgeHtml(row.STATUS)}</td>
            <td data-label="Компания">${escapeHtml(row.COMPANY_NAME || '')}</td>
            <td data-label="Тип">${escapeHtml((dict.request_types || {})[row.REQUEST_TYPE] || row.REQUEST_TYPE || '')}</td>
            <td data-label="Инициатор">${escapeHtml(row.INITIATOR_NAME || '')}</td>
            <td data-label="Подразделение">${escapeHtml(row.DEPARTMENT_NAME || '')}</td>
            <td data-label="Площадка">${escapeHtml(row.SITE_NAME || '')}</td>
            <td data-label="Сумма"><span class="amount">${escapeHtml(formatMoney(row.TOTAL_AMOUNT, row.CURRENCY))}</span></td>
            <td data-label="Рег. номер">${escapeHtml(row.REG_NUMBER || '')}</td>
            <td data-label=""><button type="button" class="light" data-open-request="${escapeHtml(row.ID)}">Открыть</button></td>
        </tr>`).join('')}</tbody></table></div>`;
}

async function openRequest(id) {
    const data = await api('requests.php', {params:{action:'view', id}});
    const r = data.request;
    document.getElementById('requestId').value = r.ID || '';
    document.getElementById('companyKey').value = r.COMPANY_KEY || firstCompanyKey();
    renderSiteOptions(r.SITE_KEY || '');
    document.getElementById('siteKey').value = r.SITE_KEY || '';
    document.getElementById('requestType').value = r.REQUEST_TYPE || 'goods';
    renderDepartmentOptions(r.DEPARTMENT_NAME || '');
    document.getElementById('departmentName').value = r.DEPARTMENT_NAME || '';
    document.getElementById('positionName').value = r.INITIATOR_POSITION || '';
    document.getElementById('placeText').value = r.PLACE_TEXT || '';
    document.getElementById('requiredDate').value = inputDateValue(r.REQUIRED_DATE);
    document.getElementById('justification').value = r.JUSTIFICATION || '';
    document.getElementById('commentText').value = r.COMMENT_TEXT || '';
    currentItems = (r.ITEMS || []).map(item => ({
        category: item.CATEGORY || 'goods',
        name: item.NAME || '',
        equipment_text: item.EQUIPMENT_TEXT || '',
        quantity: item.QUANTITY || 1,
        unit: item.UNIT || 'pcs',
        estimated_price: item.ESTIMATED_PRICE || 0,
        justification: item.JUSTIFICATION || ''
    }));
    renderItems();
    renderRoute(r.ROUTE || []);
    renderTimeline(r.TIMELINE || []);
    renderExistingAttachments(r.ATTACHMENTS || []);
    document.querySelector('[data-view="newView"]').click();
}

function warehouseStatusOptions(selected = '') {
    return '<option value="">Выберите</option>' + optionHtml({
        full: 'Есть полностью',
        partial: 'Есть частично',
        none: 'Нет',
        na: 'Не применимо'
    }, selected);
}

function taskUsesItemApproval(task) {
    const roleCode = task.ROLE_CODE || '';
    const stepCode = task.STEP_CODE || '';
    const checklist = task.CHECKLIST_LABELS || {};
    if (Object.keys(checklist).length) return false;
    if ((task.REQUEST_STATUS || '') === 'ACCEPTANCE') return false;
    if (['warehouse', 'supply', 'initiator'].includes(roleCode)) return false;
    if (['warehouse', 'supply', 'automation_execution', 'initiator_acceptance'].includes(stepCode)) return false;
    return true;
}

function taskItemsHtml(task) {
    const items = task.ITEMS || [];
    if (!items.length) {
        return '<div class="task-items"><b>Состав закупки</b><div class="muted">Строки не найдены.</div></div>';
    }

    const roleCode = task.ROLE_CODE || '';
    const isWarehouse = roleCode === 'warehouse';
    const isApproval = taskUsesItemApproval(task);
    const head = `
        <th>Наименование</th>
        <th>Место установки</th>
        <th>Вид</th>
        <th>Кол-во</th>
        <th>Предп. цена</th>
        <th>Сумма</th>
        <th>Комментарий</th>
        ${isWarehouse ? '<th>Наличие</th><th>Кол-во на складе</th><th>Комментарий склада</th>' : ''}
        ${isApproval ? '<th>Согласование</th>' : ''}
    `;
    const rows = items.map(item => {
        const qty = Number(item.QUANTITY || 0);
        const price = Number(item.ESTIMATED_PRICE || 0);
        const amount = qty * price;
        const itemId = escapeHtml(item.ID || '');
        return `<tr${isWarehouse ? ` data-warehouse-row="${itemId}"` : ''}>
            <td data-label="Наименование">${escapeHtml(item.NAME || '')}</td>
            <td data-label="Место установки">${escapeHtml(item.EQUIPMENT_TEXT || '')}</td>
            <td data-label="Вид">${escapeHtml((dict.item_categories || {})[item.CATEGORY] || item.CATEGORY || '')}</td>
            <td data-label="Кол-во">${escapeHtml(qty || '')} ${escapeHtml((dict.units || {})[item.UNIT] || item.UNIT || '')}</td>
            <td data-label="Предп. цена">${price ? escapeHtml(formatMoney(price, task.CURRENCY || 'RUB')) : ''}</td>
            <td data-label="Сумма">${amount ? `<span class="amount">${escapeHtml(formatMoney(amount, task.CURRENCY || 'RUB'))}</span>` : ''}</td>
            <td data-label="Комментарий">${escapeHtml(item.JUSTIFICATION || '')}</td>
            ${isWarehouse ? `<td data-label="Наличие"><select data-warehouse-field="status">${warehouseStatusOptions(item.WAREHOUSE_STATUS || '')}</select></td>
                <td data-label="Кол-во на складе"><input class="small-input" data-warehouse-field="qty" type="number" min="0" step="0.0001" value="${escapeHtml(item.WAREHOUSE_QTY || '')}"></td>
                <td data-label="Комментарий склада"><input data-warehouse-field="comment" type="text" value="${escapeHtml(item.WAREHOUSE_COMMENT || '')}"></td>` : ''}
            ${isApproval ? `<td data-label="Согласование"><select data-item-decision="${itemId}"><option value="approve">Согласовать</option><option value="reject">Не согласовать</option></select></td>` : ''}
        </tr>`;
    }).join('');

    return `<div class="task-items">
        <b>Состав закупки</b>
        <div class="task-table-wrap"><table class="task-table"><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table></div>
        ${task.JUSTIFICATION ? `<div class="muted">Обоснование: ${escapeHtml(task.JUSTIFICATION)}</div>` : ''}
    </div>`;
}

function taskChecklistHtml(task) {
    const checklist = task.CHECKLIST_LABELS || {};
    const values = task.CHECKLIST || {};
    if (!Object.keys(checklist).length) return '';
    return `<div class="check-list">
        <b>${escapeHtml(task.CHECKLIST_TITLE || 'Чек-лист')}</b>
        ${Object.entries(checklist).map(([key, label]) => `<label class="check-row"><input type="checkbox" data-checklist-key="${escapeHtml(key)}"${values[key] ? ' checked' : ''}> ${escapeHtml(label)}</label>`).join('')}
        <div class="actions"><button type="button" class="light" data-save-checklist>Сохранить чек-лист</button></div>
        <label>Делегировать задание</label>
        <div class="grid">
            <div class="col-8"><input data-delegate-search type="text" placeholder="ФИО, email, логин или ID"></div>
            <div class="col-4"><button type="button" class="light" data-search-delegate>Найти</button></div>
        </div>
        <div data-delegate-results class="user-search-results"></div>
    </div>`;
}

function taskApproveLabel(task) {
    const stepCode = task.STEP_CODE || '';
    const roleCode = task.ROLE_CODE || '';
    const checklist = task.CHECKLIST_LABELS || {};
    if (stepCode === 'initiator_acceptance' || (task.REQUEST_STATUS || '') === 'ACCEPTANCE') return 'Принять выполнение';
    if (Object.keys(checklist).length) return 'Завершить этап';
    if (roleCode === 'warehouse') return 'Проверка выполнена';
    return 'Согласовать';
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
                    <span class="entity-title">Заявка #${escapeHtml(task.REQUEST_ID)}: ${escapeHtml(task.STEP_TITLE)}</span>
                    <span class="entity-subtitle">Инициатор: ${escapeHtml([task.INITIATOR_NAME || '', task.INITIATOR_POSITION || ''].filter(Boolean).join(' · ') || 'не указан')}</span>
                    <div class="task-meta">
                        <span>${escapeHtml(task.SITE_NAME || 'Площадка не указана')}</span>
                        <span>${escapeHtml(task.DEPARTMENT_NAME || 'Подразделение не указано')}</span>
                        <span>${escapeHtml(formatMoney(task.TOTAL_AMOUNT, task.CURRENCY))}</span>
                    </div>
                </div>
                <span class="badge open">${escapeHtml((dict.roles || {})[task.ROLE_CODE] || task.ROLE_CODE)}</span>
            </div>
            ${task.IS_SUBSTITUTE === 'Y' ? `<div class="notice">Вы действуете как замещающий. Основной исполнитель: #${escapeHtml(task.ASSIGNED_USER_ID)} ${escapeHtml(task.ASSIGNED_USER_NAME || '')}</div>` : ''}
            ${taskItemsHtml(task)}
            ${(task.ATTACHMENTS || []).length ? `<div class="task-items"><b>Файлы заявки</b>${attachmentListHtml(task.ATTACHMENTS || [])}</div>` : ''}
            ${taskChecklistHtml(task)}
            <label class="task-comment">Комментарий</label>
            <textarea data-task-field="comment"></textarea>
            <div class="actions task-actions">
                <button type="button" data-decision="approve">${escapeHtml(taskApproveLabel(task))}</button>
                <button type="button" class="secondary" data-decision="revision">Вернуть</button>
                <button type="button" class="danger" data-decision="reject">Отклонить</button>
                <button type="button" class="light" data-open-request="${escapeHtml(task.REQUEST_ID)}">Открыть заявку</button>
            </div>
        </div>`).join('');
}

async function sendDecision(taskEl, decision) {
    const field = name => taskEl.querySelector(`[data-task-field="${name}"]`)?.value || '';
    const warehouseItems = {};
    taskEl.querySelectorAll('[data-warehouse-row]').forEach(row => {
        const itemId = row.dataset.warehouseRow || '';
        if (!itemId) return;
        warehouseItems[itemId] = {
            status: row.querySelector('[data-warehouse-field="status"]')?.value || '',
            qty: row.querySelector('[data-warehouse-field="qty"]')?.value || '',
            comment: row.querySelector('[data-warehouse-field="comment"]')?.value || ''
        };
    });
    const itemDecisions = {};
    taskEl.querySelectorAll('[data-item-decision]').forEach(select => {
        if (select.dataset.itemDecision) itemDecisions[select.dataset.itemDecision] = select.value;
    });
    const taskChecklist = collectTaskChecklist(taskEl);
    await api('tasks.php', {method:'POST', body:{
        action:'decision',
        task_id: Number(taskEl.dataset.task),
        decision,
        comment: field('comment'),
        warehouse: {items: warehouseItems},
        supply: {checklist: taskChecklist},
        item_decisions: itemDecisions
    }});
    showNotice('Решение сохранено.', 'success');
    await loadTasks();
    await loadRequests();
}

function collectTaskChecklist(taskEl) {
    const checklist = {};
    taskEl.querySelectorAll('[data-checklist-key]').forEach(input => {
        checklist[input.dataset.checklistKey] = input.checked;
    });
    return checklist;
}

async function saveTaskChecklist(taskEl) {
    await api('tasks.php', {method:'POST', body:{
        action:'save_checklist',
        task_id: Number(taskEl.dataset.task),
        checklist: collectTaskChecklist(taskEl)
    }});
    showNotice('Чек-лист сохранен.', 'success');
    await loadTasks();
}

function renderTaskDelegateResults(taskEl, users) {
    const box = taskEl.querySelector('[data-delegate-results]');
    if (!box) return;
    if (!users || !users.length) {
        box.innerHTML = '<div class="muted">Пользователи не найдены.</div>';
        return;
    }
    box.innerHTML = users.map(user => `<button type="button" data-delegate-user-id="${escapeHtml(user.id)}">
        ${escapeHtml(user.name || ('Пользователь #' + user.id))}
        <span class="muted">#${escapeHtml(user.id)}${user.position ? ' · ' + escapeHtml(user.position) : ''}${user.department ? ' · ' + escapeHtml(user.department) : ''}</span>
    </button>`).join('');
}

async function searchTaskDelegates(taskEl) {
    const input = taskEl.querySelector('[data-delegate-search]');
    const box = taskEl.querySelector('[data-delegate-results]');
    if (box) box.innerHTML = '<div class="muted">Ищем...</div>';
    const data = await api('users.php', {params:{
        purpose:'delegate',
        task_id:Number(taskEl.dataset.task),
        q:(input?.value || '').trim()
    }});
    renderTaskDelegateResults(taskEl, data.users || []);
}

async function delegateTask(taskEl, delegateUserId) {
    if (!delegateUserId) {
        showNotice('Выберите сотрудника для делегирования.', 'error');
        return;
    }
    await api('tasks.php', {method:'POST', body:{
        action:'delegate',
        task_id:Number(taskEl.dataset.task),
        delegate_user_id:Number(delegateUserId)
    }});
    showNotice('Задание делегировано.', 'success');
    await loadTasks();
}

async function loadAdmin() {
    const box = document.getElementById('adminContent');
    box.className = 'notice';
    box.textContent = 'Загружаем настройки...';
    const data = await api('admin.php');
    if (data.companies) dict.companies = data.companies;
    if (data.sites) dict.sites = data.sites;
    if (data.sites_by_company) dict.sites_by_company = data.sites_by_company;
    adminCache = data;
    renderAdmin(data);
}

function optionsWithEmpty(map, emptyLabel, selected = '') {
    const selectedAttr = selected === '' || selected === null || typeof selected === 'undefined' ? ' selected' : '';
    return `<option value=""${selectedAttr}>${escapeHtml(emptyLabel)}</option>` + optionHtml(map || {}, selected);
}

function arrayOptions(values, emptyLabel, selected = '') {
    const selectedAttr = selected === '' || selected === null || typeof selected === 'undefined' ? ' selected' : '';
    return `<option value=""${selectedAttr}>${escapeHtml(emptyLabel)}</option>` + (values || []).map(value => `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(value)}</option>`).join('');
}

function siteOptionsForCompanySelect(companyKey, emptyLabel, selected = '') {
    return optionsWithEmpty(companyKey ? sitesForCompany(companyKey) : (dict.sites || {}), emptyLabel, selected);
}

function updateAdminSiteOptions(selected = '') {
    const companyKey = document.getElementById('admCompany')?.value || '';
    const site = document.getElementById('admSite');
    if (site) site.innerHTML = siteOptionsForCompanySelect(companyKey, 'Все площадки', selected);
}

function updateRouteSiteOptions(selected = '') {
    const companyKey = document.getElementById('routeCompany')?.value || '';
    const site = document.getElementById('routeSite');
    if (site) site.innerHTML = siteOptionsForCompanySelect(companyKey, 'Все площадки', selected);
}

function selectedRoute() {
    const index = Number(document.getElementById('routeSelector')?.value || 0);
    return (adminCache?.routes || [])[index] || {};
}

function fillRouteForm(route = {}) {
    if (!document.getElementById('routeId')) return;
    document.getElementById('routeId').value = route.ID || 0;
    document.getElementById('routeTitle').value = route.TITLE || route.title || 'Маршрут';
    document.getElementById('routeSort').value = route.SORT || route.sort || 100;
    document.getElementById('routeCompany').value = route.COMPANY_KEY || route.company_key || '';
    updateRouteSiteOptions(route.SITE_KEY || route.site_key || '');
    document.getElementById('routeSite').value = route.SITE_KEY || route.site_key || '';
    document.getElementById('routeRequestType').value = route.REQUEST_TYPE || route.request_type || '';
    document.getElementById('routeMinAmount').value = route.MIN_AMOUNT || route.min_amount || '';
    document.getElementById('routeMaxAmount').value = route.MAX_AMOUNT || route.max_amount || '';
    document.getElementById('routeInitiatorPosition').value = route.INITIATOR_POSITION || route.initiator_position || '';
    document.getElementById('routeItemCategory').value = route.ITEM_CATEGORY || route.item_category || '';
    document.getElementById('routeActive').checked = (route.IS_ACTIVE || route.is_active || 'Y') !== 'N';
    document.getElementById('routeSteps').value = JSON.stringify(route.STEPS || route.steps || adminCache?.default_steps || [], null, 2);
    const deleteButton = document.getElementById('deleteRoute');
    if (deleteButton) deleteButton.disabled = !(route.ID || 0);
}

function setSelectValueWithOption(selectId, value) {
    const select = document.getElementById(selectId);
    if (!select || !value) return;
    if (![...select.options].some(option => option.value === value)) {
        select.insertAdjacentHTML('afterbegin', `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`);
    }
    select.value = value;
}

function selectAdminUser(user) {
    document.getElementById('admUserId').value = user.id || '';
    document.getElementById('admUserName').value = user.name || '';
    setSelectValueWithOption('admDepartment', user.department || '');
    if (user.position) document.getElementById('admPosition').value = user.position;
    document.getElementById('admUserSummary').textContent = user.id ? `Выбран: #${user.id} ${user.name || ''}${user.email ? ' · ' + user.email : ''}` : '';
    document.getElementById('admUserResults').innerHTML = '';
}

function selectAdminSubstitute(user) {
    document.getElementById('admSubUserId').value = user.id || '';
    document.getElementById('admSubUserName').value = user.name || '';
    setSelectValueWithOption('admSubDepartment', user.department || '');
    if (user.position) document.getElementById('admSubPosition').value = user.position;
    document.getElementById('admSubUserSummary').textContent = user.id ? `Выбран замещающий: #${user.id} ${user.name || ''}${user.email ? ' · ' + user.email : ''}` : '';
    document.getElementById('admSubUserResults').innerHTML = '';
}

function clearAdminSubstitute() {
    document.getElementById('admSubUserSearch').value = '';
    document.getElementById('admSubUserId').value = '';
    document.getElementById('admSubUserName').value = '';
    document.getElementById('admSubDepartment').value = '';
    document.getElementById('admSubPosition').value = '';
    document.getElementById('admSubUserSummary').textContent = '';
    document.getElementById('admSubUserResults').innerHTML = '';
}

function renderAdminUserResults(users, target = 'primary') {
    const box = document.getElementById(target === 'substitute' ? 'admSubUserResults' : 'admUserResults');
    if (!users || !users.length) {
        box.innerHTML = '<div class="muted">Пользователи не найдены.</div>';
        return;
    }
    const attr = target === 'substitute' ? 'data-admin-sub-user' : 'data-admin-user';
    box.innerHTML = users.map(user => `<button type="button" ${attr}='${escapeHtml(JSON.stringify(user))}'>
        ${escapeHtml(user.name || ('Пользователь #' + user.id))}
        <span class="muted">#${escapeHtml(user.id)}${user.position ? ' · ' + escapeHtml(user.position) : ''}${user.department ? ' · ' + escapeHtml(user.department) : ''}</span>
    </button>`).join('');
}

async function searchAdminUsers(target = 'primary') {
    const query = document.getElementById(target === 'substitute' ? 'admSubUserSearch' : 'admUserSearch').value.trim();
    const box = document.getElementById(target === 'substitute' ? 'admSubUserResults' : 'admUserResults');
    box.innerHTML = '<div class="muted">Ищем...</div>';
    const data = await api('users.php', {params:{q:query}});
    renderAdminUserResults(data.users || [], target);
}

function selectAdminUserViaBitrix(target = 'primary') {
    searchAdminUsers(target).catch(err => showNotice(err.message, 'error'));
}

function renderAdmin(data) {
    const box = document.getElementById('adminContent');
    box.className = '';
    const roleOptions = optionHtml(data.roles);
    const assignmentCompanyOptions = optionsWithEmpty(data.companies || dict.companies, 'Все компании');
    const assignmentSiteOptions = siteOptionsForCompanySelect('', 'Все площадки');
    const assignmentDepartmentOptions = arrayOptions(data.initiator_departments || dict.initiator_departments || [], 'Любое подразделение');
    const routes = data.routes || [];
    const route = routes[0] || {};
    const routeOptions = routes.length ? routes.map((item, index) => `<option value="${index}">${escapeHtml(item.TITLE || ('Маршрут #' + item.ID))}</option>`).join('') : '<option value="0">Новый маршрут</option>';
    const presetOptions = '<option value="">Выберите шаблон</option>' + (data.route_presets || []).map(preset => `<option value="${escapeHtml(preset.code)}">${escapeHtml(preset.title)}</option>`).join('');
    const routeCompanyOptions = optionsWithEmpty(data.companies || dict.companies, 'Все компании', route.COMPANY_KEY || '');
    const routeSiteOptions = siteOptionsForCompanySelect(route.COMPANY_KEY || '', 'Все площадки', route.SITE_KEY || '');
    const routeRequestTypeOptions = optionsWithEmpty(data.request_types || dict.request_types, 'Любой тип', route.REQUEST_TYPE || '');
    const routeCategoryOptions = optionsWithEmpty(data.item_categories || dict.item_categories, 'Любой вид строки', route.ITEM_CATEGORY || '');
    box.innerHTML = `
        <div class="admin-layout">
            <div class="stack">
                <div class="admin-box">
                    <h2>Назначение роли</h2>
                    <label>Роль</label><select id="admRole">${roleOptions}</select>
                    <label>Поиск сотрудника</label>
                    <div class="grid">
                        <div class="col-8"><input id="admUserSearch" type="text" placeholder="ФИО, email, логин или ID"></div>
                        <div class="col-4"><button type="button" class="light" id="admFindUser">Найти</button></div>
                    </div>
                    <div class="actions"><button type="button" class="light" id="admSelectUserBx">Показать сотрудников</button></div>
                    <div id="admUserResults" class="user-search-results"></div>
                    <div id="admUserSummary" class="muted"></div>
                    <label>ID пользователя Bitrix</label><input id="admUserId" type="number" min="1" readonly>
                    <label>ФИО</label><input id="admUserName" type="text">
                    <label>Компания</label><select id="admCompany">${assignmentCompanyOptions}</select>
                    <label>Площадка</label><select id="admSite">${assignmentSiteOptions}</select>
                    <label>Подразделение</label><select id="admDepartment">${assignmentDepartmentOptions}</select>
                    <label>Должность</label><input id="admPosition" type="text">
                    <h2>Замещающий</h2>
                    <label>Поиск замещающего</label>
                    <div class="grid">
                        <div class="col-8"><input id="admSubUserSearch" type="text" placeholder="ФИО, email, логин или ID"></div>
                        <div class="col-4"><button type="button" class="light" id="admSubFindUser">Найти</button></div>
                    </div>
                    <div class="actions"><button type="button" class="light" id="admSubSelectUserBx">Показать сотрудников</button><button type="button" class="light" id="admClearSubstitute">Очистить</button></div>
                    <div id="admSubUserResults" class="user-search-results"></div>
                    <div id="admSubUserSummary" class="muted"></div>
                    <label>ID замещающего</label><input id="admSubUserId" type="number" min="1" readonly>
                    <label>ФИО замещающего</label><input id="admSubUserName" type="text">
                    <label>Подразделение замещающего</label><select id="admSubDepartment">${assignmentDepartmentOptions}</select>
                    <label>Должность замещающего</label><input id="admSubPosition" type="text">
                    <div class="field-hint">Замещающий увидит задания и сможет действовать только если основной сотрудник сейчас указан в графике отсутствий Bitrix.</div>
                    <div class="actions"><button type="button" id="saveAssignment">Сохранить назначение</button></div>
                </div>
                <div class="admin-box">
                    <h2>Маршрут</h2>
                    <label>Выбранный маршрут</label><select id="routeSelector">${routeOptions}</select>
                    <label>Шаблон маршрута</label><select id="routePreset">${presetOptions}</select>
                    <div class="actions"><button type="button" class="light" id="applyRoutePreset">Применить шаблон</button><button type="button" class="light" id="installRoutePresets">Завести типовые маршруты</button></div>
                    <input type="hidden" id="routeId" value="${escapeHtml(route.ID || 0)}">
                    <div class="grid">
                        <div class="col-8"><label>Название</label><input id="routeTitle" value="${escapeHtml(route.TITLE || 'Базовый маршрут')}"></div>
                        <div class="col-4"><label>Сортировка</label><input id="routeSort" type="number" value="${escapeHtml(route.SORT || 100)}"></div>
                        <div class="col-6"><label>Компания</label><select id="routeCompany">${routeCompanyOptions}</select></div>
                        <div class="col-6"><label>Площадка</label><select id="routeSite">${routeSiteOptions}</select></div>
                        <div class="col-6"><label>Тип заявки</label><select id="routeRequestType">${routeRequestTypeOptions}</select></div>
                        <div class="col-6"><label>Минимальная сумма</label><input id="routeMinAmount" type="number" min="0" step="0.01" value="${escapeHtml(route.MIN_AMOUNT || '')}"></div>
                        <div class="col-6"><label>Максимальная сумма</label><input id="routeMaxAmount" type="number" min="0" step="0.01" value="${escapeHtml(route.MAX_AMOUNT || '')}"></div>
                        <div class="col-6"><label>Должность/подразделение инициатора содержит</label><input id="routeInitiatorPosition" value="${escapeHtml(route.INITIATOR_POSITION || '')}"></div>
                        <div class="col-6"><label>Вид строки</label><select id="routeItemCategory">${routeCategoryOptions}</select></div>
                    </div>
                    <label><input id="routeActive" type="checkbox" ${route.IS_ACTIVE === 'N' ? '' : 'checked'} style="width:auto"> Активен</label>
                    <label>Шаги маршрута JSON</label><textarea id="routeSteps" class="json-area">${escapeHtml(JSON.stringify(route.STEPS || data.default_steps || [], null, 2))}</textarea>
                    <div class="actions"><button type="button" id="saveRoute">Сохранить маршрут</button><button type="button" class="light" id="newRoute">Новый маршрут</button><button type="button" class="danger" id="deleteRoute" ${route.ID ? '' : 'disabled'}>Удалить маршрут</button></div>
                </div>
            </div>
            <div>
                <h2>Текущие назначения</h2>
                <div class="table-wrap"><table><thead><tr><th>Роль</th><th>Пользователь</th><th>Замещающий</th><th>Компания</th><th>Площадка</th><th>Подразделение</th><th>Должность</th><th></th></tr></thead><tbody>
                    ${(data.assignments || []).map(row => `<tr>
                        <td data-label="Роль">${escapeHtml(data.roles[row.ROLE_CODE] || row.ROLE_CODE)}</td>
                        <td data-label="Пользователь">#${escapeHtml(row.USER_ID)} ${escapeHtml(row.USER_NAME || '')}</td>
                        <td data-label="Замещающий">${row.SUBSTITUTE_USER_ID > 0 ? '#' + escapeHtml(row.SUBSTITUTE_USER_ID) + ' ' + escapeHtml(row.SUBSTITUTE_USER_NAME || '') : ''}</td>
                        <td data-label="Компания">${escapeHtml((data.companies || {})[row.COMPANY_KEY]?.name || row.COMPANY_KEY || 'Все')}</td>
                        <td data-label="Площадка">${escapeHtml(siteNameByKey(row.SITE_KEY) || 'Все')}</td>
                        <td data-label="Подразделение">${escapeHtml(row.DEPARTMENT_NAME || 'Все')}</td>
                        <td data-label="Должность">${escapeHtml(row.POSITION_NAME || '')}</td>
                        <td data-label=""><button type="button" class="danger" data-delete-assignment="${escapeHtml(row.ID)}">Удалить</button></td>
                    </tr>`).join('')}
                </tbody></table></div>
            </div>
        </div>`;
}

function bindEvents() {
    bindTabs();
    document.getElementById('companyKey').addEventListener('change', () => { renderSiteOptions(''); renderDepartmentOptions(''); renderInitiatorProfiles(); });
    document.getElementById('siteKey').addEventListener('change', () => { renderDepartmentOptions(''); renderInitiatorProfiles(); });
    document.getElementById('initiatorProfile').addEventListener('change', applyInitiatorProfile);
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
    document.getElementById('resetRequest').addEventListener('click', () => { document.getElementById('requestForm').reset(); document.getElementById('requestId').value = ''; document.getElementById('companyKey').value = firstCompanyKey(); document.getElementById('attachmentsInput').value = ''; currentItems = []; selectedFiles = []; fillDictionaries(); renderSelectedFiles(); renderExistingAttachments([]); renderRoute([]); renderTimeline([]); });
    document.getElementById('refreshRequests').addEventListener('click', () => loadRequests().catch(err => showNotice(err.message, 'error')));
    document.getElementById('refreshTasks').addEventListener('click', () => loadTasks().catch(err => showNotice(err.message, 'error')));
    document.getElementById('refreshAllRequests').addEventListener('click', () => loadAllRequests().catch(err => showNotice(err.message, 'error')));
    document.getElementById('allFilters').addEventListener('submit', e => { e.preventDefault(); loadAllRequests().catch(err => showNotice(err.message, 'error')); });
    document.getElementById('clearAllFilters').addEventListener('click', () => { document.getElementById('allFilters').reset(); renderAllSiteFilterOptions(''); loadAllRequests().catch(err => showNotice(err.message, 'error')); });
    document.getElementById('allCompany').addEventListener('change', () => renderAllSiteFilterOptions(''));
    document.getElementById('refreshAdmin').addEventListener('click', () => loadAdmin().catch(err => showNotice(err.message, 'error')));
    document.body.addEventListener('keydown', e => {
        if (e.target.id === 'admUserSearch' && e.key === 'Enter') {
            e.preventDefault();
            searchAdminUsers().catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'admSubUserSearch' && e.key === 'Enter') {
            e.preventDefault();
            searchAdminUsers('substitute').catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.closest('[data-delegate-search]') && e.key === 'Enter') {
            e.preventDefault();
            searchTaskDelegates(e.target.closest('.task')).catch(err => showNotice(err.message, 'error'));
        }
    });
    document.body.addEventListener('change', e => {
        if (e.target.id === 'routeSelector') {
            fillRouteForm(selectedRoute());
        }
        if (e.target.id === 'admCompany') {
            updateAdminSiteOptions('');
        }
        if (e.target.id === 'routeCompany') {
            updateRouteSiteOptions('');
        }
    });
    document.body.addEventListener('click', e => {
        const open = e.target.closest('[data-open-request]');
        if (open) openRequest(open.dataset.openRequest).catch(err => showNotice(err.message, 'error'));
        const saveChecklist = e.target.closest('[data-save-checklist]');
        if (saveChecklist) saveTaskChecklist(saveChecklist.closest('.task')).catch(err => showNotice(err.message, 'error'));
        const searchDelegate = e.target.closest('[data-search-delegate]');
        if (searchDelegate) searchTaskDelegates(searchDelegate.closest('.task')).catch(err => showNotice(err.message, 'error'));
        const delegateUser = e.target.closest('[data-delegate-user-id]');
        if (delegateUser) delegateTask(delegateUser.closest('.task'), delegateUser.dataset.delegateUserId).catch(err => showNotice(err.message, 'error'));
        const decision = e.target.closest('[data-decision]');
        if (decision) sendDecision(decision.closest('.task'), decision.dataset.decision).catch(err => showNotice(err.message, 'error'));
        const del = e.target.closest('[data-delete-assignment]');
        if (del) api('admin.php', {method:'POST', body:{action:'delete_assignment', id:Number(del.dataset.deleteAssignment)}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        const foundUser = e.target.closest('[data-admin-user]');
        if (foundUser) {
            try {
                selectAdminUser(JSON.parse(foundUser.dataset.adminUser));
            } catch (err) {
                showNotice('Не удалось прочитать выбранного пользователя.', 'error');
            }
        }
        const foundSubUser = e.target.closest('[data-admin-sub-user]');
        if (foundSubUser) {
            try {
                selectAdminSubstitute(JSON.parse(foundSubUser.dataset.adminSubUser));
            } catch (err) {
                showNotice('Не удалось прочитать выбранного замещающего.', 'error');
            }
        }
        if (e.target.id === 'admFindUser') {
            searchAdminUsers().catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'admSubFindUser') {
            searchAdminUsers('substitute').catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'admSelectUserBx') {
            searchAdminUsers().catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'admSubSelectUserBx') {
            searchAdminUsers('substitute').catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'admClearSubstitute') {
            clearAdminSubstitute();
        }
        if (e.target.id === 'saveAssignment') {
            api('admin.php', {method:'POST', body:{action:'save_assignment', assignment:{
                role_code: document.getElementById('admRole').value,
                user_id: Number(document.getElementById('admUserId').value || 0),
                user_name: document.getElementById('admUserName').value,
                company_key: document.getElementById('admCompany').value,
                site_key: document.getElementById('admSite').value,
                department_name: document.getElementById('admDepartment').value,
                position_name: document.getElementById('admPosition').value,
                substitute_user_id: Number(document.getElementById('admSubUserId').value || 0),
                substitute_user_name: document.getElementById('admSubUserName').value,
                substitute_department_name: document.getElementById('admSubDepartment').value,
                substitute_position_name: document.getElementById('admSubPosition').value
            }}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'newRoute') {
            fillRouteForm({ID:0, TITLE:'Новый маршрут', SORT:100, COMPANY_KEY:'', SITE_KEY:'', IS_ACTIVE:'Y', STEPS:adminCache?.default_steps || []});
        }
        if (e.target.id === 'applyRoutePreset') {
            const code = document.getElementById('routePreset').value;
            const preset = (adminCache?.route_presets || []).find(item => item.code === code);
            if (!preset) {
                showNotice('Выберите шаблон маршрута.', 'error');
                return;
            }
            fillRouteForm({...preset, ID:0, IS_ACTIVE:'Y'});
        }
        if (e.target.id === 'installRoutePresets') {
            api('admin.php', {method:'POST', body:{action:'install_route_presets'}}).then(data => {
                showNotice((data.installed_ids || []).length ? 'Типовые маршруты заведены.' : 'Типовые маршруты уже есть.', 'success');
                return loadAdmin();
            }).catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'deleteRoute') {
            const id = Number(document.getElementById('routeId').value || 0);
            if (!id) return;
            if (!confirm('Удалить выбранный маршрут? Активные заявки сохранят уже назначенный маршрут.')) return;
            api('admin.php', {method:'POST', body:{action:'delete_route', id}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        }
        if (e.target.id === 'saveRoute') {
            let steps;
            try { steps = JSON.parse(document.getElementById('routeSteps').value); } catch (err) { showNotice('Некорректный JSON маршрута.', 'error'); return; }
            api('admin.php', {method:'POST', body:{action:'save_route', route:{
                id: Number(document.getElementById('routeId').value || 0),
                title: document.getElementById('routeTitle').value,
                sort: Number(document.getElementById('routeSort').value || 100),
                company_key: document.getElementById('routeCompany').value,
                site_key: document.getElementById('routeSite').value,
                request_type: document.getElementById('routeRequestType').value,
                min_amount: document.getElementById('routeMinAmount').value,
                max_amount: document.getElementById('routeMaxAmount').value,
                initiator_position: document.getElementById('routeInitiatorPosition').value,
                item_category: document.getElementById('routeItemCategory').value,
                is_active: document.getElementById('routeActive').checked ? 'Y' : 'N',
                steps
            }}}).then(loadAdmin).catch(err => showNotice(err.message, 'error'));
        }
    });
}

async function init() {
    bindEvents();
    const hasBx24Auth = await ensureBx24Auth(false);
    const data = await api('bootstrap.php');
    apiMode = data.api_mode || (String(data.user?.source || '').indexOf('bitrix_session') !== -1 ? 'direct' : 'proxy');
    dict = data.dictionaries || dict;
    currentUser = data.user || {};
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
