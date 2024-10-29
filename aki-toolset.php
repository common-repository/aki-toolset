<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: Aki Toolset
Description: Install One Click Demo Import Plugin First. Import the demos of Aki Themes Product. The activated themes demo data will show under Appearance > Import Dummy Data.
Version:     1.0.3
Author:      Aki Themes
Author URI:  http://www.akithemes.com
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: aki-toolset
*/

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
define( 'AKI_TOOLSET_PATH', plugin_dir_path( __FILE__ ) );
define( 'AKI_TOOLSET_PLUGIN_NAME', 'aki-toolset' );
define( 'AKI_TOOLSET_VERSION', '1.0.3' );
define( 'AKI_TOOLSET_URL', plugin_dir_url( __FILE__ ) );
define( 'AKI_TOOLSET_TEMPLATE_URL', AKI_TOOLSET_URL.'inc/demo/' );

require AKI_TOOLSET_PATH . 'inc/init.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.2
 */
if( !function_exists( 'run_aki_toolset')){

    function run_aki_toolset() {

        return Aki_Toolset::instance();
    }
    run_aki_toolset()->run();
}