<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load up our plugin into BuddyPress.
 */
function bp_fus_init() {
	buddypress()->fus = new BP_Forum_User_Settings;
}
add_action( 'bp_loaded', 'bp_fus_init' );

if ( ! class_exists( 'BP_Forum_User_Settings' ) ) :
/**
 * BP Forum User Settings Core.
 */
class BP_Forum_User_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// bbPress plugin needs to be active
		if ( ! class_exists( 'bbPress' ) ) {
			return;
		}

		// settings component needs to be active
		if ( ! bp_is_active( 'settings' ) ) {
			return;
		}

		$this->includes();
		$this->setup_hooks();
	}

	/**
	 * Includes.
	 */
	protected function includes() {
		require constant( 'BP_FUS_DIR' ) . '/includes/bp-fus-functions.php';
	}

	/**
	 * Setup hooks.
	 *
	 * bbPress-specific hooks are in bp-fus-functions.php.
	 */
	protected function setup_hooks() {
		// setup globals
		add_action( 'bp_forums_setup_globals', array( $this, 'setup_globals' ) );

		// nav / adminbar
		add_filter( 'bp_settings_setup_nav',   array( $this, 'setup_nav' ) );
		add_filter( 'bp_forums_admin_nav',     array( $this, 'forums_admin_nav' ) );
		add_filter( 'bp_settings_admin_nav',   array( $this, 'settings_admin_nav' ) );
	}

	/**
	 * Setup globals.
	 */
	public function setup_globals() {
		// Define default slug
		if ( ! defined( 'BP_FUS_SLUG' ) ) {
			define( 'BP_FUS_SLUG', 'forums' );
		}

		$this->slug = apply_filters( 'bp_forum_user_settings_slug', sanitize_title( constant( 'BP_FUS_SLUG' ) ) );

		// get settings for logged in user
		if ( bp_loggedin_user_id() ) {
			$this->settings = get_user_meta( bp_loggedin_user_id(), 'bp_forum_user_settings', true );
		}
	}

	/**
	 * Setup settings nav.
	 */
	public function setup_nav() {
		// Determine user to use
		if ( bp_displayed_user_domain() ) {
			$user_domain = bp_displayed_user_domain();
		} elseif ( bp_loggedin_user_domain() ) {
			$user_domain = bp_loggedin_user_domain();
		} else {
			return;
		}

		$settings_link = trailingslashit( $user_domain . bp_get_settings_slug() );

		bp_core_new_subnav_item( array(
			'name'            => __( 'Forums', 'bp-fus' ),
			'slug'            => $this->slug,
			'parent_url'      => $settings_link,
			'parent_slug'     => bp_get_settings_slug(),
			'screen_function' => array( 'BP_Forum_User_Settings_Screens', 'init' ),
			'position'        => apply_filters( 'bp_fus_position', 31 ),
			'item_css_id'     => 'settings-forums'
		) );
	}

	/**
	 * Inject "Settings" nav item to WP adminbar's "Forums" main nav.
	 *
	 * @param array $retval
	 * @return array
	 */
	public function forums_admin_nav( $retval ) {
		return $this->add_to_adminbar(
			$retval,
			'settings',
			_x( 'Settings', 'Adminbar settings subnav', 'bp-fus' )
		);
	}

	/**
	 * Inject "Forums" nav item to WP adminbar's "Settings" main nav.
	 *
	 * @param array $retval
	 * @return array
	 */
	public function settings_admin_nav( $retval ) {
		return $this->add_to_adminbar(
			$retval,
			'forums',
			_x( 'Forums', 'Adminbar settings subnav', 'bp-fus' )
		);
	}

	/** UTILITY *******************************************************/

	/**
	 * Utilty method to inject an item into the BP adminbar array.
	 *
	 * @param $retval The BP adminbar nav array.
	 * @param string $id The id for the adminbar subnav item.
	 * @param string $label The label name for the adminbar item.
	 * @return array Modified BP adminbar nav array.
	 */
	protected function add_to_adminbar( $retval = array(), $id = '', $label = '' ) {
		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		$parent = ( 'forums' === $id ) ? 'settings' : 'forums';

		$new_item = array(
			'parent' => "my-account-{$parent}",
			'id'     => "my-account-{$parent}-{$id}",
			'title'  => $label,
			'href'   => bp_loggedin_user_domain() . bp_get_settings_slug() . '/' . $this->slug . '/',
		);

		// inject item in between "Email" and "Delete Account" subnav items
		$last = end( $retval );

		// Settings nav - inject just before 'Delete account' item
		if ( 'my-account-settings-delete-account' === $last['id'] ) {
			$offset = key( $retval );

			$inject = array();
			$inject[$offset] = $new_item;

			$retval = array_merge( array_slice( $retval, 0, $offset, true ), $inject, array_slice( $retval, $offset, NULL, true ) );

		// just add nav item to the end
		} else {
			$inject = array();
			$inject[] = $new_item;
			$retval = array_merge( $retval, $inject );
		}

		return $retval;
	}

}
endif;

/**
 * Screen loader class for BP Forum User Settings.
 */
class BP_Forum_User_Settings_Screens {
	/**
	 * Initializes the user settings forum screen.
	 */
	public static function init() {
		self::validate();

		add_action( 'bp_template_content', array( __CLASS__, 'content' ) );

		// this is for bp-default themes
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Validate the submitted forum settings options.
	 */
	protected static function validate() {
		if ( empty( $_POST['save'] ) ) {
			return;
		}

		check_admin_referer( 'bp_forum_user_settings', 'bp-fus-nonce' );

		update_user_meta( bp_loggedin_user_id(), 'bp_forum_user_settings', $_POST['settings'] );

		// make sure we update the user settings property
		buddypress()->fus->settings = $_POST['settings'];

		bp_core_add_message( __( 'Forum settings updated', 'bp-fus' ) );
	}

	/**
	 * Content for the user settings forum screen.
	 *
	 * @todo Use a template part for this.
	 * @todo Breakout dropdown options into filterable functions
	 */
	public static function content() {
	?>

		<form action="<?php echo esc_url( bp_displayed_user_domain() . bp_get_settings_slug() . '/' . buddypress()->fus->slug . '/' ); ?>" method="post" class="standard-form" id="settings-form">

			<div class="thread_display">
				<label for="thread_display"><?php _e( 'Thread Display Mode:', 'bp-fus' ); ?></label>
				<select name="settings[thread_display]" id="thread_display" >
					<option value="oldest" <?php selected( bp_fus_get_setting( 'thread_display' ), 'oldest' ); ?>>Linear - Oldest First</option>
					<option value="newest" <?php selected( bp_fus_get_setting( 'thread_display' ), 'newest' ); ?>>Linear - Newest First</option>
					<?php if ( bbp_allow_threaded_replies() ) : ?>
						<option value="threaded" <?php selected( bp_fus_get_setting( 'thread_display' ), 'threaded' ); ?>>Threaded</option>
					<?php endif; ?>
				</select>

				<p class="description"><?php _e( 'Here you can choose the display mode for threads.', 'bp-fus' ); ?></p>
			</div>

			<div class="per_page">
				<label for="per_page"><?php _e( 'Number of Posts to Show Per Page:', 'bp-fus' ); ?></label>
				<select name="settings[per_page]" id="per_page">
					<option value="" <?php selected( bp_fus_get_setting( 'per_page' ), '' ); ?>>Use Forum Default</option>
					<option value="5" <?php selected( bp_fus_get_setting( 'per_page' ), '5' ); ?>>Show 5 posts per page</option>
					<option value="10" <?php selected( bp_fus_get_setting( 'per_page' ), '10' ); ?>>Show 10 posts per page</option>
					<option value="20" <?php selected( bp_fus_get_setting( 'per_page' ), '20' ); ?>>Show 20 posts per page</option>
					<option value="30" <?php selected( bp_fus_get_setting( 'per_page' ), '30' ); ?>>Show 30 posts per page</option>
					<option value="40" <?php selected( bp_fus_get_setting( 'per_page' ), '40' ); ?>>Show 40 posts per page</option>
				</select>

				<p class="description">
					<?php _e( 'Use this option to set the number of posts to show in a thread before splitting the display into multiple pages.', 'bp-fus' ); ?>
					<?php if ( bbp_allow_threaded_replies() ) : ?>
						<?php _e( 'Note: If "Thread Display Mode" is set to "Threaded", this option does not take effect due to an incompatibility between the two modes.', 'bp-fus' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<?php do_action( 'bp_forum_user_settings_extra' ); ?>

			<div class="submit">
				<input type="submit" name="save" value="<?php _e( 'Save Changes', 'buddypress' ); ?>" id="submit" class="auto" />
			</div>

			<?php wp_nonce_field( 'bp_forum_user_settings', 'bp-fus-nonce' ); ?>

		</form>

		<script type="text/javascript">
		jQuery(function($){
			var per_page = $('#per_page'),
				per_page_label = $('.per_page label'),
				per_page_label_color = per_page_label.css('color');

			if ( 'threaded' === $('#thread_display option').filter(':selected').val() ) {
				per_page_toggle();
			}

			$( '#thread_display' ).change(function() {
				if ( 'threaded' !== $(this).val() ) {
					per_page_toggle( 'enabled' );
				} else {
					per_page_toggle();
				}
			});

			function per_page_toggle( state ) {
				state = state || 'disabled';

				if ( 'disabled' === state ) {
					per_page.prop( 'disabled', true );
					per_page_label.css( 'color', '#aaa' );
				} else {
					per_page.prop( 'disabled', false );
					per_page_label.css( 'color', per_page_label_color );
				}
			}
		});
		</script>
	<?php
	}
}
