<?php

/**
 *
 * Plugin Name: PodiPress
 * Plugin URI: https://podipress.com/plugin/
 * Description: Enables you to connect your WordPress to Podio using PodiPress
 * Version: 0.0.6
 * Author: Friedhelm Oja, Uwe Kamper
 * Author URI: https://podipress.com/
 * Text Domain: podipress
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;

require_once 'incl/class_podipress.php';

register_activation_hook( __FILE__, array( 'PodiPress', 'fo_podipress_install' ) );
register_activation_hook( __FILE__, array( 'PodiPress', 'fo_podipress_install_data' ) );

new PodiPress();
