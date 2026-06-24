<?php

require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/crest.php';

$_REQUEST += [
    'event' => '',
    'PLACEMENT' => '',
];

$settingsFile = __DIR__ . '/settings.json';
if (!file_exists($settingsFile)) {
    @file_put_contents($settingsFile, '{}');
}
@chmod($settingsFile, 0664);

$result = CRest::installApp();
$isInstallFrame = ($result['rest_only'] === false);

prLog('install', [
    'request_keys' => array_keys($_REQUEST),
    'result' => $result,
    'settings_writable' => is_writable($settingsFile) ? 'Y' : 'N',
]);

if ($isInstallFrame): ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <script src="//api.bitrix24.com/api/v1/"></script>
        <script>
            BX24.init(function () {
                BX24.installFinish();
            });
        </script>
    </head>
    <body>installation has been finished</body>
    </html>
<?php endif; ?>
