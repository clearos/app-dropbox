<?php

/////////////////////////////////////////////////////////////////////////////
// General information
///////////////////////////////////////////////////////////////////////////// 

$app['basename'] = 'dropbox';
$app['version'] = '2.5.0';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('dropbox_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('dropbox_app_name');
$app['category'] = lang('base_category_cloud');
$app['subcategory'] = lang('base_subcategory_applications');

/////////////////////////////////////////////////////////////////////////////
// Tooltips
/////////////////////////////////////////////////////////////////////////////

$app['tooltip'] = array(
    lang('dropbox_starting_service'),
);

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['dropbox']['title'] = $app['name'];
$app['controllers']['policy']['title'] = lang('base_app_policy');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-base',
    'app-user-dropbox',
);

$app['core_requires'] = array(
    'dropbox >= 19.4.12',
    'app-user-dropbox-core >= 1:1.6.0',
    'app-users-core',
    'app-base-core >= 1:2.4.13',
    'app-user-dropbox-plugin-core',
);

$app['core_file_manifest'] = array(
    'dropbox.php'=> array('target' => '/var/clearos/base/daemon/dropbox.php'),
    'dropbox.conf' => array(
        'target' => '/etc/clearos/dropbox.conf',
        'mode' => '0644',
        'owner' => 'root',
        'group' => 'root',
        'config' => TRUE,
        'config_params' => 'noreplace',
    ),
);
$app['delete_dependency'] = array(
    'app-dropbox-core',
    'app-user-dropbox',
    'app-user-dropbox-core',
    'app-user-dropbox-extension-core',
    'dropbox'
);
