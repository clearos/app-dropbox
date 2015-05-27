<?php

/**
 * Dropbox class.
 *
 * @category   apps
 * @package    dropbox
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/dropbox/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\dropbox;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('dropbox');
clearos_load_language('base');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File;
use \clearos\apps\base\Shell;
use \clearos\apps\base\Folder;
use \clearos\apps\base\Daemon;
use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\users\User_Manager_Factory;
use \clearos\apps\groups\Group_Factory;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Shell');
clearos_load_library('base/Folder');
clearos_load_library('base/Daemon');
clearos_load_library('base/Configuration_File');
clearos_load_library('users/User_Manager_Factory');
clearos_load_library('groups/Group_Factory');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;
use \clearos\apps\base\Folder_Not_Found_Exception as Folder_Not_Found_Exception;
use \clearos\apps\dropbox\Account_Initialize_Busy_Exception as Account_Initialize_Busy_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/Validation_Exception');
clearos_load_library('base/Folder_Not_Found_Exception');
clearos_load_library('dropbox/Account_Initialize_Busy_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Dropbox class.
 *
 * @category   apps
 * @package    dropbox
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/dropbox/
 */

class Dropbox extends Daemon
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/clearos/dropbox.conf';
    const COMMAND_DROPBOX = '/usr/bin/dropbox';
    const FILE_USER_INIT_LOG = '/home/%s/.dropbox/init.log';
    const PATH_USER_DEFAULT = '/home/%s/Dropbox';
    const PATH_USER_CONFIG = '/home/%s/.dropbox';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $config = NULL;
    protected $is_loaded = FALSE;

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Dropbox constructor.
     */

    function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        parent::__construct('dropbox');
    }

    /**
     * Synchronises config file with user/admin settings.
     *
     * @param bool $disable_restart disabled restart on change
     *
     * @return void
     * @throws Engine_Exception
     */

    public function sync_config($disable_restart = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);
        try {
            $groupobj = Group_Factory::create('dropbox_plugin');
            $group_info = $groupobj->get_info();
            $disabled_users = $this->get_disabled_users();
            $configured_users = $this->get_configured_users();
            $updated_users = array();
            $changes_found = FALSE;
            foreach ($configured_users as $username) {
                if (!in_array($username, $group_info['core']['members'])) {
                    $changes_found = TRUE;
                    continue;
                }
                if (in_array($username, $disabled_users)) {
                    $changes_found = TRUE;
                    continue;
                }
                if ($this->get_init_user() != NULL) {
                    $changes_found = TRUE;
                    $this->set_init_user(NULL);
                }
                $updated_users[] = $username;
            }

            if ($changes_found)
                $this->set_configured_users($updated_users);

            $file = new File(self::FILE_CONFIG, TRUE);

            if ($file->exists() && !$changes_found) {
                // Let's check timestamp too...users disabling their account do not automatically trigger restart
                $last_modified = $file->last_modified();
                $one_day_ago = strtotime("-1 day");
                if ($last_modified > $one_day_ago)
                    $changes_found = TRUE;
            }
            if ($changes_found && !$disable_restart && $this->get_boot_state())
                $this->restart();
            
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Initializes an account.
     *
     * @param String $username username
     *
     * @return void
     * @throws Account_Initialize_Busy_Exception, Engine_Exception
     */

    public function init_account($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Check to see if we're already initialized
        if ($this->get_user_url_link($username) != NULL)
            return;

        if ($this->get_init_user() != NULL)
            throw new Account_Initialize_Busy_Exception();

        $home = new Folder("/home/$username");
        if (!$home->exists())
            throw new Engine_Exception(lang('dropbox_missing_home_folder'), CLEAROS_ERROR);

        // Create this...init scripts need to write to log file
        $folder = new Folder(sprintf(self::PATH_USER_CONFIG, $username), TRUE);
        if (!$folder->exists())
            $folder->create($username, 'webconfig', '0700');
        else
            $folder->chown($username, 'webconfig');

        try {
            // Touch log file where init script will log to
            $log = new File(sprintf(self::FILE_USER_INIT_LOG, $username), TRUE);
            if (!$log->exists())
                $log->create($username, 'webconfig', '0660');
            else
                $log->chown($username, 'webconfig');


            if (!$this->get_running_state() && !$this->get_boot_state())
                throw new Engine_Exception(lang('dropbox_service_not_running'), CLEAROS_ERROR);

            // TODO
            // Ugly hack...but how to start init scripts as user otherwise?  Don't want to stop/start all instances
            $this->set_init_user($username);
            $this->restart();
            $this->set_init_user(NULL);

        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Get user Dropbox URL link.
     *
     * @param String $username username
     *
     * @return String
     */

    public function get_user_url_link($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $contents = $this->get_user_log($username);
            $configured_users = $this->get_configured_users();
            $disabled_users = $this->get_disabled_users();
            foreach ($contents as $line) {
                if (preg_match("/.*(https.*nonce=[\w_]+)\s+.*/", $line, $match)) {
                    if (!in_array($username, $configured_users) && !in_array($username, $disabled_users)) {
                        $configured_users[] = $username;
                        $this->set_configured_users($configured_users);
                    }
                    return $match[1];
                }
            }
            return NULL;
        } catch (Exception $e) {
            return NULL;
        }
    }

    /**
     * Get user log.
     *
     * @param String $username username
     *
     * @return array
     */

    public function get_user_log($username)
    {
        clearos_profile(__METHOD__, __LINE__);
        $contents = array();
        try {
            $file = new File(sprintf(self::FILE_USER_INIT_LOG, $username), TRUE);
            if ($file->exists()) {
                $contents = array_reverse($file->get_contents_as_array());
                return $contents;
            }
            return $contents;
        } catch (Exception $e) {
            return $contents;
        }
    }

    /**
     * Get folder status.
     *
     * @param String $username username
     *
     * @return boolean
     */

    public function is_folder_created($username)
    {
        clearos_profile(__METHOD__, __LINE__);
        try {
            $folder = new Folder(sprintf(self::PATH_USER_DEFAULT, $username), TRUE);
            if ($folder->exists())
                return TRUE;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /**
     * Get folder size.
     *
     * @param String $username username
     *
     * @return int
     *
     * @throws Folder_Not_Found_Exception
     */

    public function get_folder_size($username)
    {
        clearos_profile(__METHOD__, __LINE__);
        $folder = new Folder(sprintf(self::PATH_USER_DEFAULT, $username), TRUE);
        if ($folder->exists())
            return $folder->get_size();
        else
            throw new Folder_Not_Found_Exception();
    }

    /**
     * Get user settings.
     *
     * @return void
     * @throws Engine_Exception
     */

    public function get_users()
    {
        clearos_profile(__METHOD__, __LINE__);
        $info = array();
        try {
            $user_factory = new User_Manager_Factory();
            $user_manager = $user_factory->create();
            $users = $user_manager->get_core_details();

            $groupobj = Group_Factory::create('dropbox_plugin');
            $group_info = $groupobj->get_info();
            foreach ($users as $username => $details) {

                $status = lang('base_disabled');
                $enabled = FALSE;

                if (in_array($username, $group_info['core']['members']))
                    $enabled = TRUE;

                try {
                    $size = $this->get_folder_size($username);
                    if ($enabled)
                        $status = lang('base_running');
                } catch (Folder_Not_Found_Exception $e) {
                    $size = 0;
                    if ($enabled)
                        $status = lang('dropbox_status_not_initialized');
                }

                if (!$this->get_running_state())
                    $status = lang('base_stopped');
                
                $info[$username] = array(
                    'enabled' => $enabled,
                    'status' => $status,
                    'size' => $size
                );
                
            }
            return $info;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Set dropbox init user.
     *
     * @param String $user user
     *
     * @return void
     * @throws Account_Initialize_Busy_Exception, Validation_Exception
     */

    function set_init_user($user)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($user != NULL)
            Validation_Exception::is_valid($this->validate_user($user));

        if ($user != NULL && $this->get_init_user() != NULL)
            throw new Account_Initialize_Busy_Exception();

        $this->_set_parameter('INIT_USER', ($user == NULL ? '' : $user));
    }

    /**
     * Set dropbox disabled manual override.
     *
     * @param array $users users
     *
     * @return void
     * @throws Validation_Exception
     */

    function set_disabled_users($users)
    {
        clearos_profile(__METHOD__, __LINE__);

        foreach ($users as $user)
            Validation_Exception::is_valid($this->validate_user($user));

        $this->_set_parameter('USER_DISABLED', implode(' ', $users));
    }

    /**
     * Set dropbox configured users.
     *
     * @param array $users users
     *
     * @return void
     * @throws Validation_Exception
     */

    function set_configured_users($users)
    {
        clearos_profile(__METHOD__, __LINE__);

        foreach ($users as $user)
            Validation_Exception::is_valid($this->validate_user($user));

        $this->_set_parameter('DROPBOX_USERS', implode(' ', $users));
    }

    /**
     * Get init user.
     *
     * @return array
     */

    function get_init_user()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!$this->is_loaded)
            $this->_load_config();

        if (isset($this->config['INIT_USER']) && $this->config['INIT_USER'] != '')
            return $this->config['INIT_USER'];
        else
            return NULL;
    }

    /**
     * Get dropbox user disable override.
     *
     * @return array
     */

    function get_disabled_users()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!$this->is_loaded)
            $this->_load_config();

        $empty_array = array();

        if (isset($this->config['USER_DISABLED']) && $this->config['USER_DISABLED'] != '')
            return explode(' ', $this->config['USER_DISABLED']);
        else
            return $empty_array;
    }

    /**
     * Get dropbox configured users.
     *
     * @return array
     */

    function get_configured_users()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!$this->is_loaded)
            $this->_load_config();

        $empty_array = array();

        if (isset($this->config['DROPBOX_USERS']) && $this->config['DROPBOX_USERS'] != '')
            return explode(' ', $this->config['DROPBOX_USERS']);
        else
            return $empty_array;
    }

    /**
     * Reset dropbox account locally.
     *
     * @param string $username username
     *
     * @return void
     * @throws Engine_Exception
     */

    function reset_account($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $config = new Folder(sprintf(self::PATH_USER_CONFIG, $username), TRUE);
            if ($config->exists())
                $config->delete(TRUE);
            $home = new Folder(sprintf(self::PATH_USER_DEFAULT, $username), TRUE);
            if ($home->exists())
                $home->delete(TRUE);
            $configured_users = $this->get_configured_users();
            $disabled_users = $this->get_disabled_users();
            $update = FALSE;
            if (in_array($username, $configured_users)) {
                $pos = array_search($username, $configured_users);
                unset($configured_users[$pos]);
                $this->set_configured_users($configured_users);
                $update = TRUE;
            }
            if (in_array($username, $disabled_users)) {
                $pos = array_search($username, $disabled_users);
                unset($disabled_users[$pos]);
                $this->set_configured_users($disabled_users);
                $update = TRUE;
            }
            if ($update && $this->get_boot_state())
                $this->restart();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for dropbox user.
     *
     * @param string $user user
     *
     * @return mixed void if user is valid, errmsg otherwise
     */

    public function validate_user($user)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $user_factory = new User_Manager_Factory();
            $user_manager = $user_factory->create();
            $users = $user_manager->get_core_details();
            if (! array_key_exists($user, $users))
                return $user . ' ' . lang('dropbox_user_invalid');
        } catch (Exception $e) {
            return clearos_exception_message($e);
        }
        
    }

    /**
     * Validation routine for dropbox user enable/disable override.
     *
     * @param boolean $enabled enabled
     *
     * @return mixed void if enable is valid, errmsg otherwise
     */

    public function validate_enabled($enabled)
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Loads configuration files.
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _load_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        $configfile = new Configuration_File(self::FILE_CONFIG, 'match', "/(.*)\s*=\s*\"(.*)\"/");

        try {
            $this->config = $configfile->load();
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $this->is_loaded = TRUE;
    }

    /**
     * Generic set routine.
     *
     * @param string $key   key name
     * @param string $value value for the key
     *
     * @return  void
     * @throws Engine_Exception
     */

    function _set_parameter($key, $value)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FILE_CONFIG, TRUE);

            if (!$file->exists())
                $file->create('webconfig', 'webconfig', '0644');

            $match = $file->replace_lines("/^$key\s*=\s*/", "$key=\"$value\"\n");

            if (!$match)
                $file->add_lines("$key=\"$value\"\n");
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }

        $this->is_loaded = FALSE;
    }
}
