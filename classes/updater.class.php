<?php
/**
 *
 *
 * @version 1.7
 * @author Joachim Kudish <info@jkudish.com>
 * @link http://jkudish.com
 * @package WP_GitHub_Updater_PW3
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2011-2013, Joachim Kudish
 *
 * GNU General Public License, Free Software Foundation
 * <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Prevent loading this file directly and/or if the class is already defined
if ( ! defined('ABSPATH') || class_exists('WP_GitHub_Updater_PW3')) {
    return; // Exit if accessed directly
}

class WP_GitHub_Updater_PW3
{

    /**
     * GitHub Updater version
     */
    const VERSION = 1.7;

    const PROPER_FOLDER_NAME      = 'proper_folder_name';
    const SSLVERIFY               = 'sslverify';
    const ACCESS_TOKEN            = 'access_token';
    const RAW_URL                 = 'raw_url';
    const ZIP_URL                 = 'zip_url';
    const README                  = 'readme';
    const RAW_RESPONSE            = 'raw_response';
    const NEW_VERSION             = 'new_version';
    const NEW_TESTED              = 'new_tested';
    const ICONS                   = 'icons';
    const LAST_UPDATED            = 'last_updated';
    const DESCRIPTION             = 'description';
    const CHANGELOG               = 'changelog';
    const PLUGIN_NAME             = 'plugin_name';
    const AUTHOR                  = 'author';
    const HOMEPAGE                = 'homepage';
    const VERSION_LOWER_CASE_TEXT = 'version';

    /**
     * @var $config the config for the updater
     * @access public
     */
    public $config;

    /**
     * @var $missing_config any config that is missing from the initialization of this instance
     * @access public
     */
    public $missing_config;

    /**
     * @var $github_data temporiraly store the data fetched from GitHub, allows us to only load the data once per class instance
     * @access private
     */
    private $github_data;

    /**
     * Class Constructor
     *
     * @param array $config the configuration required for the updater to work
     *
     * @return void
     * @see has_minimum_config()
     * @since 1.0
     */
    public function __construct($config = array())
    {
        $defaults = array(
            'slug'                   => plugin_basename(__FILE__),
            self::PROPER_FOLDER_NAME => dirname(plugin_basename(__FILE__)),
            self::SSLVERIFY          => true,
            self::ACCESS_TOKEN       => '',
        );

        $this->config = wp_parse_args($config, $defaults);

        // If the minimum config isn't set, issue a warning and bail
        if ( ! $this->has_minimum_config()) {
            $message = 'The GitHub Updater was initialized without the minimum required configuration, please check the config in your plugin. The following params are missing: ';
            $message .= implode(',', $this->missing_config);
            _doing_it_wrong(__CLASS__, $message, self::VERSION);

            return;
        }

        $this->set_defaults();
    }

    /**
     * Adds Wordpress filters
     */
    public function add_filters()
    {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'api_check'));

        // Hook into the plugin details screen
        add_filter('plugins_api', array($this, 'get_plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'upgrader_post_install'), 10, 3);

        // Set timeout
        add_filter('http_request_timeout', array($this, 'http_request_timeout'));

        // Set sslverify for zip download
        add_filter('http_request_args', array($this, 'http_request_sslverify'), 10, 2);
    }

    public function has_minimum_config()
    {
        $this->missing_config = array();

        $required_config_params = array(
            'api_url',
            self::RAW_URL,
            'github_url',
            self::ZIP_URL,
            'requires',
            'tested',
            self::README,
        );

        foreach ($required_config_params as $required_param) {
            if (empty($this->config[$required_param])) {
                $this->missing_config[] = $required_param;
            }
        }

        return (empty($this->missing_config));
    }

    /**
     * Check wether or not the transients need to be overruled and API needs to be called for every single page load
     *
     * @return bool overrule or not
     */
    public function overrule_transients()
    {
        return (defined('WP_GITHUB_FORCE_UPDATE') && WP_GITHUB_FORCE_UPDATE);
    }

    /**
     * Set defaults
     *
     * @return void
     * @since 1.2
     */
    public function set_defaults()
    {
        if ( ! empty($this->config[self::ACCESS_TOKEN])) {
            // See Downloading a zipball (private repo) https://help.github.com/articles/downloading-files-from-the-command-line
            extract(parse_url($this->config[self::ZIP_URL])); // $scheme, $host, $path

            $zip_url = $scheme . '://api.github.com/repos' . $path;
            $zip_url = add_query_arg(array(self::ACCESS_TOKEN => $this->config[self::ACCESS_TOKEN]), $zip_url);

            $this->config[self::ZIP_URL] = $zip_url;
        }

        if ( ! isset($this->config[self::RAW_RESPONSE])) {
            $this->config[self::RAW_RESPONSE] = $this->get_raw_response();
        }

        if ( ! isset($this->config[self::NEW_VERSION])) {
            $this->config[self::NEW_VERSION] = $this->get_new_version();
        }

        if ( ! isset($this->config[self::NEW_TESTED])) {
            $this->config[self::NEW_TESTED] = $this->get_new_tested();
        }

        if ( ! isset($this->config[self::ICONS])) {
            $this->config[self::ICONS] = $this->get_icons();
        }

        if ( ! isset($this->config[self::LAST_UPDATED])) {
            $this->config[self::LAST_UPDATED] = $this->get_date();
        }

        if ( ! isset($this->config[self::DESCRIPTION])) {
            $this->config[self::DESCRIPTION] = $this->get_description();
        }

        if ( ! isset($this->config[self::CHANGELOG])) {
            $this->config[self::CHANGELOG] = $this->get_changelog();
        }

        $plugin_data = $this->get_plugin_data();
        if ( ! isset($this->config[self::PLUGIN_NAME])) {
            $this->config[self::PLUGIN_NAME] = $plugin_data['Name'];
        }

        if ( ! isset($this->config[self::VERSION_LOWER_CASE_TEXT])) {
            $this->config[self::VERSION_LOWER_CASE_TEXT] = $plugin_data['Version'];
        }

        if ( ! isset($this->config[self::AUTHOR])) {
            $this->config[self::AUTHOR] = $plugin_data['Author'];
        }

        if ( ! isset($this->config[self::HOMEPAGE])) {
            $this->config[self::HOMEPAGE] = $plugin_data['PluginURI'];
        }

        if ( ! isset($this->config[self::README])) {
            $this->config[self::README] = 'README.md';
        }
    }

    /**
     * Callback fn for the http_request_timeout filter
     *
     * @return int timeout value
     * @since 1.0
     */
    public function http_request_timeout()
    {
        return 2;
    }

    /**
     * Callback fn for the http_request_args filter
     *
     * @param unknown $args
     * @param unknown $url
     *
     * @return mixed
     */
    public function http_request_sslverify($args, $url)
    {
        if ($this->config[self::ZIP_URL] == $url) {
            $args[self::SSLVERIFY] = $this->config[self::SSLVERIFY];
        }

        return $args;
    }

    /**
     * Get Icons from GitHub
     *
     * @return array $icons the plugin icons
     * @since 1.7
     */
    public function get_icons()
    {
        $assest_url = $this->config[self::RAW_URL] . '/assets/images/';

        return array(
            'default' => $assest_url . 'icon-128x128.png',
            '1x'      => $assest_url . 'icon-128x128.png',
            '2x'      => $assest_url . 'icon-256x256.png',
        );
    }

    /**
     * Get Raw Response from GitHub
     *
     * @return int $raw_response the raw response
     * @since 1.7
     */
    public function get_raw_response()
    {
        return $this->remote_get(trailingslashit($this->config[self::RAW_URL]) . basename($this->config['slug']));
    }

    /**
     * Get New Version from GitHub
     *
     * @return int $version the version number
     * @since 1.0
     */
    public function get_new_version()
    {
        $version = get_site_transient(md5($this->config['slug']) . '_new_version');

        if ($this->overrule_transients() || ( ! isset($version) || ! $version || '' == $version)) {
            $raw_response = $this->config[self::RAW_RESPONSE];

            if (is_wp_error($raw_response)) {
                $version = false;
            }

            if (is_array($raw_response) && ! empty($raw_response['body'])) {
                preg_match('/.*Version\:\s*(.*)$/mi', $raw_response['body'], $matches);
            }

            if (empty($matches[1])) {
                $version = false;
            } else {
                $version = $matches[1];
            }

            // Refresh every 6 hours
            if (false !== $version) {
                set_site_transient(md5($this->config['slug']) . '_new_version', $version, 60 * 60 * 6);
            }
        }

        return $version;
    }

    /**
     * Get New Tested from GitHub
     *
     * @return int $tested the tested number
     * @since 1.7
     */
    public function get_new_tested()
    {
        $tested = get_site_transient(md5($this->config['slug']) . '_new_tested');

        if ($this->overrule_transients() || ( ! isset($tested) || ! $tested || '' == $tested)) {
            $raw_response = $this->config[self::RAW_RESPONSE];

            if (is_wp_error($raw_response)) {
                $tested = false;
            }

            if (is_array($raw_response) && ! empty($raw_response['body'])) {
                preg_match('/.*Tested\:\s*(.*)$/mi', $raw_response['body'], $matches);
            }

            if (empty($matches[1])) {
                $tested = $this->config['tested'];
            } else {
                $tested = $matches[1];
            }

            // Refresh every 6 hours
            if (false !== $tested) {
                set_site_transient(md5($this->config['slug']) . '_new_tested', $tested, 60 * 60 * 6);
            }
        }

        return $tested;
    }

    /**
     * Interact with GitHub
     *
     * @param string $query
     *
     * @return mixed
     * @since 1.6
     */
    public function remote_get($query)
    {
        if ( ! empty($this->config[self::ACCESS_TOKEN])) {
            $query = add_query_arg(array(self::ACCESS_TOKEN => $this->config[self::ACCESS_TOKEN]), $query);
        }

        return wp_remote_get(
            $query,
            array(
                self::SSLVERIFY => $this->config[self::SSLVERIFY],
            )
        );
    }

    /**
     * Get GitHub Data from the specified repository
     *
     * @return array|bool
     * @since 1.0
     */
    public function get_github_data()
    {
        if (isset($this->github_data) && ! empty($this->github_data)) {
            $github_data = $this->github_data;
        } else {
            $github_data = get_site_transient(md5($this->config['slug']) . '_github_data');

            if ($this->overrule_transients() || ( ! isset($github_data) || ! $github_data || '' == $github_data)) {
                $github_data = $this->remote_get($this->config['api_url']);

                if (is_wp_error($github_data)) {
                    return false;
                }

                $github_data = json_decode($github_data['body']);

                // Refresh every 6 hours
                set_site_transient(md5($this->config['slug']) . '_github_data', $github_data, 60 * 60 * 6);
            }

            // Store the data in this class instance for future calls
            $this->github_data = $github_data;
        }

        return $github_data;
    }

    /**
     * Get update date
     *
     * @return string $date the date
     * @since 1.0
     */
    public function get_date()
    {
        $_date = $this->get_github_data();

        return ( ! empty($_date->updated_at)) ? date('Y-m-d', strtotime($_date->updated_at)) : false;
    }

    /**
     * Get plugin description
     *
     * @return string $description the description
     * @since 1.0
     */
    public function get_description()
    {
        $_description = $this->get_github_data();

        return ( ! empty($_description->description)) ? $_description->description : false;
    }

    /**
     * Get plugin changelog
     *
     * @return string $_changelog the changelog
     * @since 1.0
     */
    public function get_changelog()
    {
        $_changelog = '';
        if ( ! is_wp_error($this->config)) {
            $_changelog = $this->remote_get($this->config[self::RAW_URL] . '/changelog.txt');
        }
        if ( ! is_wp_error($_changelog)) {
            $_changelog = nl2br($_changelog['body']);
        } else {
            $_changelog = '';
        }

        return (! empty($_changelog) ? $_changelog : 'Could not get changelog from server.');
    }

    /**
     * Get Plugin data
     *
     * @return object the data
     * @since 1.0
     */
    public function get_plugin_data()
    {
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        return get_plugin_data(WP_PLUGIN_DIR . '/' . $this->config['slug']);
    }

    /**
     * Hook into the plugin update check and connect to GitHub
     *
     * @param object $transient the plugin data transient
     *
     * @return object $transient updated plugin data transient
     * @since 1.0
     */
    public function api_check($transient)
    {
        // Check if the transient contains the 'checked' information
        // If not, just return its value without hacking it
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check the version and decide if it's new
        $update = version_compare($this->config[self::NEW_VERSION], $this->config[self::VERSION_LOWER_CASE_TEXT]);

        if (1 === $update) {
            $response              = new stdClass;
            $response->new_version = $this->config[self::NEW_VERSION];
            $response->slug        = $this->config[self::PROPER_FOLDER_NAME];
            $response->url         = add_query_arg(
                array(self::ACCESS_TOKEN => $this->config[self::ACCESS_TOKEN]),
                $this->config['github_url']
            );
            $response->package     = $this->config[self::ZIP_URL];
            $response->icons       = $this->config[self::ICONS];
            $response->tested      = $this->config[self::NEW_TESTED];

            // If response is false, don't alter the transient
            if (false !== $response) {
                $transient->response[$this->config['slug']] = $response;
            }
        }

        return $transient;
    }

    /**
     * Get Plugin info
     *
     * @param bool $false always false
     * @param string $action the API function being performed
     * @param $response
     *
     * @return bool|object
     * @since 1.0
     */
    public function get_plugin_info($false, $action, $response)
    {
        // Check if this call API is for the right plugin
        if ( ! isset($response->slug) || $response->slug != $this->config[self::PROPER_FOLDER_NAME]) {
            return false;
        } else {
            $res                = new stdClass();
            $res->name          = $this->config[self::PLUGIN_NAME];
            $res->slug          = $this->config['slug'];
            $res->version       = $this->config[self::NEW_VERSION];
            $res->author        = $this->config[self::AUTHOR];
            $res->homepage      = $this->config[self::HOMEPAGE];
            $res->requires      = $this->config['requires'];
            $res->tested        = $this->config[self::NEW_TESTED];
            $res->downloaded    = 0;
            $res->last_updated  = $this->config[self::LAST_UPDATED];
            $res->sections      = array(
                self::DESCRIPTION => $this->config[self::DESCRIPTION],
                self::CHANGELOG   => $this->config[self::CHANGELOG],
            );
            $res->download_link = $this->config[self::ZIP_URL];

            // Useful fields for a later version
            // $res->rating = '100';
            // $res->num_ratings = '1124';
            // $res->active_installs = '11056';
            // $res->downloaded = '18056';

            return $res;
        }
    }

    /**
     * Upgrader/Updater
     * Move & activate the plugin, echo the update message
     *
     * @param boolean $true always true
     * @param mixed $hook_extra not used
     * @param array $result the result of the move
     *
     * @return array $result the result of the move
     * @since 1.0
     */
    public function upgrader_post_install($true, $hook_extra, $result)
    {
        global $wp_filesystem;

        // Move & Activate
        $proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
        $wp_filesystem->move($result['destination'], $proper_destination);
        $result['destination'] = $proper_destination;
        $activate              = activate_plugin(WP_PLUGIN_DIR . '/' . $this->config['slug']);

        // Output the update message
        $fail    = __(
            'The plugin has been updated, but could not be reactivated. Please reactivate it manually.',
            'github_plugin_updater'
        );
        $success = __('Plugin reactivated successfully.', 'github_plugin_updater');
        echo is_wp_error($activate) ? $fail : $success;

        return $result;
    }
}
