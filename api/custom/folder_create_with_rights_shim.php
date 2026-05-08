<?php

declare(strict_types=1);

/**
 * TeamPass folder create shim endpoint.
 *
 * Deploy this file to your TeamPass server at:
 *   /api/custom/folder_create_with_rights.php
 *
 * Then set create_bp_folders.php config:
 *   createFolderEndpointPath => '/api/custom/folder_create_with_rights.php'
 *
 * This shim mirrors API folder/create input but forces the FolderManager
 * options that persist folder role permissions (manageFolderPermissions).
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require dirname(__DIR__) . '/inc/bootstrap.php';
require_once TEAMPASS_ROOT_PATH . '/sources/folders.class.php';

$apiStatus = json_decode(apiIsEnabled(), true);
if (!is_array($apiStatus) || ($apiStatus['error'] ?? true) === true) {
    errorHdl(
        (string) ($apiStatus['error_header'] ?? 'HTTP/1.1 404 Not Found'),
        json_encode(['error' => (string) ($apiStatus['error_message'] ?? 'API usage is not allowed')])
    );
    exit;
}

$jwtStatus = json_decode(verifyAuth(), true);
if (!is_array($jwtStatus) || ($jwtStatus['error'] ?? true) === true) {
    errorHdl(
        (string) ($jwtStatus['error_header'] ?? 'HTTP/1.1 404 Not Found'),
        json_encode(['error' => (string) ($jwtStatus['error_message'] ?? 'Access denied')])
    );
    exit;
}

$userDataWrap = json_decode(getDataFromToken(), true);
$userData = is_array($userDataWrap) && isset($userDataWrap['data']) && is_array($userDataWrap['data'])
    ? $userDataWrap['data']
    : [];

if ((int) ($userData['allowed_to_create'] ?? 0) !== 1) {
    errorHdl('HTTP/1.1 401 Unauthorized', json_encode(['error' => 'User is not allowed to create a folder']));
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode((string) $rawBody, true);
$input = is_array($jsonBody) ? $jsonBody : $_POST;

$title = trim((string) ($input['title'] ?? ''));
$parentId = (int) ($input['parent_id'] ?? 0);
$complexity = (int) ($input['complexity'] ?? 0);
$duration = (int) ($input['duration'] ?? 0);
$createAuthWithout = (int) ($input['create_auth_without'] ?? 0);
$editAuthWithout = (int) ($input['edit_auth_without'] ?? 0);
$icon = (string) ($input['icon'] ?? '');
$iconSelected = (string) ($input['icon_selected'] ?? '');
$accessRights = trim((string) ($input['access_rights'] ?? 'W'));

if ($title === '') {
    errorHdl('HTTP/1.1 422 Unprocessable Entity', json_encode(['error' => 'Missing title']));
    exit;
}

$allowedRights = ['R', 'W', 'NE', 'ND', 'NDNE'];
if (!in_array($accessRights, $allowedRights, true)) {
    errorHdl('HTTP/1.1 422 Unprocessable Entity', json_encode(['error' => 'Invalid access_rights']));
    exit;
}

$foldersList = trim((string) ($userData['folders_list'] ?? ''));
$userFolders = $foldersList === '' ? [] : array_map('intval', explode(',', $foldersList));

try {
    $lang = new TeampassClasses\Language\Language();
    $folderManager = new FolderManager($lang);

    $params = [
        'title' => $title,
        'parent_id' => $parentId,
        'complexity' => $complexity,
        'duration' => $duration,
        'create_auth_without' => $createAuthWithout,
        'edit_auth_without' => $editAuthWithout,
        'icon' => $icon,
        'icon_selected' => $iconSelected,
        'access_rights' => $accessRights,
        'user_is_admin' => (int) ($userData['is_admin'] ?? 0),
        'user_accessible_folders' => $userFolders,
        'user_is_manager' => (int) ($userData['is_manager'] ?? 0),
        'user_can_create_root_folder' => (int) ($userData['user_can_create_root_folder'] ?? 0),
        'user_can_manage_all_users' => (int) ($userData['user_can_manage_all_users'] ?? 0),
        'user_id' => (int) ($userData['id'] ?? 0),
        'user_roles' => (string) ($userData['roles'] ?? ''),
    ];

    $options = [
        'rebuildFolderTree' => true,
        'setFolderCategories' => false,
        'manageFolderPermissions' => true,
        'copyCustomFieldsCategories' => false,
        'refreshCacheForUsersWithSimilarRoles' => true,
    ];

    $creationStatus = $folderManager->createNewFolder($params, $options);

    if (!is_array($creationStatus)) {
        errorHdl('HTTP/1.1 500 Internal Server Error', json_encode(['error' => 'Unexpected creation response']));
        exit;
    }

    http_response_code(200);
    echo json_encode($creationStatus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    errorHdl('HTTP/1.1 500 Internal Server Error', json_encode(['error' => $e->getMessage()]));
}
