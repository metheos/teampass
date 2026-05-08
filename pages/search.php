<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      search.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__ . '/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('search') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //


?>

<style>
    #search-results-items {
        width: 100% !important;
    }

    @media (max-width: 767.98px) {
        #search-results-items thead {
            display: none;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) {
            display: block;
            margin-bottom: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            background-color: #fff;
            overflow: hidden;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) td {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border: 0;
            border-bottom: 1px solid #f1f3f5;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) td:last-child {
            border-bottom: 0;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) td::before {
            content: attr(data-label);
            flex: 0 0 40%;
            max-width: 40%;
            font-weight: 600;
            color: #495057;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) td.mobile-row-action {
            justify-content: flex-end;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #search-results-items tbody tr:not(.new-row):not(.child) td.mobile-row-action::before {
            content: '';
            display: none;
        }

        #search-results-items tbody tr.new-row td,
        #search-results-items tbody tr.child td {
            display: table-cell;
        }
    }
</style>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-search mr-2"></i><?php echo $lang->get('find'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- MASS OPERATION -->
<div class="card card-warning m-2 hidden" id="dialog-mass-operation">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-bug mr-2"></i>
            <?php echo $lang->get('mass_operation'); ?>
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12 col-md-12" id="dialog-mass-operation-html">

            </div>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary mr-2" id="dialog-mass-operation-button"><?php echo $lang->get('perform'); ?></button>
        <button class="btn btn-default float-right close-element"><?php echo $lang->get('cancel'); ?></button>
    </div>
</div>
<!-- /.MASS OPERATION -->

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mr-2" id="search-select"></h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="search-results-items" class="table table-bordered table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th><?php echo $lang->get('label'); ?></th>
                                    <th><?php echo $lang->get('login'); ?></th>
                                    <th><?php echo $lang->get('description'); ?></th>
                                    <th><?php echo $lang->get('tags'); ?></th>
                                    <th><?php echo $lang->get('url'); ?></th>
                                    <th><?php echo $lang->get('group'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>