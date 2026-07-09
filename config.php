<?php

const PR_APP_TITLE = 'Заявки на закупку';
const PR_APP_CODE = 'purchase_requests';

/*
 * Пользователь, от имени которого приложение отправляет личные сообщения.
 * По требованию проекта стартовое значение: ID=1.
 */
const PR_NOTIFY_FROM_USER_ID = 1;
const PR_NOTIFY_MARKETPLACE_APP_ID = 49;

/*
 * Первый административный доступ. После запуска администраторов процесса можно
 * назначать через интерфейс, роль process_admin.
 */
const PR_ADMIN_USER_IDS = [1];
const PR_ADMIN_GROUP_IDS = [];

const PR_DEFAULT_CURRENCY = 'RUB';
const PR_MAX_UPLOAD_FILE_SIZE = 52428800;
const PR_EXPENSIVE_AMOUNT_LIMIT = 1000000;
const PR_AVTOSERVICE_OKS_LIMIT = 200000;
const PR_COMPUTERS_EXPENSE_CONTROL_LIMIT = 100000;

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

function prDefaultCompanyKey(): string
{
    $keys = array_keys(prCompanies());
    return (string)($keys[0] ?? 'egida_plus');
}

function prSites(string $companyKey = ''): array
{
    $companies = prCompanies();
    if ($companyKey !== '') {
        return $companies[$companyKey]['sites'] ?? [];
    }

    $sites = [];
    foreach ($companies as $company) {
        foreach (($company['sites'] ?? []) as $siteKey => $siteName) {
            $sites[$siteKey] = $siteName;
        }
    }
    return $sites;
}

function prSitesByCompany(): array
{
    $sites = [];
    foreach (prCompanies() as $companyKey => $company) {
        $sites[$companyKey] = $company['sites'] ?? [];
    }
    return $sites;
}

function prKazanInitiatorProfiles(): array
{
    return [
        ['department' => 'Директор завода', 'position' => 'Директор завода', 'label' => 'Директор завода'],
        ['department' => 'Главный инженер', 'position' => 'Главный инженер', 'label' => 'Главный инженер'],
        ['department' => 'Начальник производства', 'position' => 'Начальник производства', 'label' => 'Начальник производства'],
        ['department' => 'Отдел автоматизации', 'position' => 'Начальник отдела', 'label' => 'Отдел автоматизации / Начальник отдела'],
        ['department' => 'Управление по контролю за расходованием денежных средств и финансовыми рисками', 'position' => 'Начальник управления', 'label' => 'Контроль расходов / Начальник управления'],
        ['department' => 'Отдел снабжения', 'position' => 'Начальник отдела', 'label' => 'Отдел снабжения / Начальник отдела'],
        ['department' => 'Погрузочно-разгрузочный терминал', 'position' => 'Заведующий складом', 'label' => 'Склад / Заведующий складом'],
        ['department' => 'Технологический отдел', 'position' => 'Главный технолог', 'label' => 'Технологический отдел / Главный технолог'],
        ['department' => 'Отдел по подготовке производства и логистике', 'position' => 'Начальник отдела', 'label' => 'Подготовка производства и логистика / Начальник отдела'],
        ['department' => 'Отдел капитального строительства', 'position' => 'Начальник отдела', 'label' => 'ОКС / Начальник отдела'],
        ['department' => 'Отдел по промышленному инжинирингу и развитию производства', 'position' => 'Начальник отдела', 'label' => 'Промышленный инжиниринг / Начальник отдела'],
        ['department' => 'Административно-хозяйственная служба', 'position' => '', 'label' => 'Административно-хозяйственная служба'],
    ];
}

function prInitiatorProfiles(): array
{
    return [
        'krasnoborskaya_2' => array_merge([
            ['department' => 'Отдел автоматизации', 'position' => '', 'label' => 'Отдел автоматизации'],
            ['department' => 'Отдел автоматизации', 'position' => 'Начальник отдела автоматизации', 'label' => 'Начальник отдела автоматизации'],
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
        ], prKazanInitiatorProfiles()),
        'avtoservisnaya' => array_merge([
            ['department' => 'Отдел автоматизации', 'position' => '', 'label' => 'Отдел автоматизации'],
            ['department' => 'Отдел автоматизации', 'position' => 'Начальник отдела автоматизации', 'label' => 'Начальник отдела автоматизации'],
            ['department' => 'Материальная база', 'position' => 'Механик', 'label' => 'Механик'],
            ['department' => 'Материальная база', 'position' => 'Энергетик', 'label' => 'Энергетик'],
            ['department' => 'Материальная база', 'position' => 'Кладовщик', 'label' => 'Кладовщик'],
            ['department' => 'ОКС', 'position' => 'Начальник ОКС', 'label' => 'Начальник ОКС'],
            ['department' => 'Клеевой участок', 'position' => 'Начальник клеевого участка', 'label' => 'Начальник клеевого участка'],
        ], prKazanInitiatorProfiles()),
        'krasnokakshayskaya_47' => array_merge([
            ['department' => 'Отдел автоматизации', 'position' => '', 'label' => 'Отдел автоматизации'],
            ['department' => 'Отдел автоматизации', 'position' => 'Начальник отдела автоматизации', 'label' => 'Начальник отдела автоматизации'],
            ['department' => 'Заводоуправление', 'position' => 'Секретарь', 'label' => 'Секретарь'],
            ['department' => 'Административно-хозяйственная служба', 'position' => 'Заведующий АХО', 'label' => 'Заведующий АХО'],
            ['department' => 'Отдел маркетинга', 'position' => 'Начальник отдела маркетинга', 'label' => 'Начальник отдела маркетинга'],
            ['department' => 'Служба безопасности', 'position' => 'Начальник службы безопасности', 'label' => 'Начальник службы безопасности'],
        ], prKazanInitiatorProfiles()),
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
        'raw_materials' => 'Сырье',
        'computers' => 'Компьютеры',
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
        'production_chief' => 'Директор завода',
        'automation_head' => 'Начальник отдела автоматизации',
        'director' => 'Директор',
        'expense_control' => 'Контроль расходов',
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
        'EXECUTION' => 'В исполнении',
        'ACCEPTANCE' => 'Ожидает приемки инициатором',
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
        ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
        ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Техническое согласование', 'status' => 'APPROVAL'],
        ['code' => 'director', 'role' => 'director', 'title' => 'Утверждение', 'status' => 'APPROVAL'],
        ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
        ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
        ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
    ];
}

function prSupplyChecklistLabels(): array
{
    return [
        'processed_1c' => 'Заявка обработана и внесена в 1С',
        'contractor_details' => 'Реквизиты контрагента внесены',
        'paid_waiting_delivery' => 'Оплачено, ожидаем поставку',
        'transferred_to_initiator' => 'Передана инициатору',
    ];
}

function prTaskChecklistLabels(string $roleCode, string $requestType = '', string $stepCode = ''): array
{
    if ($requestType === 'computers') {
        if ($roleCode === 'automation_head' && $stepCode !== 'automation_approval') {
            return prSupplyChecklistLabels();
        }
        return [];
    }

    return $roleCode === 'supply' ? prSupplyChecklistLabels() : [];
}

function prTaskChecklistTitle(string $roleCode, string $requestType = '', string $stepCode = ''): string
{
    if ($requestType === 'computers' && $roleCode === 'automation_head') {
        return 'Чек-лист отдела автоматизации';
    }

    if ($roleCode === 'supply') {
        return 'Чек-лист снабжения';
    }

    return 'Чек-лист исполнения';
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
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Согласование главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'director', 'role' => 'director', 'title' => 'Утверждение директором завода', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
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
            'max_amount' => PR_AVTOSERVICE_OKS_LIMIT,
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Утверждение главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
        [
            'code' => 'avtoservisnaya_oks',
            'title' => 'Автосервисная: ОКС работы свыше 200 000',
            'sort' => 90,
            'company_key' => 'egida_plus',
            'site_key' => 'avtoservisnaya',
            'request_type' => 'work',
            'min_amount' => PR_AVTOSERVICE_OKS_LIMIT + 0.01,
            'max_amount' => '',
            'initiator_position' => 'ОКС',
            'item_category' => '',
            'steps' => [
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Согласование главным инженером', 'status' => 'APPROVAL'],
                ['code' => 'director', 'role' => 'director', 'title' => 'Утверждение директором', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
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
                ['code' => 'expense_control', 'role' => 'expense_control', 'title' => 'Контроль расходов', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
        [
            'code' => 'expensive_expense_control',
            'title' => 'Дорогостоящая заявка: контроль расходов',
            'sort' => 80,
            'company_key' => 'egida_plus',
            'site_key' => '',
            'request_type' => '',
            'min_amount' => PR_EXPENSIVE_AMOUNT_LIMIT,
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
                ['code' => 'chief_engineer', 'role' => 'chief_engineer', 'title' => 'Техническое согласование', 'status' => 'APPROVAL'],
                ['code' => 'expense_control', 'role' => 'expense_control', 'title' => 'Контроль расходов', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
        [
            'code' => 'raw_materials',
            'title' => 'Сырье: профильный согласующий и директор завода',
            'sort' => 70,
            'company_key' => 'egida_plus',
            'site_key' => '',
            'request_type' => 'raw_materials',
            'min_amount' => '',
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'profile_approval', 'role' => 'profile_approver', 'title' => 'Профильное согласование', 'status' => 'APPROVAL'],
                ['code' => 'plant_director', 'role' => 'director', 'title' => 'Согласование директором завода', 'status' => 'APPROVAL'],
                ['code' => 'warehouse', 'role' => 'warehouse', 'title' => 'Проверка склада', 'status' => 'WAREHOUSE'],
                ['code' => 'supply', 'role' => 'supply', 'title' => 'Задача снабжению', 'status' => 'SUPPLY'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
        [
            'code' => 'computers',
            'title' => 'Компьютеры: отдел автоматизации',
            'sort' => 60,
            'company_key' => 'egida_plus',
            'site_key' => '',
            'request_type' => 'computers',
            'min_amount' => '',
            'max_amount' => PR_COMPUTERS_EXPENSE_CONTROL_LIMIT,
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'automation_approval', 'role' => 'automation_head', 'title' => 'Согласование начальником отдела автоматизации', 'status' => 'APPROVAL'],
                ['code' => 'automation_execution', 'role' => 'automation_head', 'title' => 'Исполнение заявки отделом автоматизации', 'status' => 'EXECUTION'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
        [
            'code' => 'computers_expense_control',
            'title' => 'Компьютеры свыше 100 000: отдел автоматизации и контроль расходов',
            'sort' => 50,
            'company_key' => 'egida_plus',
            'site_key' => '',
            'request_type' => 'computers',
            'min_amount' => PR_COMPUTERS_EXPENSE_CONTROL_LIMIT + 0.01,
            'max_amount' => '',
            'initiator_position' => '',
            'item_category' => '',
            'steps' => [
                ['code' => 'automation_approval', 'role' => 'automation_head', 'title' => 'Согласование начальником отдела автоматизации', 'status' => 'APPROVAL'],
                ['code' => 'expense_control', 'role' => 'expense_control', 'title' => 'Контроль расходов', 'status' => 'APPROVAL'],
                ['code' => 'automation_execution', 'role' => 'automation_head', 'title' => 'Исполнение заявки отделом автоматизации', 'status' => 'EXECUTION'],
                ['code' => 'initiator_acceptance', 'role' => 'initiator', 'title' => 'Приемка выполнения инициатором', 'status' => 'ACCEPTANCE'],
            ],
        ],
    ];
}
