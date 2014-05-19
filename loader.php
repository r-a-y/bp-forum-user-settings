<?php
/*
Plugin Name: BP Forum User Settings
Description: Allow users to configure various forum options in BuddyPress. Requires bbPress.
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
Version: 0.1
License: GPLv2 or later
*/

/**
 * BP Forum User Settings
 *
 * @package BP_FUS
 * @subpackage Loader
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Only load the plugin code if BuddyPress is activated.
 */
function bp_fus_include() {
	// some pertinent defines
	define( 'BP_FUS_DIR', dirname( __FILE__ ) );
	define( 'BP_FUS_URL', plugins_url( basename( BP_FUS_DIR ) ) . '/' );

	require constant( 'BP_FUS_DIR' ) . '/bp-fus-core.php';
}
add_action( 'bp_include', 'bp_fus_include' );
