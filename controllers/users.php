<?php

/**
 * Dropbox status controller
 *
 * @category   apps
 * @package    dropbox
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
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
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Dropbox status controller
 *
 * @category   apps
 * @package    dropbox
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/dropbox/
 */

class Users extends ClearOS_Controller
{
    /**
     * Dropbox users controller
     *
     * @return view
     */

    function index()
    {
        // Load dependencies
        //------------------

        $this->lang->load('dropbox');
        $this->load->library('dropbox/Dropbox');

        // Load view data
        //---------------

        try {
            $data['users'] = $this->dropbox->get_users();
        } catch (Engine_Engine_Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load views
        //-----------

        $this->page->view_form('dropbox/summary', $data, lang('dropbox_app_name'));
    }

    /**
     * Ajax request to get folder size
     *
     * @return JSON
     */

    function folder_size($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        header('Cache-Control: no-cache, must-revalidate');
        header('Content-type: application/json');

        // Load libraries
        //---------------

        $this->load->library('dropbox/Dropbox');

        try {
            echo json_encode(array('code' => 0, 'size' => $this->dropbox->get_folder_size($username)));
        } catch (Exception $e) {
            echo json_encode(array('code' => clearos_exception_code($e), 'errmsg' => clearos_exception_message($e)));
        }
    }
}
