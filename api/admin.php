<?php

require_once __DIR__ . '/common.php';

$user = prRequireApiUser();
prRequireAdmin($user);

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = prReadJsonBody();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? $body['action'] ?? 'list');

try {
    if ($method === 'GET' || $action === 'list') {
        prApiResponse(true, [
            'assignments' => prFetchRoleAssignments(),
            'routes' => prFetchRouteRules(),
            'roles' => prRoleLabels(),
            'companies' => prCompanies(),
            'sites' => prSites(),
            'sites_by_company' => prSitesByCompany(),
            'initiator_departments' => prInitiatorDepartments(),
            'request_types' => prRequestTypes(),
            'item_categories' => prItemCategories(),
            'default_steps' => prDefaultRouteSteps(),
            'route_presets' => prRoutePresets(),
        ]);
    }

    if ($action === 'save_assignment') {
        $id = prSaveRoleAssignment($body['assignment'] ?? $body, (int)$user['id']);
        prApiResponse(true, ['id' => $id, 'assignments' => prFetchRoleAssignments()]);
    }

    if ($action === 'delete_assignment') {
        prDeleteRoleAssignment((int)($body['id'] ?? 0), (int)$user['id']);
        prApiResponse(true, ['assignments' => prFetchRoleAssignments()]);
    }

    if ($action === 'save_route') {
        $id = prSaveRouteRule($body['route'] ?? $body, (int)$user['id']);
        prApiResponse(true, ['id' => $id, 'routes' => prFetchRouteRules()]);
    }

    if ($action === 'delete_route') {
        prDeleteRouteRule((int)($body['id'] ?? 0), (int)$user['id']);
        prApiResponse(true, ['routes' => prFetchRouteRules()]);
    }

    if ($action === 'install_route_presets') {
        $ids = prInstallRoutePresets((int)$user['id']);
        prApiResponse(true, ['installed_ids' => $ids, 'routes' => prFetchRouteRules()]);
    }

    prApiResponse(false, ['errors' => ['Неизвестное действие администрирования.']], 400);
} catch (Throwable $e) {
    prLog('admin_api', ['event' => 'exception', 'message' => $e->getMessage()]);
    prApiResponse(false, ['errors' => [$e->getMessage()]], 400);
}
