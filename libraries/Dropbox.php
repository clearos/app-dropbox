<?php

/**
 * Dropbox class.
 *
 * @category   apps
 * @package    dropbox
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2017 ClearFoundation
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
 * @copyright  2003-2017 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/dropbox/
 */

class Dropbox extends Daemon
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/clearos/dropbox.conf';
    const FILE_CACHE_SIZE = 'dropbox-%s.size';
    const FILE_USER_INIT_LOG = '/home/%s/.dropbox/init.log';
    const FILE_USER_LINK = '/home/%s/.dropbox/info.json';
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
     * Is user linked to Dropbox.
     *
     * @param String $username username
     *
     * @return boolean
     */

    public function is_linked($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(sprintf(self::FILE_USER_LINK, $username), TRUE);
            if ($file->exists())
                RETURN TRUE;
            return FALSE;
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /**
     * Is enabled.
     *
     * @param String  $username username
     *
     * @return void
     */

    public function is_enabled($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        $options['validate_exit_code'] = FALSE;
        $options['env'] = 'LANG=en_US';
        $shell = new Shell();
        $shell->execute(parent::COMMAND_SYSTEMCTL, "is-enabled dropbox@$username.service", TRUE, $options);
        if ($shell->get_last_output_line() == 'enabled')
            return TRUE;
        return FALSE;
    }

    /**
     * Get is running status for user.
     *
     * @param String  $username username
     *
     * @return void
     */

    public function is_running($username)
    {
        clearos_profile(__METHOD__, __LINE__);

        $options['validate_exit_code'] = FALSE;
        $shell = new Shell();
        $shell->execute(parent::COMMAND_SYSTEMCTL, "is-active dropbox@$username.service", TRUE, $options);
        if ($shell->get_last_output_line() == 'active')
            return TRUE;
        return FALSE;
    }

    /**
     * Set enable/disable.
     *
     * @param String  $username username
     * @param boolean $enabled
     *
     * @return void
     */

    public function set_state($username, $enabled)
    {
        clearos_profile(__METHOD__, __LINE__);

        $options['validate_exit_code'] = FALSE;
        $shell = new Shell();
        if ($enabled) {
            $shell->execute(parent::COMMAND_SYSTEMCTL, "enabled dropbox@$username.service", TRUE, $options);
            $shell->execute(parent::COMMAND_SYSTEMCTL, "start dropbox@$username.service", TRUE, $options);
        } else {
            $shell->execute(parent::COMMAND_SYSTEMCTL, "disabled dropbox@$username.service", TRUE, $options);
            $shell->execute(parent::COMMAND_SYSTEMCTL, "stop dropbox@$username.service", TRUE, $options);
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
            foreach ($contents as $line) {
                if (preg_match("/.*(https\S+)\s+.*/", $line, $match))
                    return $match[1];
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
     * @param boolean $force no cache
     *
     * @return int
     *
     * @throws Folder_Not_Found_Exception
     */

    public function get_folder_size($username, $force = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);
        $cache_time = 3600;
        $file = new File(CLEAROS_CACHE_DIR . "/" . sprintf(self::FILE_CACHE_SIZE, $username));

        $lastmod = 0;
        if ($file->exists())
            $lastmod = filemtime($file->get_filename());

        if (!$force && $lastmod && (time() - $lastmod < $cache_time))
            return $file->get_contents();

        $folder = new Folder(sprintf(self::PATH_USER_DEFAULT, $username), TRUE);
        if (!$folder->exists())
            return 0;

        $size = $folder->get_size();
        if ($file->exists())
            $file->delete();

        $file->create('webconfig', 'webconfig', '0640');
        $file->add_lines($size);
        return $size;
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
                    if ($enabled)
                        $status = lang('base_running');
                } catch (Folder_Not_Found_Exception $e) {
                    $size = 0;
                    if ($enabled)
                        $status = lang('dropbox_status_not_initialized');
                }

                $options['validate_exit_code'] = FALSE;
                $shell = new Shell();
                $exit_code = $shell->execute(self::COMMAND_SYSTEMCTL, "status dropbox@" . $username . ".service", FALSE, $options);

                if ($exit_code !== 0)
                    $status = lang('base_stopped');
                else
                    $status = lang('base_running');
                
                $info[$username] = array(
                    'enabled' => $enabled,
                    'status' => $status,
                    'size' => NULL
                );
                
            }
            return $info;
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
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

            $options['validate_exit_code'] = FALSE;
            $shell = new Shell();
            $shell->execute(parent::COMMAND_SYSTEMCTL, "stop dropbox@$username.service", TRUE, $options);

            $home = new Folder(sprintf(self::PATH_USER_DEFAULT, $username), TRUE);
            if ($home->exists())
                $home->delete(TRUE);
        } catch (Exception $e) {
            throw new Engine_Exception(clearos_exception_message($e), CLEAROS_ERROR);
        }
    }

    /**
     * Returns list of systemd services.
     *
     * @return array list of systemd services
     * @throws Engine_Exception
     */

    public function get_systemd_services()
    {
        clearos_profile(__METHOD__, __LINE__);

        $groupobj = Group_Factory::create('dropbox_plugin');
        $group_info = $groupobj->get_info();

        foreach ($group_info['core']['members'] as $user)
            $services[] = 'dropbox@' . $user . '.service';

        return $services;
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
