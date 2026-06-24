<?php

const PR_APP_TITLE = 'Заявки на закупку';
const PR_APP_CODE = 'purchase_requests';

/*
 * Пользователь, от имени которого приложение отправляет личные сообщения.
 * По требованию проекта стартовое значение: ID=1.
 */
const PR_NOTIFY_FROM_USER_ID = 1;

/*
 * Первый административный доступ. После запуска администраторов процесса можно
 * назначать через интерфейс, роль process_admin.
 */
const PR_ADMIN_USER_IDS = [1];
const PR_ADMIN_GROUP_IDS = [];

const PR_DEFAULT_CURRENCY = 'RUB';
const PR_MAX_UPLOAD_FILE_SIZE = 52428800;

function prAllowedFileExtensions(): array
{
    return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'zip', 'rar', '7z'];
}

function prCompanies(): array
{
    return [
        'egida_plus' => [
            'name' => 'Эгида +',
            'sites' => [
                'krasnokakshayskaya_47' => 'Краснокакшайская 47',
                'krasnoborskaya_2' => 'Красноборская д. 2',
                'avtoservisnaya' => 'Автосервисная',
            ],
        ],
    ];
}

function prRequestTypes(): array
{
    return [
        'goods' => 'ТМЦ',
        'work' => 'Работа',
        'service' => 'Услуга',
        'mixed' => 'Смешанная',
    ];
}

function prItemCategories(): array
{
    return [
        'goods' => 'Товар',
        'work' => 'Работа',
        'service' => 'Услуга',
    ];
}

function prUnits(): array
{
    return [
        'pcs' => 'шт.',
        'kg' => 'кг',
        'm' => 'м',
        'm2' => 'м2',
        'm3' => 'м3',
        'service' => 'услуга',
        'set' => 'комплект',
    ];
}

function prRoleLabels(): array
{
    return [
        'initiator' => 'Инициатор',
        'warehouse' => 'Склад',
        'profile_approver' => 'Профильный согласующий',
        'chief_engineer' => 'Главный инженер',
        'director' => 'Директор / Президент',
        'expense_control' => 'Контроль расходов',
        'registrar' => 'Регистратор',
        'supply' => 'Снабжение',
        'process_admin' => 'Администратор процесса',
        'observer' => 'Наблюдатель',
    ];
}

function prStatusLabels(): array
{
    return [
        'DRAFT' => 'Черновик',
        'WAREHOUSE' => 'Ожидает проверки склада',
        'APPROVAL' => 'На согласовании',
        'APPROVED' => 'Согласована',
        'REGISTRATION' => 'Ожидает регистрации',
        'REGISTERED' => 'Зарегистрирована',
        'SUPPLY' => 'Передана в снабжение',
        'IN_PROGRESS' => 'Принята в работу',
        'REJECTED' => 'Отклонена',
        'REVISION' => 'На доработке',
        'CANCELLED' => 'Аннулирована',
        'DONE' => 'Завершена',
    ];
}

function prDefaultRouteSteps(): array
{
    return [
        ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
        ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
        ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Техническое согласование', 'status' => 'APPROVAL'],
        ['code' => 'director', 'role' => 'director', 'title' => 'Утверждение', 'status' => 'APPROVAL'],
        ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация', 'status' => 'REGISTRATION'],
        ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
    ];
}
