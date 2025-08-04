<?php
/*
Plugin Name: ET Website Support
Plugin URI: https://your-website.com/
Description: Bộ công cụ hỗ trợ và tùy chỉnh website, có khả năng tự động cập nhật.
Version: 1.0.0
Author: ET Website Support
Author URI: https://your-website.com/
License: GPLv2 or later
Text Domain: et-website-support
*/

if (!defined('ABSPATH')) {
    exit;
}

// Nạp file updater
require_once(__DIR__ . '/updater.php');

// Khởi tạo updater
if (is_admin()) {
    new ET_Website_Support_Updater(
        __FILE__,
        'et-website',           // Tên người dùng GitHub của bạn
        'diamondentertainment'  // Tên kho chứa trên GitHub
    );
}

/*
|--------------------------------------------------------------------------
| CÁC CHỨC NĂNG CỦA PLUGIN
|--------------------------------------------------------------------------
|
| Phần code chức năng của bạn (tạo widget, ẩn menu, v.v.)
| sẽ nằm ở dưới đây. Bạn có thể giữ nguyên phần này như cũ.
|
*/



// Tắt XML-RPC và Pingback
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback'], $headers['x-pingback']);
    return $headers;
});
add_filter('pings_open', '__return_false', 9999);
add_filter('pre_update_option_enable_xmlrpc', '__return_false');
add_filter('pre_option_enable_xmlrpc', '__return_zero');

// Xóa thông tin phiên bản WordPress
add_filter('the_generator', fn() => '');

/* Vô hiệu hóa chỉnh sửa file và plugin */
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

// Thêm cột ảnh đại diện vào admin
add_filter('manage_post_posts_columns', fn($cols) => array_slice($cols, 0, 1, true) + ['featured_image' => 'Ảnh đại diện'] + array_slice($cols, 1, null, true));
add_action('manage_posts_custom_column', function ($col, $id) {
    if ($col === 'featured_image') {
        $thumbnail_id = get_post_thumbnail_id($id);
        $src = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : get_stylesheet_directory_uri() . '/placeholder.png';
        echo '<img data-id="' . esc_attr($thumbnail_id ?: -1) . '" src="' . esc_url($src) . '" style="max-width:60px; height:auto;">';
    }
}, 10, 2);

// Thay footer admin
add_filter('admin_footer_text', fn() => 'ET Website Support1 | Website được hỗ trợ bởi ET');

// Soạn thảo cũ
add_filter('use_block_editor_for_post', '__return_false');

// Ẩn menu bar
add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $items = [
        'wp-logo', 'flatsome_panel', 'new-content', 'wpseo-menu', 'archive', 'about', 'wporg', 'documentation', 'support-forums', 'feedback', 'vc_inline-admin-bar-link', 'admin.php?page=jnews', 'updates', 'comments',
        'w3tc', 'rank-math', 'litespeed-menu', 'wpdiscuz', 'view', 'edit',
        'customize', 'stats', 'wpo_purge_cache', 'notes', 'search', 'menus', 'widgets', 'themes', 'wp-rocket'
    ];
    foreach ($items as $item) $wp_admin_bar->remove_menu($item);
});

// Ẩn menu không cần thiết trong Admin Dashboard
add_filter('acf/settings/show_admin', '__return_false');
add_action('admin_menu', function () {
    $menus_to_remove = [
        'index.php', 'edit.php', 'upload.php', 'edit.php?post_type=page',
        'edit-comments.php', 'themes.php', 'plugins.php', 'users.php',
        'tools.php', 'options-general.php',
        'smtp-setting', 'edit.php?post_type=featured_item', 'edit.php?post_type=blocks', 'wpcf7', 'flatsome-panel'
    ];
    foreach ($menus_to_remove as $menu) remove_menu_page($menu);
}, 999);

// Ẩn menu trái và các thành phần khác bằng CSS
add_action('admin_head', function () {
    echo '<style>
        #adminmenumain { display: none !important; }
        #wpcontent, #wpfooter { margin-left: 0 !important; }
        #postbox-container-3, #flatsome-notice, #postbox-container-4, .notice-info, #contextual-help-link-wrap { display: none !important; }
    </style>';
});

// Đổi màu thanh menu bar Admin
add_action('wp_head', 'ews_change_admin_bar_color');
add_action('admin_head', 'ews_change_admin_bar_color');
function ews_change_admin_bar_color()
{
    if (is_admin_bar_showing()) {
        echo '<style>
            #wpadminbar { background: #b8860b !important; }
            #wpadminbar .ab-item, #wpadminbar a.ab-item { color: #fff !important; }
            #wpadminbar .ab-item:hover { color: #ffcc00 !important; }
        </style>';
    }
}

// Thay đổi câu chào "Chào,"
add_filter('admin_bar_menu', function ($bar) {
    if ($node = $bar->get_node('my-account'))
        $bar->add_node(['id' => 'my-account', 'title' => str_replace(['Chào,', 'Xin chào,', 'Howdy,'], 'Xin chào Admin:', $node->title)]);
}, 25);

// Xoá widgets mặc định trên Dashboard
add_action('wp_dashboard_setup', function () {
    global $wp_meta_boxes;
    $widgets_to_remove = [
        'dashboard_primary', 'dashboard_plugins', 'dashboard_right_now', 'dashboard_quick_press',
        'dashboard_recent_drafts', 'dashboard_secondary', 'dashboard_recent_comments',
        'dashboard_activity', 'wpseo-dashboard-overview', 'flatsome-notice', 'rank_math_dashboard_widget', 'wp_mail_smtp_reports_widget_lite'
    ];
    foreach ($widgets_to_remove as $widget) {
        remove_meta_box($widget, 'dashboard', 'normal');
        remove_meta_box($widget, 'dashboard', 'side');
    }
    remove_action('welcome_panel', 'wp_welcome_panel');
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_site_health']);
}, 999);


// Thêm Dashboard tùy chỉnh
add_action('wp_dashboard_setup', 'ews_welcome_dashboard_widget', 1);
function ews_welcome_dashboard_widget() {
    wp_add_dashboard_widget('et_support_widget', 'Bảng điều khiển ET Support', 'ews_dashboard_content');
}

function ews_dashboard_content() {
    $sections = [
        'HỆ THỐNG' => [
            ['upload.php', 'media', 'Thư viện'],
            ['users.php', 'users', 'Thành viên'],
            ['edit-comments.php', 'comments', 'Bình luận'],
            ['options-general.php', 'settings', 'Cài đặt'],
            ['edit.php', 'posts', 'Bài viết'],
        ],
        'CHỈNH SỬA THEO MODULE' => [
            ['post.php?post=1034&action=edit&app=uxbuilder&type=editor', 'settings', 'Slide Trang chủ'],
            ['post.php?post=1044&action=edit&app=uxbuilder&type=editor', 'add', 'Sứ mệnh - Tầm nhìn - GTCL'],
            ['post.php?post=1644&action=edit&app=uxbuilder&type=editor', 'posts', 'SLIDE đối tác'],
        ],
    ];

    foreach ($sections as $title => $links) {
        echo "<div class='default-container'><h2>" . esc_html($title) . "</h2><hr></div><div class='icon-container'>";
        foreach ($links as [$url, $class, $text]) {
            echo "<div class='column'><a href='" . esc_url(admin_url($url)) . "' class='" . esc_attr($class) . "' target='_blank'>" . esc_html($text) . "</a></div>";
        }
        echo "</div>";
    }
    ?>
    <style>
        #wpbody-content #dashboard-widgets #postbox-container-1 { width: 100%; }
        .default-container { display: grid; grid-template-columns: 1fr; padding: 20px; text-align: center; }
        .default-container h2 { font-size: 20px; }
        .default-container hr { height: 3px; background: #ebebeb; border: none; width: 10%; margin: 1em auto; }
        .icon-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); padding: 20px; text-align: center; }
        .column { background: #fff; box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px; font-size: 16px; margin: 3%; padding: 30px; transition: 0.5s; text-transform: uppercase; border-radius: 20px; }
        .column a { color: #000; text-decoration: none; display: block; }
        .column a:before { font-family: "dashicons"; font-size: 34px; display: block; color: #2681B0; margin-bottom: 4px; }
        .column:hover { background: #ffd881; border-radius: 20px; }
        <?php
        $icons = [
            'pages' => 'f123', 'users' => 'f110', 'posts' => 'f109', 'add' => 'f133',
            'media' => 'f104', 'plugin' => 'f106', 'theme' => 'f100', 'settings' => 'f108',
            'comments' => 'f101'
        ];
        foreach ($icons as $class => $code) {
            echo ".icon-container ." . esc_attr($class) . ":before { content: \"\\" . esc_attr($code) . "\"; }";
        }
        ?>
    </style>
    <?php
}