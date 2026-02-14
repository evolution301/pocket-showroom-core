<?php
/**
 * GitHub Plugin Updater
 * 
 * Enables automatic updates forWordPress plugins hosted on GitHub.
 * 
 * @package Pocket Showroom Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class PS_Plugin_Updater {
    /**
     * GitHub repository owner (username or organization)
     * @var string
     */
    private $github_user;

    /**
     * GitHub repository name
     * @var string
     */
    private $github_repo;

    /**
     * Plugin basename (folder/main-file.php)
     * @var string
     */
    private $plugin_basename;

    /**
     * Current plugin version
     * @var string
     */
    private $current_version;

    /**
     * Cache key for transient
     * @var string
     */
    private $cache_key;

    /**
     * Cache expiration in seconds (12 hours)
     * @var int
     */
    private $cache_expiration = 43200;

    /**
     * Initialize the updater.
     *
     * @param string $github_userGitHub username or organization
     * @param string $github_repoGitHub repository name
     * @param string $plugin_file Full path to the main plugin file
     */
    public function __construct($github_user, $github_repo, $plugin_file) {
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->current_version = PS_CORE_VERSION;
        $this->cache_key = 'ps_github_updater_' . md5($this->github_user . '/' . $this->github_repo);

        // Hook into WordPress update system
        add_filter('site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Add "Check for updates" link
        add_action('admin_init', array($this, 'manual_check_trigger'));
        add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_check_update_link'));
    }

    /**
     * Add "Check for updates" link to plugin actions.
     *
     * @param array $links Existing plugin action links
     * @return array Modified links
     */
    public function add_check_update_link($links) {
        $check_link = '<a href="' . wp_nonce_url(admin_url('plugins.php?ps_check_update=1'), 'ps_check_update') . '">Check for Updates</a>';
        array_push($links, $check_link);
        return $links;
    }

    /**
     * Handle manual update check trigger.
     */
    public function manual_check_trigger() {
        if (!isset($_GET['ps_check_update']) || !current_user_can('update_plugins')) {
            return;
        }

        check_admin_referer('ps_check_update');

        // Clear cache to force fresh check
        delete_site_transient($this->cache_key);
        delete_site_transient('update_plugins');

        // Redirect back to plugins page with success message
        wp_redirect(add_query_arg('ps_update_checked', '1', admin_url('plugins.php')));
        exit;
    }

    /**
     * Check for plugin updates.
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient)) {
            return $transient;
        }

        // Get remote version info
        $remote_info = $this->get_remote_info();

        if (!$remote_info) {
            return $transient;
        }

        // Compare versions
        if (version_compare($this->current_version, $remote_info->version, '<')) {
            $plugin_data = array(
                'slug' => dirname($this->plugin_basename),
                'new_version' => $remote_info->version,
                'url' => $remote_info->url,
                'package' => $remote_info->download_url,
                'tested' => $remote_info->tested ?? '6.4',
                'requires_php' => $remote_info->requires_php ?? '7.4',
                'icons' => array(
                    '1x' => PS_CORE_URL . 'assets/icon-128x128.png',
                    '2x' => PS_CORE_URL . 'assets/icon-256x256.png',
                ),
            );

            $transient->response[$this->plugin_basename] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Get remote version information from GitHub.
     *
     * @return object|false Version info object or false on failure
     */
    private function get_remote_info() {
        // Try to get from cache first
        $cached = get_site_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Fetch latest release from GitHub API
        $response = wp_remote_get(
            "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest",
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                ),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_response_body($response));

        if (!$release || !isset($release->tag_name)) {
            return false;
        }

        // Parse version from tag (remove 'v' prefix if present)
        $version = ltrim($release->tag_name, 'v');

        // Find the zip asset or use source code zip
        $download_url = '';
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false && 
                    strpos($asset->name, 'pocket-showroom-core') !== false) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Fallback to source code zip
        if (empty($download_url)) {
            $download_url = "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/{$release->tag_name}.zip";
        }

        $info = (object) array(
            'version' => $version,
            'url' => $release->html_url,
            'download_url' => $download_url,
            'requires_php' => '7.4',
            'tested' => '6.4',
            'last_updated' => $release->published_at,
            'changelog' => $release->body ?? '',
        );

        // Cache the result
        set_site_transient($this->cache_key, $info, $this->cache_expiration);

        return $info;
    }

    /**
     * Display plugin information in the update popup.
     *
     * @param false|object|array $result Plugin info
     * @param string $action Action being performed
     * @param object $args Extra arguments
     * @return object|false Plugin info object
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_basename)) {
            return $result;
        }

        $remote_info = $this->get_remote_info();

        if (!$remote_info) {
            return $result;
        }

        return (object) array(
            'name' => 'Pocket Showroom Core',
            'slug' => dirname($this->plugin_basename),
            'version' => $remote_info->version,
            'author' => '<a href="https://github.com/' . $this->github_user . '">Evolution301</a>',
            'author_profile' => 'https://github.com/' . $this->github_user,
            'last_updated' => $remote_info->last_updated,
            'homepage' => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'short_description' => 'A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.',
            'sections' => array(
                'description' => '<p>A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.</p>',
                'changelog' => '<pre>' . esc_html($remote_info->changelog) . '</pre>',
            ),
            'download_link' => $remote_info->download_url,
            'tested' => $remote_info->tested ?? '6.4',
            'requires_php' => $remote_info->requires_php ?? '7.4',
        );
    }

    /**
     * Fix plugin folder structure after update.
     *
     * @param array $result Installation result
     * @param array $hook_extra Extra hook arguments
     * @param array $result Installation result data
     * @return array Modified result
     */
    public function after_install($result, $hook_extra, $result_data) {
        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $result;
        }

        global $wp_filesystem;

        // GitHub releases create folders like "repo-name-v1.0.0"
        // We need to rename to match the expected plugin folder
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->plugin_basename);
        $temp_folder = $result['destination'];

        // If the folder name doesn't match, rename it
        if ($temp_folder !== $plugin_folder && $wp_filesystem->exists($temp_folder)) {
            // Remove old folder if it exists
            if ($wp_filesystem->exists($plugin_folder)) {
                $wp_filesystem->delete($plugin_folder, true);
            }
            // Rename new folder
            $wp_filesystem->move($temp_folder, $plugin_folder);
            $result['destination'] = $plugin_folder;
        }

        return $result;
    }
}
