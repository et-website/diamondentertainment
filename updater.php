<?php
if (!defined('ABSPATH')) {
    exit;
}

class ET_Website_Support_Updater {
    private $file;
    private $plugin_data;
    private $basename;
    private $github_username;
    private $github_repo;
    private $github_response;

    public function __construct($file, $github_username, $github_repo) {
        $this->file = $file;
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;

        add_action('admin_init', [$this, 'set_plugin_properties']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient']);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        // THAY ĐỔI QUAN TRỌNG NẰM Ở HÀM NÀY
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    public function set_plugin_properties() {
        $this->plugin_data = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
    }

    private function get_repo_release_info() {
        if (!empty($this->github_response)) {
            return;
        }
        $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get($url);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        $this->github_response = json_decode(wp_remote_retrieve_body($response));
    }

    public function modify_transient($transient) {
        if (property_exists($transient, 'checked') && $checked = $transient->checked) {
            $this->get_repo_release_info();
            if ($this->github_response && version_compare($this->plugin_data['Version'], $this->github_response->tag_name, '<')) {
                $plugin = [
                    'url' => $this->plugin_data['PluginURI'],
                    'slug' => current(explode('/', $this->basename)),
                    'package' => $this->github_response->zipball_url,
                    'new_version' => $this->github_response->tag_name
                ];
                $transient->response[$this->basename] = (object) $plugin;
            }
        }
        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if (!empty($args->slug) && $args->slug == current(explode('/', $this->basename))) {
            $this->get_repo_release_info();
            if ($this->github_response) {
                return (object) [
                    'name' => $this->plugin_data['Name'],
                    'slug' => $this->basename,
                    'version' => $this->github_response->tag_name,
                    'author' => $this->plugin_data['Author'],
                    'homepage' => $this->plugin_data['PluginURI'],
                    'short_description' => $this->plugin_data['Description'],
                    'sections' => ['description' => $this->plugin_data['Description'], 'changelog' => nl2br(esc_html($this->github_response->body))],
                    'download_link' => $this->github_response->zipball_url
                ];
            }
        }
        return $result;
    }
    
    /**
     * Xử lý sau khi cài đặt, di chuyển file từ thư mục tạm của GitHub
     * vào đúng thư mục plugin đang tồn tại.
     *
     * @param bool   $response   Trạng thái cài đặt.
     * @param array  $hook_extra Dữ liệu bổ sung.
     * @param array  $result     Kết quả chi tiết.
     * @return array Kết quả sau khi xử lý.
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Thư mục cài đặt của plugin hiện tại (VD: /.../wp-content/plugins/et-website-support/)
        $install_directory = plugin_dir_path( $this->file );
        
        // Di chuyển thư mục plugin mới được giải nén vào đúng vị trí
        $wp_filesystem->move( $result['destination'], $install_directory, true );

        // Kích hoạt lại plugin
        activate_plugin( $this->basename );

        return $result;
    }
}
