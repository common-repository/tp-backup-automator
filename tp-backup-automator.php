<?php

/**
 * Plugin Name: TP Backup Automator
 * Plugin URI: https://wordpress.org/plugins/tp-backup-automator/
 * Description: Backup & Restore your WordPress data and keep it safe.     
 * Version: 1.0.2
 * Author: ThemesPond
 * Author URI: https://themespond.com/
 * License: GNU General Public License v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Requires at least: 4.3
 * Tested up to: 4.9
 * Text Domain: tp-backup-automator
 * Domain Path: /languages/
 *
 * @package TPBA
 */
if ( !class_exists( 'TPBA' ) ) {

	final class TPBA {

		function __construct() {

			$this->defined();
			$this->hook();
			$this->includes();

			do_action( 'tpba_loaded' );
		}

		/**
		 * The single instance of the class.
		 *
		 * @var TPBA
		 * @since 1.0
		 */
		protected static $_instance = null;

		/**
		 * Main Tp Backup Instance.
		 *
		 * Ensures only one instance of TPBA is loaded or can be loaded.
		 *
		 * @since 1.0
		 * @static
		 * @see TPBA()
		 * @return TP Backup - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Call functions to WordPress hooks
		 * @since 1.0
		 * @return void
		 */
		public function hook() {
			register_activation_hook( __FILE__, array( 'TPBA_Install', 'install' ) );
			register_deactivation_hook( __FILE__, array( 'TPBA_Install', 'uninstall' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'admin_menu', array( $this, 'register_page' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );
		}

		/**
		 * Show notices in admin
		 * @since 1.0
		 */
		public function admin_notices( $hook ) {

			$user = new TPBA_User();
			$is_plugin_page = isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) == 'tp-backup-automator';

			if ( $user->get_token() == '' && $user->get_email() == '' && !$is_plugin_page ) {
				echo '<div class="notice notice-info tpba-notice"><p>';
				echo wp_kses_post( sprintf( __( '<a href="%s" class="button button-primary">Setup a backup email</a> Almost done! Backup & Restore your WordPress data and keep it safe.', 'tp-backup-automator' ), admin_url( 'tools.php?page=tp-backup-automator' ) ) );
				echo '</p></div>';
			} else if ( !$user->is_validate() && !$is_plugin_page ) {
				echo '<div class="notice notice-error tpba-notice"><p>';
				echo wp_kses_post( sprintf( __( '<strong>TP Backup Automator:</strong> Email and product key are invalid. <a href="%s">Fix now</a>', 'tp-backup-automator' ), admin_url( 'tools.php?page=tp-backup-automator' ) ) );
				echo '</p></div>';
			}
		}


		/**
		 * Include toolkit functions
		 * @since 1.0
		 */
		public function includes() {
			
			require TPBA_DIR . 'includes/helper-functions.php';
			require TPBA_DIR . 'includes/file-functions.php';
			require TPBA_DIR . 'includes/class-tpba-ajax.php';
			require TPBA_DIR . 'includes/class-tpba-user.php';
			require TPBA_DIR . 'includes/class-tpba-services.php';
			require TPBA_DIR . 'includes/class-tpba-scanner.php';
			require TPBA_DIR . 'includes/class-tpba-master.php';
			require TPBA_DIR . 'includes/class-tpba-log.php';
			require TPBA_DIR . 'includes/class-tpba-sql.php';
			require TPBA_DIR . 'includes/class-tpba-sql-dump.php';
			require TPBA_DIR . 'includes/class-tpba-install.php';
			require TPBA_DIR . 'includes/class-tpba-cron.php';
		}

		/**
		 * Register plugin page
		 */
		public function register_page() {
			add_submenu_page( 'tools.php', esc_html__( 'TP Backup Automator', 'tp-backup-automator' ), esc_html__( 'Backup Automator', 'tp-backup-automator' ), 'manage_options', 'tp-backup-automator', array( $this, 'settings_page' ) );
		}

		/**
		 * Setting page
		 */
		public function settings_page() {

			$user = new TPBA_User();

			if ( $user->is_validate() ) {
				tpba_template( 'plugin-page', array( 'user' => $user ) );
			} else {
				tpba_template( 'install', array( 'user' => $user ) );
			}
			
		}

		/**
		 * Activation function fires when the plugin is activated.
		 * 
		 * @since 1.0
		 * @return void
		 */
		public function activation() {

			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$active = sanitize_text_field($_GET['activate']);
				unset( $_GET['activate'] );
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}

		/**
		 * Defined 
		 */
		public function defined() {
			define( 'TPBA_URL', plugin_dir_url( __FILE__ ) );
			define( 'TPBA_DIR', plugin_dir_path( __FILE__ ) );
			define( 'TPBA_VER', '1.0.2' );
		}

		/**
		 * Enqueue admin script
		 * @since 1.0.0
		 * @param string $hook
		 * @return void
		 */
		public function admin_scripts( $hook ) {
			wp_enqueue_style( 'tpba-admin', TPBA_URL . 'assets/css/admin.css', array(), TPBA_VER );
			
			if ( $hook == 'tools_page_tp-backup-automator' ) {
				wp_enqueue_script( 'backbone' );
				wp_enqueue_script( 'underscore' );
				wp_enqueue_script( 'tpba-admin', TPBA_URL . 'assets/js/admin.js', array( 'jquery' ), time(), true );

				wp_enqueue_script( 'tpba-installer', TPBA_URL . 'assets/js/installer.js', array( 'jquery' ), time(), true );
				wp_localize_script( 'tpba-installer', 'tpba_var', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'invalid_email' => esc_html__( 'Please, enter a valid email.', 'tp-backup-automator' ),
					'invalid_token' => esc_html__( 'Please, enter a valid product key.', 'tp-backup-automator' ),
					'connecting'=> esc_html__( 'Connecting...','tp-backup-automator'),
					'prepare_file_error'=> esc_html__( 'Cannot prepare files.','tp-backup-automator'),
					'done'=> esc_html__( 'All done','tp-backup-automator'),
					'warning'=> esc_html__( 'Warning','tp-backup-automator'),
					'file_backup_done'=> esc_html__( 'files have backed up successfully.','tp-backup-automator'),
					'file_restore_done'=> esc_html__( 'files have restored successfully.','tp-backup-automator'),
					'confirm_backup'=> esc_html__( 'Are you sure you want to create a backup now?','tp-backup-automator'),
					'confirm_restore'=> esc_html__('All your current data will be changed, are you sure?','tp-backup-automator')
				) );
			}
		}

		/**
		 * Load Local files.
		 * @since 1.0.0
		 * @return void
		 */
		public function load_plugin_textdomain() {

			// Set filter for plugin's languages directory
			$dir = TPBA_DIR . 'languages/';
			$dir = apply_filters( 'tpba_languages_directory', $dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'tp-backup-automator' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'tp-backup-automator', $locale );

			$mofile_local = $dir . $mofile;

			$mofile_global = WP_LANG_DIR . '/tp-backup-automator/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				load_textdomain( 'tp-backup-automator', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				load_textdomain( 'tp-backup-automator', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'tp-backup-automator', false, $dir );
			}
		}
		
		/**
		 * Add links to Plugins page
		 * @since 1.0.0
		 * @return array
		 */
		function add_action_links( $links ) {

			$plugin_links = array(
				'page' => '<a href="' . esc_url( apply_filters( 'tpba_page_url', admin_url( 'tools.php?page=tp-backup-automator' ) ) ) . '" aria-label="' . esc_attr__( 'Settings', 'tp-backup-automator' ) . '">' . esc_html__( 'Settings', 'tp-backup-automator' ) . '</a>',
			);

			return array_merge( $links, $plugin_links );
		}

	}

	/**
	 * Main instance of TPBA.
	 *
	 * Returns the main instance of TPBA to prevent the need to use globals.
	 *
	 * @since  1.0
	 * @return TPBA
	 */
	function TPBA() {
		return TPBA::instance();
	}

	/**
	 * Global for backwards compatibility.
	 */
	$GLOBALS['TPBA'] = TPBA();
}
