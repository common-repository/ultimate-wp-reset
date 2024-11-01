<?php
/*
Plugin Name: Ultimate WordPress Reset
Description: Resets the WordPress database back to default state with one click. Deletes all settings and content. Does not modify any files only resets the database.
Author: MagniumThemes
Version: 1.0
Author URI: http://magniumthemes.com/
*/

// Allow to use plugin only by admin
if ( is_admin() && ! class_exists( 'Ultimate_WordPress_Reset' ) ) :

class Ultimate_WordPress_Reset {
	/**
	 * The class constructor.
	 * contains Action/Filter Hooks
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'favorite_actions', array( $this, 'favorites' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_link' ) );
		add_filter( 'wp_mail', array( $this, 'hijack_mail' ), 1 );
	}

	/**
	 * favorite_actions filter hook operations
	 * While this plugin is active put a link to the reset page in the favorites drop down.
	 */
	public function favorites( $actions ) {
		$reset['tools.php?page=ultimate-wordpress-reset'] = array( esc_html__( 'Reset WordPress' ), 'level_10' );
		return array_merge( $reset, $actions );
	}

	/**
	 * wp_before_admin_bar_render action hook operations
	 * While this plugin is active put a link to the reset page in the admin bar under the site title
	 */
	public function admin_bar_link() {
		global $wp_admin_bar;
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'ultimate-wordpress-reset',
				'title'  => 'Reset WordPress',
				'href'   => admin_url( 'tools.php?page=ultimate-wordpress-reset' )
			)
		);
	}

	/**
	 * admin_init action hook operations
	 * Checks for wordpress_reset post value and if there deletes all wp tables
	 * and performs an install, populating the users previous password also
	 */
	public function admin_init() {
		global $current_user;

		$wordpress_reset = ( isset( $_POST['wordpress_reset'] ) && $_POST['wordpress_reset'] == 'true' ) ? true : false;
		$wordpress_reset_confirm = ( isset( $_POST['wordpress_reset_confirm'] ) && $_POST['wordpress_reset_confirm'] == 'reset' ) ? true : false;
		$valid_nonce = ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wordpress_reset' ) ) ? true : false;

		if ( $wordpress_reset && $wordpress_reset_confirm && $valid_nonce ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

			$blogname    = get_option( 'blogname' );
			$admin_email = get_option( 'admin_email' );
			$blog_public = get_option( 'blog_public' );

			if ( $current_user->user_login != 'admin' ) {
				$user = get_user_by( 'login', 'admin' );
			}

			if ( empty( $user->user_level ) || $user->user_level < 10 ) {
				$user = $current_user;
			}

			global $wpdb, $reactivate_wp_reset_plugins;

			$prefix = str_replace( '_', '\_', $wpdb->prefix );
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}

			$result = wp_install( $blogname, $user->user_login, $user->user_email, $blog_public );
			extract( $result, EXTR_SKIP );

			$query = $wpdb->prepare( "UPDATE $wpdb->users SET user_pass = %s, user_activation_key = '' WHERE ID = %d", $user->user_pass, $user_id );
			$wpdb->query( $query );

			$get_user_meta    = function_exists( 'get_user_meta' ) ? 'get_user_meta' : 'get_usermeta';
			$update_user_meta = function_exists( 'update_user_meta' ) ? 'update_user_meta' : 'update_usermeta';

			if ( $get_user_meta( $user_id, 'default_password_nag' ) ) {
				$update_user_meta( $user_id, 'default_password_nag', false );
			}

			if ( $get_user_meta( $user_id, $wpdb->prefix . 'default_password_nag' ) ) {
				$update_user_meta( $user_id, $wpdb->prefix . 'default_password_nag', false );
			}

			if ( defined( 'REACTIVATE_WP_RESET' ) && REACTIVATE_WP_RESET === true ) {
				activate_plugin( plugin_basename( __FILE__ ) );
			}

			if ( ! empty( $reactivate_wp_reset_plugins ) ) {
				foreach ( $reactivate_wp_reset_plugins as $plugin ) {
					$plugin = plugin_basename( $plugin );
					if ( ! is_wp_error( validate_plugin( $plugin ) ) ) {
						activate_plugin( $plugin );
					}
				}
			}

			wp_clear_auth_cookie();
			wp_set_auth_cookie( $user_id );

			wp_redirect( admin_url() . '?reset' );
			exit();
		}

		if ( array_key_exists( 'reset', $_GET ) && stristr( $_SERVER['HTTP_REFERER'], 'wordpress-reset' ) ) {
			add_action( 'admin_notices', array( &$this, 'reset_notice' ) );
		}
	}

	/**
	 * admin_notices action hook operations
	 * Inform the user that WordPress has been successfully reset
	 */
	public function reset_notice() {
		$user = get_user_by( 'id', 1 );
		echo '<div id="message" class="updated fade"><p><strong>WordPress has been reset back to defaults. The user "' . $user->user_login . '" was recreated with its previous password.</strong></p></div>';
		do_action( 'wordpress_reset_post', $user );
	}

	/**
	 * Overwrite the password, because we actually reset it after this email goes out
	 */
	public function hijack_mail( $args ) {
		if ( preg_match( '/Your new WordPress (blog|site) has been successfully set up at/i', $args['message'] ) ) {
			$args['message'] = str_replace( 'Your new WordPress site has been successfully set up at:', 'Your WordPress site has been successfully reset, and can be accessed at:', $args['message'] );
			$args['message'] = preg_replace( '/Password:.+/', 'Password: previously specified password', $args['message'] );
		}
		return $args;
	}

	/**
	 * admin_print_scripts action hook operations
	 * Enqueue jQuery to the head
	 */
	public function admin_js() {
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * admin_footer action hook operations
	 * Do some jQuery stuff to warn the user before submission
	 */
	public function footer_js() { ?>
		<script type="text/javascript">
		/* <![CDATA[ */
			jQuery('#wordpress_reset_submit').click(function(){
				if ( jQuery('#wordpress_reset_confirm').val() == 'reset' ) {
					var message = 'This action is not reversable.\n\nClicking "OK" will reset your database back to it\'s defaults. Click "Cancel" to abort.'
					var reset = confirm(message);
					if ( reset ) {
						jQuery('#wordpress_reset_form').submit();
					} else {
						jQuery('#wordpress_reset').val('false');
						return false;
					}
				} else {
					alert('Invalid confirmation word. Please type the word \'reset\' in the confirmation field.');
					return false;
				}
			});
		/* ]]> */
		</script>
		<?php
	}

	/**
	 * admin_menu action hook operations
	 * Add the settings page
	 */
	public function add_page() {
		if ( current_user_can( 'level_10' ) && function_exists( 'add_management_page' ) )
			$hook = add_management_page( 'WordPress Reset', 'WordPress Reset', 'level_10', 'ultimate-wordpress-reset', array( &$this, 'admin_page' ) );
			add_action( "admin_print_scripts-{$hook}", array( &$this, 'admin_js' ) );
			add_action( "admin_footer-{$hook}", array( &$this, 'footer_js' ) );
	}

	/**
	 * add_option_page callback operations
	 * The settings page
	 */
	function admin_page() {
		global $current_user, $reactivate_wp_reset_plugins;
		if ( isset( $_POST['wordpress_reset_confirm'] ) && $_POST['wordpress_reset_confirm'] != 'reset' ) {
			echo '<div class="error fade"><p><strong>Invalid confirmation word. Please type the word \'reset\' in the confirmation field.</strong></p></div>';
		} elseif ( isset( $_POST['_wpnonce'] ) ) {
			echo '<div class="error fade"><p><strong>Invalid nonce. Please try again.</strong></p></div>';
		}

		$missing = array();
		if ( ! empty( $reactivate_wp_reset_plugins ) ) {
			foreach ( $reactivate_wp_reset_plugins as $key => $plugin ) {
				if ( is_wp_error( validate_plugin( $plugin ) ) ) {
					unset( $reactivate_wp_reset_plugins[$key] );
					$missing[] = $plugin;
				}
			}
		}

		$will_reactivate = ( defined( 'REACTIVATE_WP_RESET') && REACTIVATE_WP_RESET === true ) ? true : false;

		$admin = get_user_by( 'login', 'admin' );
		?>
		<div class="wrap">
			<div id="icon-tools" class="icon32"><br /></div>
				<div id="welcome-panel" class="welcome-panel">
				<div class="welcome-panel-content">
				<h2>Welcome to Ultimate WordPress Reset</h2>
				<p class="about-description">You are at one click from cleaning up your WebSite and starting from scratch!</p>
				<h3>What you will get after reset?</h3>
				<ul>
					<?php if ( ! isset( $admin->user_login ) || $admin->user_level < 10 ) : $user = $current_user; ?>
					<li><?php printf( esc_html__( '- The user %s will be recreated with its current password and admin user level.', 'uwp-reset' ), '<strong>' . esc_html( $user->user_login ) . '</strong>' ); ?></li>
					<?php else : ?>
					<li><?php esc_html_e( '- The "admin" user exists and will be recreated with its current password.', 'uwp-reset' ); ?></li>
					<?php endif; ?>
					<li><?php esc_html_e( '- All your themes, plugins will be deactivated and all its settings will be resetted.', 'uwp-reset' ); ?></li>
					<li><?php esc_html_e( '- All your content will be removed (posts, pages, custom post types, comments, menus, users and WordPress settings).', 'uwp-reset' ); ?></li>
					<li><?php esc_html_e( '- None of your files will be removed.', 'uwp-reset' ); ?></li>
					<?php if ( $will_reactivate ) : ?>
					<li><?php _e( '- This plugin <strong>will be automatically reactivated</strong>.', 'uwp-reset' ); ?></li>
					<?php else : ?>
					<li><?php _e( '- This plugin <strong>will not be automatically reactivated</strong> after the reset.', 'uwp-reset' ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $reactivate_wp_reset_plugins ) ) : ?>
					<?php esc_html_e( '- The following additional plugins will be reactivated:', 'uwp-reset' ); ?>
					<li>
						<ul style="list-style-type: disc;">
							<?php foreach ( $reactivate_wp_reset_plugins as $plugin ) : ?>
								<?php $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin ); ?>
								<li style="margin: 5px 0 0 30px;"><strong><?php echo esc_html( $plugin_data['Name'] ); ?></strong></li>
							<?php endforeach; ?>
							<?php unset( $reactivate_wp_reset_plugins, $plugin, $plugin_data ); ?>
						</ul>
					</li>
					<?php endif; ?>
					<?php if ( ! empty( $missing ) ) : ?>
					<?php esc_html_e( '- The following additional plugins are missing and cannot be reactivated:', 'uwp-reset' ); ?>
					<li>
						<ul style="list-style-type: disc;">
							<?php foreach ( $missing as $plugin ) : ?>
								<li style="margin: 5px 0 0 30px;"><strong><?php echo esc_html( $plugin ); ?></strong></li>
							<?php endforeach; ?>
							<?PHP unset( $missing, $plugin ); ?>
						</ul>
					</li>
					<?php endif; ?>
					<li>- <?php esc_html_e( 'You will be redirected to the dashboard after reset.', 'uwp-reset' ); ?></li>
					<li>- <?php esc_html_e( 'If you want to undo this action in future you need to make full site backup first from your hosting side.', 'uwp-reset' ); ?></li>
				</ul>
		
						<h3>Confirm Reset action</h3>
						<p><?php printf( esc_html__( 'Type %s in the confirmation field and then click the reset button:', 'uwp-reset' ), '<strong>reset</strong>' ); ?></p>
						<form id="wordpress_reset_form" action="" method="post">
							<?php wp_nonce_field( 'wordpress_reset' ); ?>
							<input id="wordpress_reset" type="hidden" name="wordpress_reset" value="true" />
							<input id="wordpress_reset_confirm" type="text" name="wordpress_reset_confirm" value="" />
							<p>
								<input id="wordpress_reset_submit" type="submit" name="Submit" class="button button-primary button-hero" value="<?php esc_html_e( 'Reset WordPress' ); ?>" />
							</p>
							<br/>
						</form>
						
				</div>
				</div>


		</div>
		<?php
	}
}

// Instantiate the class
$Ultimate_WordPress_Reset = new Ultimate_WordPress_Reset();

// End if for is_admin
endif;