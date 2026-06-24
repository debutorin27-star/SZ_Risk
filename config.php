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
const PR_EXPENSIVE_AMOUNT_LIMIT = 1000000;
const PR_AVTOSERVICE_OKS_PRESIDENT_LIMIT = 200000;

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

function prSites(): array
{
    $companies = prCompanies();
    return $companies['egida_plus']['sites'] ?? [];
}

function prInitiatorProfiles(): array
{
    return [
        'krasnoborskaya_2' => [
            ['department' => 'Отдел главного механика', 'position' => 'Главный механик', 'label' => 'Главный механик'],
            ['department' => 'Заводоуправление', 'position' => 'Заместитель главного инженера', 'label' => 'Заместитель главного инженера'],
            ['department' => 'Транспортный участок', 'position' => 'Заведующий гаражом', 'label' => 'Заведующий гаражом'],
            ['department' => 'Административно-хозяйственная служба', 'position' => 'Заведующий АХО', 'label' => 'Заведующий АХО'],
            ['department' => 'Отдел главного энергетика', 'position' => 'Главный энергетик', 'label' => 'Главный энергетик'],
            ['department' => 'Производство', 'position' => 'Начальник производства', 'label' => 'Начальник производства'],
            ['department' => 'Технологическая служба', 'position' => 'Главный технолог', 'label' => 'Главный технолог'],
            ['department' => 'Служба охраны труда', 'position' => 'Специалист по охране труда', 'label' => 'Специалист по охране труда'],
            ['department' => 'Заводоуправление', 'position' => 'Секретарь', 'label' => 'Секретарь'],
            ['department' => 'Конструкторский отдел', 'position' => 'Главный конструктор', 'label' => 'Главный конструктор'],
            ['department' => 'РММ', 'position' => 'Начальник РММ', 'label' => 'Начальник РММ'],
            ['department' => 'ОТК', 'position' => 'Начальник ОТК', 'label' => 'Начальник ОТК'],
            ['department' => 'ОВиИ', 'position' => 'Начальник ОВиИ', 'label' => 'Начальник ОВиИ'],
            ['department' => 'Производственная группа', 'position' => 'Начальник производственной группы', 'label' => 'Начальник производственной группы'],
        ],
        'avtoservisnaya' => [
            ['department' => 'Материальная база', 'position' => 'Механик', 'label' => 'Механик'],
            ['department' => 'Материальная база', 'position' => 'Энергетик', 'label' => 'Энергетик'],
            ['department' => 'Материальная база', 'position' => 'Кладовщик', 'label' => 'Кладовщик'],
            ['department' => 'ОКС', 'position' => 'Начальник ОКС', 'label' => 'Начальник ОКС'],
            ['department' => 'Клеевой участок', 'position' => 'Начальник клеевого участка', 'label' => 'Начальник клеевого участка'],
        ],
        'krasnokakshayskaya_47' => [
            ['department' => 'Отдел автоматизации', 'position' => 'Начальник отдела автоматизации', 'label' => 'Начальник отдела автоматизации'],
            ['department' => 'Заводоуправление', 'position' => 'Секретарь', 'label' => 'Секретарь'],
            ['department' => 'Административно-хозяйственная служба', 'position' => 'Заведующий АХО', 'label' => 'Заведующий АХО'],
            ['department' => 'Отдел маркетинга', 'position' => 'Начальник отдела маркетинга', 'label' => 'Начальник отдела маркетинга'],
            ['department' => 'Служба безопасности', 'position' => 'Начальник службы безопасности', 'label' => 'Начальник службы безопасности'],
        ],
    ];
}

function prInitiatorDepartments(): array
{
    $departments = [];
    foreach (prInitiatorProfiles() as $profiles) {
        foreach ($profiles as $profile) {
            $department = (string)($profile['department'] ?? '');
            if ($department !== '') {
                $departments[$department] = $department;
            }
        }
    }
    ksort($departments);
    return array_values($departments);
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
        'director' => 'Директор',
        'president' => 'Президент',
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

function prRoutePresets(): array
{
    return [
        [
            'code' => 'krasnoborskaya_standard',
            'title' => 'Красноборская: стандартный маршрут',
            'sort' => 100,
            'company_key' => 'egida_plus',
            'site_key' => 'krasnoborskaya_2',
            'request_type' => '',
            'min_amount' => '',
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Согласование главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'director', 'role' => 'director', 'title' => 'Утверждение директором завода', 'status' => 'APPROVAL'],
                ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация секретарем', 'status' => 'REGISTRATION'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
            ],
        ],
        [
            'code' => 'avtoservisnaya_standard',
            'title' => 'Автосервисная: стандартный маршрут',
            'sort' => 110,
            'company_key' => 'egida_plus',
            'site_key' => 'avtoservisnaya',
            'request_type' => '',
            'min_amount' => '',
            'max_amount' => PR_AVTOSERVICE_OKS_PRESIDENT_LIMIT,
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Утверждение главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация', 'status' => 'REGISTRATION'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
            ],
        ],
        [
            'code' => 'avtoservisnaya_oks_president',
            'title' => 'Автосервисная: ОКС работы свыше 200 000',
            'sort' => 90,
            'company_key' => 'egida_plus',
            'site_key' => 'avtoservisnaya',
            'request_type' => 'work',
            'min_amount' => PR_AVTOSERVICE_OKS_PRESIDENT_LIMIT + 0.01,
            'max_amount' => '',
            'initiator_position' => 'ОКС',
            'item_category' => '',
            'steps' => [
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Согласование главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'president_approval', 'role' => 'president', 'title' => 'Утверждение Президентом', 'status' => 'APPROVAL'],
                ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация', 'status' => 'REGISTRATION'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
            ],
        ],
        [
            'code' => 'krasnokakshayskaya_expense_control',
            'title' => 'Краснококшайская: контроль расходов',
            'sort' => 120,
            'company_key' => 'egida_plus',
            'site_key' => 'krasnokakshayskaya_47',
            'request_type' => '',
            'min_amount' => '',
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'expense_control', 'role' => 'expense_control', 'title' => 'Контроль расходов', 'status' => 'APPROVAL'],
                ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация', 'status' => 'REGISTRATION'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
            ],
        ],
        [
            'code' => 'expensive_president',
            'title' => 'Дорогостоящая заявка: Президент',
            'sort' => 80,
            'company_key' => 'egida_plus',
            'site_key' => '',
            'request_type' => '',
            'min_amount' => PR_EXPENSIVE_AMOUNT_LIMIT,
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Техническое согласование', 'status' => 'APPROVAL'],
                ['code' => 'president_approval', 'role' => 'president', 'title' => 'Утверждение Президентом', 'status' => 'APPROVAL'],
                ['code' => 'registration', 'role' => 'registrar', 'title' => 'Регистрация', 'status' => 'REGISTRATION'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Передача в снабжение', 'status' => 'SUPPLY'],
            ],
        ],
    ];
}
