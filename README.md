# Заявки на закупку

Отдельное локальное приложение Bitrix24 для создания, согласования, регистрации и контроля заявок на закупку ТМЦ, работ и услуг.

## Размещение

Целевая папка на портале:

```text
/home/bitrix/www/local/purchase_requests/
```

Основные URL:

```text
/local/purchase_requests/index.php
/local/purchase_requests/install.php
/local/purchase_requests/api/health.php
```

В настройках локального приложения Bitrix24:

```text
Название: Заявки на закупку
Путь обработчика: /local/purchase_requests/index.php
Путь установки: /local/purchase_requests/install.php
```

## Архитектура

Bitrix24 используется как точка входа, источник пользователя и канал личных сообщений. Вся прикладная логика заявок, маршрутов, ролей, решений, версий и аудита хранится в собственных таблицах `b_pr_*`.

Подход выбран гибридный:

- авторизация через Bitrix24/REST;
- самописный workflow внутри приложения;
- назначение ролей и маршруты через административный интерфейс;
- уведомления в личные сообщения от пользователя `ID=1`;
- дальнейшая интеграция с бизнес-процессами Bitrix возможна отдельными bridge-методами.

## Первичный запуск

1. Скопировать папку в `/local/purchase_requests/`.
2. Открыть `/local/purchase_requests/install.php` как локальное приложение Bitrix24.
3. Открыть `/local/purchase_requests/index.php`.
4. Первый пользователь-администратор задаётся в `config.php` через `PR_ADMIN_USER_IDS`.
5. В разделе администрирования назначить роли по площадкам.

Таблицы создаются лениво при первом обращении к API.

Если API сообщает `Не удалось создать API job`, откройте:

```text
/local/purchase_requests/api/health.php
```

Runtime должен писать во внутреннюю папку приложения или fallback `/upload/purchase_requests_runtime/`.

## MVP-статусы

```text
DRAFT
WAREHOUSE
APPROVAL
APPROVED
REGISTRATION
REGISTERED
SUPPLY
IN_PROGRESS
REJECTED
REVISION
CANCELLED
DONE
```

## Основные роли

```text
initiator
warehouse
profile_approver
chief_engineer
director
expense_control
registrar
supply
process_admin
observer
```

Фамилии не зашиваются в код. Пользователи назначаются на роли через таблицу `b_pr_role_assignments` и административный UI.
