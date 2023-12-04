<?php
/*

    Plugin Name: 2GOOD Product Schema
    Description: Adds 2GOOD schema markup to WooCommerce product pages.
    Version: 1.0.0
    Author: 2GOOD Tech LTD
    License: GPLv2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html
    
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
        // Everything currently in v1.1 is handled in the /includes/main.php file

        define('TWOGOOD_PRODUCT_SCHEMA_DIR', plugin_dir_path(__FILE__));
        include_once(TWOGOOD_PRODUCT_SCHEMA_DIR . 'includes/main.php');
    }
};