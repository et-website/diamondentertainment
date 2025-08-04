<?php
/*
Plugin Name: ET Website Support
Plugin URI: https://your-website.com/
Description: Bộ công cụ hỗ trợ và tùy chỉnh website, có khả năng tự động cập nhật.
Version: 1.0.2
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

// Ví dụ một hàm chức năng
add_filter('admin_footer_text', fn() => 'ET Website Support | Website được hỗ trợ bởi ET');

// ... và các hàm khác của bạn ...
