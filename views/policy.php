<?php

/**
 * Dropbox policy view.
 *
 * @category   Apps
 * @package    Dropbox
 * @subpackage Views
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

$this->lang->load('base');
$this->lang->load('dropbox');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($restore_ready) {
    $buttons = array(
        anchor_custom('/app/dropbox/upload_restore/' . $filename, lang('base_restore'), 'high'),
        anchor_cancel('/app/dropbox')
    );
} else {
    $buttons = array(
        form_submit_custom('upload', lang('dropbox_upload'), 'high')
    );
}

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

echo form_open_multipart('dropbox/restore');
echo form_header(lang('dropbox_restore_from_archive'));

echo field_file('restore_file', $filename, lang('dropbox_restore_file'), $restore_ready);

if ($restore_ready)
    echo field_file('size', $size, lang('base_file_size'), $restore_ready);

echo field_button_set($buttons);

echo form_footer();
echo form_close();
