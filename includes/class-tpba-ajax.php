<?php

class TPBA_Ajax {

	public static function init() {


		/**
		 * Register ajax event
		 */
		self::add_ajax_events( array(
			'backup_connect' => false,
			'backup_check_cron' => false,
			'backup_track' => false,
			'restore_connect' => false,
			'restore_check_cron' => false,
			'restore_track' => false,
			'register_token' => false,
			'validate_token' => false
		) );
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 * 
	 * @param array $ajax_events
	 * @return void
	 */
	public static function add_ajax_events( $ajax_events ) {

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_tpba_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_tpba_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Connect to server before backup processed
	 * @since 1.0.0
	 */
	public static function backup_connect() {


		$service = new TPBA_Services();
		$res = $service->connect_to_service();

		if ( $res['success'] ) {
			TPBA_Cron::register_backup();
		}

		wp_send_json( $res );
	}

	public static function backup_check_cron() {

		$result = get_option( 'tpba_cron_result' );
		$is_cron = get_transient( 'doing_cron' );

		if ( !empty( $result ) && is_array( $result ) && !$is_cron ) {
			$results = get_option( 'tpba_cron_result' );
			$result['continue'] = false;
			delete_option( 'tpba_cron_result' );
			wp_send_json( $results );
		} else if ( $is_cron ) {
			$message = get_option( 'tpba_cron_message' );
			$data = get_option( 'tpba_backup_current' );

			if ( !empty( $message ) && is_array( $message ) ) {
				wp_send_json( array(
					'index' => $message['index'],
					'data' => $message['data'],
					'total_count' => $data['total_count'],
					'continue' => true
				) );
			}
		}

		die();
	}

	/**
	 * Connect to server before backup processed
	 * @since 1.0.0
	 */
	public static function backup_track() {

		sleep( 1 );


		$result = get_option( 'tpba_cron_result' );
		
		if ( !empty( $result ) && is_array( $result ) ) {
			$result['continue'] = false;
			delete_option( 'tpba_cron_result' );
			delete_option( 'tpba_cron_message' );
			wp_send_json( $result );
		} else if ( get_transient( 'doing_cron' ) ) {
			$message = get_option( 'tpba_cron_message' );
			if ( !empty( $message ) && is_array( $message ) ) {
				wp_send_json( array(
					'index' => $message['index'],
					'data' => $message['data'],
					'continue' => true
				) );
			}
		}
		
		wp_send_json( array(
			'success' => false,
			'data' => esc_html__( 'Error when backup', 'tp-backup-automator' ),
			'continue' => false
		) );
	}

	public static function restore_connect() {

		$session_id = sanitize_text_field( $_POST['session_id'] );

		/**
		 * Remove temp file TPBA 
		 */
		$tmp_dir = untrailingslashit( ABSPATH . 'tpba/' );
		$scanner = new TPBA_Scanner();
		$scanner->delete_directory( $tmp_dir );

		/**
		 * Connect to server
		 */
		$service = new TPBA_Services( $session_id );
		$response = $service->restore_connect();

		if ( $response['success'] ) {
			TPBA_Cron::register_restore();
			wp_send_json( array( 'success' => true, 'total_count' => $response['file_count'], 'data' => $response['data'] ) );
		}

		wp_send_json( array( 'success' => false, 'total_count' => 0, 'data' => $response['data'] ) );
	}

	public static function restore_check_cron() {

		$result = get_option( 'tpba_cron_result' );

		if ( !empty( $result ) && is_array( $result ) ) {
			
			$result['continue'] = false;
			delete_option( 'tpba_cron_result' );
			wp_send_json( $result );
			
		} else if ( get_transient( 'doing_cron' ) ) {
			
			$message = get_option( 'tpba_cron_message' );
			$data = get_option( 'tpba_restore_current' );

			if ( !empty( $message ) && is_array( $message ) ) {
				wp_send_json( array(
					'index' => $message['index'],
					'data' => $message['data'],
					'total_count' => $data['total_count'],
					'continue'=>true
				) );
			}
		}

		die();
	}

	/**
	 * 
	 * @since 1.0.0
	 */
	public static function restore_track() {
		
		sleep( 1 );
		
		$result = get_option( 'tpba_cron_result' );
		
		if ( !empty( $result ) && is_array( $result ) ) {
			$result['continue'] = false;
			delete_option( 'tpba_cron_result' );
			wp_send_json( $result );
			
			
		} else if ( get_transient( 'doing_cron' ) ) {
			
			$message = get_option( 'tpba_cron_message' );
			if ( !empty( $message ) && is_array( $message ) ) {
				wp_send_json( array(
					'index' => $message['index'],
					'data' => $message['data'],
					'continue' => true
				) );
			}
		}



		wp_send_json( array(
			'success' => false,
			'data' => esc_html__( 'Error when restore a file', 'tp-backup-automator' ),
			'continue' => false
		) );
		
		
	}

	/**
	 * Register token
	 * @since 1.0.0
	 */
	public static function register_token() {

		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		$errors = array();

		if ( !wp_verify_nonce( $nonce, 'tpba_register_token' ) ) {
			$errors[] = esc_html__( 'Sorry, your security key did not verify.', 'tp-backup-automator' );
		}

		if ( !is_email( $email ) ) {
			$errors[] = esc_html__( 'Please, enter a valid email.', 'tp-backup-automator' );
		}

		if ( !empty( $errors ) ) {
			wp_send_json( array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		$user = new TPBA_User( $email );
		$response = $user->register();

		if ( !$response['success'] ) {
			wp_send_json( array(
				'status' => 202,
				'data' => array(),
				'errors' => array( $response['data'] )
			) );
		}

		$user->set_validate_key();

		wp_send_json( array(
			'status' => 200,
			'data' => array(
				'email' => $email,
				'msg' => $response['data']
			)
		) );
	}

	/**
	 * Validate token
	 */
	public static function validate_token() {

		$errors = array();

		if ( empty( $_POST['nonce'] ) || !wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'tpba_validate_token' ) ) {
			$errors[] = esc_html__( 'Sorry, your security key did not verify.', 'tp-backup-automator' );
		}

		if ( empty( $_POST['token'] ) ) {
			$errors[] = esc_html__( 'Your product key can not be empty.', 'tp-backup-automator' );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		if ( !is_email( $email ) ) {
			$errors[] = esc_html__( 'Email is not validate.', 'tp-backup-automator' );
		}

		if ( !empty( $errors ) ) {
			wp_send_json( array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		$token = sanitize_text_field( strtolower( $_POST['token'] ) );

		$user = new TPBA_User( $email, $token );
		$response = $user->validate();

		if ( !$response['success'] ) {
			update_option( 'themespond_key', '' );
			$master = new TPBA_Master();
			$master->truncate();
			$log = new TPBA_Log();
			$log->truncate();
			wp_send_json( array(
				'status' => 202,
				'data' => array(),
				'errors' => array( $response['data'] )
			) );
		}

		$user->set_validate_key();

		$service = new TPBA_Services();
		$service->remove_cache_activities();

		wp_send_json( array(
			'status' => 200,
			'data' => array(
				'email' => $email,
				'token' => $token,
				'msg' => $response['data']
			)
		) );
	}

	public static function abort() {
		
	}

}

TPBA_Ajax::init();
