<?php

/**
 * Dropbox summary view.
 *
 * @category   apps
 * @package    dropbox
 * @subpackage views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearcenter.com/support/documentation/clearos/dropbox/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.  
//  
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->load->helper('number');
$this->lang->load('base');
$this->lang->load('dropbox');

///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

$items = array();

foreach ($users as $username => $info) {
    $item = array(
        'title' => $username,
        'action' => '',
        'anchors' => NULL,
        'details' => array(
            $username,
            ($info['enabled'] ? lang('base_enabled') : lang('base_disabled')),
            $info['status'],
            ($info['enabled'] && $info['size'] > 0 ? byte_format($info['size']) : '---')
        )
    );

    $items[] = $item;
}
echo summary_table(
    lang('dropbox_users'),
    NULL,
    array(lang('base_username'), lang('base_enabled'), lang('base_status'), lang('dropbox_folder_size')),
    $items,
    array('no_action' => TRUE)
);
