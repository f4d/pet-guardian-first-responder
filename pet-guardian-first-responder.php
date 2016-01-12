<?php
require('vendor/twilio/sdk/Services/Twilio.php');

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             0.9
 * @package           Pet_Guardian_First_Responder
 *
 * @wordpress-plugin
 * Plugin Name:       Pet Guardian First Responder
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           0.9
 * Author:            David A. Powers / rezon8.net
 * Author URI:        http://rezon8.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pet-guardian-first-responder
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-name-activator.php
 */
function activate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pet-guardian-first-responder-activator.php';
	Pet_Guardian_First_Responder_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-name-deactivator.php
 */
function deactivate_plugin_name() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pet-guardian-first-responder-deactivator.php';
	Pet_Guardian_First_Responder_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_pet-guardian-first-responder' );
register_deactivation_hook( __FILE__, 'deactivate_pet-guardian-first-responder' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pet-guardian-first-responder.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pet_guardian_first_responder() {

	$plugin = new Pet_Guardian_First_Responder();
	$plugin->run();

}
run_pet_guardian_first_responder();
