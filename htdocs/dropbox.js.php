<?php

/**
 * Javascript helper for Dropbox.
 * @category   apps
 * @package    dropbox
 * @subpackage javascript
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2017 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/dropbox/
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

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('base');
clearos_load_language('dropbox');

///////////////////////////////////////////////////////////////////////////////
// J A V A S C R I P T
///////////////////////////////////////////////////////////////////////////////

header('Content-Type: application/x-javascript');

?>

var lang_bytes = '<?php echo lang('base_bytes'); ?>';
var lang_kilobytes = '<?php echo lang('base_kilobytes'); ?>';
var lang_megabytes = '<?php echo lang('base_megabytes'); ?>';
var lang_gigabytes = '<?php echo lang('base_gigabytes'); ?>';

$(document).ready(function() {
    $('.dropbox_size').each(function(e) {
        get_folder_size($(this).data('user'));
    });
    $.fn.dataTableExt.oSort['custom-asc']  = function(a,b) {
        var ida = a.match(/id=\"([-?a-z_*]+)\"/)[1];
        var idb = b.match(/id=\"([-?a-z_*]+)\"/)[1];

        x = parseFloat($('#' + ida).data('size'));
        y = parseFloat($('#' + idb).data('size'));
        return ((x < y) ? -1 : ((x > y) ?  1 : 0));
    };

    $.fn.dataTableExt.oSort['custom-desc'] = function(a,b) {
        var ida = a.match(/id=\"([-?a-z_*]+)\"/)[1];
        var idb = b.match(/id=\"([-?a-z_*]+)\"/)[1];

        x = parseFloat($('#' + ida).data('size'));
        y = parseFloat($('#' + idb).data('size'));
        return ((x < y) ?  1 : ((x > y) ? -1 : 0));
    };
});

function get_folder_size(user)
{
    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: '/app/dropbox/users/folder_size/' + user,
    }).done(function(data) {
        if (data.code == 0) {
            $('#dropbox_user_' + user).data('size', data.size);
            $('#dropbox_user_' + user).html(format_number(data.size));
        }
    }).fail(function(xhr, text, err) {
        // Don't display any errors if ajax request was aborted due to page redirect/reload
        if (xhr['abort'] == undefined)
            clearos_dialog_box('error', lang_warning, xhr.responseText.toString());
    });
}
function format_number (bytes) {
    var sizes = [
        lang_bytes,
        lang_kilobytes,
        lang_megabytes,
        lang_gigabytes
    ];
    if (bytes == 0)
        return bytes + ' ' + sizes[0]; 
    var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
    return ((i == 0)? (bytes / Math.pow(1024, i)) : (bytes / Math.pow(1024, i)).toFixed(1)) + ' ' + sizes[i];
};

// vim: syntax=javascript ts=4
