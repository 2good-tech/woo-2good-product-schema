<?php
/**
 * Plugin Name: 2GOOD Product Schema
 * Plugin URI: 
 * Description: Adds Product structured data schema markup to WooCommerce.
 * Author: 2GOOD Technologies Ltd.
 * Author URI: https://2good.tech
 * Version: 1.0.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
};

// Ensuring we execute the main only on product pages!
function is_product_page() {
    return function_exists('is_product') && is_product();
};

add_action('template_redirect', 'load_2good_product_schema');

function load_2good_product_schema() {
	
    if (is_product_page()) {
        // First remove the Woo default schema
        remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
        // Everything else is handled in the /includes/main.php file
        define('TWOGOOD_PRODUCT_SCHEMA_DIR', plugin_dir_path(__FILE__));
        include_once(TWOGOOD_PRODUCT_SCHEMA_DIR . 'includes/main.php');
    }
};
