<?php
/**
 * Plugin Name: Dynamic ACF Blocks
 * Version:     0.1
 * Plugin URI:  https://reboot.com.tr
 * Description: Adds ability to create ACF Blocks via WordPress admin panel.
 * Author:      Reboot
 * Author URI:  https://reboot.com.tr
 * Text Domain: dynamic-acf-blocks
 * Domain Path: /languages/
 */

if ( ! function_exists( 'add_filter' ) ) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
}

if ( ! defined( 'DYNAMIC_ACF_BLOCKS_FILE' ) ) {
    define( 'DYNAMIC_ACF_BLOCKS_FILE', __FILE__ );
}

require_once dirname( DYNAMIC_ACF_BLOCKS_FILE ) . '/dynamic_acf_blocks_key_helper.php';
require_once dirname( DYNAMIC_ACF_BLOCKS_FILE ) . '/dynamic-acf-blocks-main.php';