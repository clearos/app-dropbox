<?php

/////////////////////////////////////////////////////////////////////////////
// General information
///////////////////////////////////////////////////////////////////////////// 
$app['basename'] = 'dropbox';
$app['version'] = '1.0.0';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('dropbox_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('dropbox_app_name');
$app['category'] = lang('base_category_server');
$app['subcategory'] = lang('base_subcategory_file');

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['dropbox']['title'] = $app['name'];
$app['controllers']['policy']['title'] = lang('base_app_policy');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_requires'] = array(
    'dropbox',
    'app-user-dropbox-plugin',
    'app-users-core',
    'app-samba-core'
);

$app['core_file_manifest'] = array(
   'dropbox.conf' => array(
        'target' => '/etc/clearos/dropbox.conf',
        'mode' => '0644',
        'owner' => 'root',
        'group' => 'root',
        'config' => TRUE,
        'config_params' => 'noreplace',
    ),
    'app-dropbox.cron' => array(
        'target' => '/etc/cron.d/app-dropbox',
        'mode' => '0644',
        'owner' => 'root',
        'group' => 'root',
    ),
    'dropboxconf' => array(
        'target' => '/usr/sbin/dropboxconf',
        'mode' => '0744',
        'owner' => 'root',
        'group' => 'root',
    )
);
