<?php

/**
 * Backup files
 */
class TPBA_Services extends TPBA_User {

	/**
	 * @var string Session
	 */
	private $session;

	/**
	 * @var string Restore security session
	 */
	private $restore_security;

	public function __construct( $session_id = '', $restore_security = '' ) {

		parent::__construct();

		$this->session_id = $session_id;
		$this->restore_security = $restore_security;
	}

	/**
	 * Get magic method
	 * @param string $name Name of var
	 * @return var
	 */
	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * Create a connect backup
	 * @since 1.0.0
	 * @return  Description
	 */
	public function connect_to_service() {

		$local_files = tpba_local_files();

		$master = new TPBA_Master();
		$changed_files = tpba_get_changed_files( $master->get_master(), $local_files );
		$count_changed_files = tpba_count_changed_files( $changed_files );

		/**
		 * Create connect
		 */
		$data = array(
			'timeout' => 300,
			'headers' => array(
				'authentication' => $this->token
			),
			'body' => array(
				'total_count' => $count_changed_files['all'],
				'file_count' => $count_changed_files['wp-content'] + $count_changed_files['root'],
				'sql_count' => $count_changed_files['sql'],
				'file_status' => 1,
			)
		);

		/**
		 * Send to service
		 */
		$response = wp_remote_post( $this->service_api . 'connect', $data );
		$status = wp_remote_retrieve_response_code( $response );

		$results = array( 'success' => false, 'data' => wp_remote_retrieve_body( $response ), 'total_count' => $count_changed_files['all'] );

		/**
		 * Check operation timed out
		 */
		if ( isset( $response->errors['http_request_failed'][0] ) ) {
			$res['data'] = esc_html__( 'Response to service is too long, pls try again.', 'tp-backup-automator' );
			return $res;
		}

		if ( $status == 404 || $status == 500 || !$status ) {
			$results['data'] = esc_html__( 'Cannot connect to service.', 'tp-backup-automator' );
			return $results;
		}

		if ( is_wp_error( $response ) ) {
			$results['data'] = esc_html__( 'Error from service.', 'tp-backup-automator' );
			return $results;
		}

		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response );

		if ( $response->success && !empty( $response->data->session ) ) {

			$session_id = $response->data->session;

			/**
			 * Save log
			 */
			$log = new TPBA_Log( $session_id );

			$log->truncate();

			$log->add( $changed_files );

			if ( $log->__get( 'id' ) ) {

				/**
				 * Save sql has changed content to local
				 */
				tpba_dump_sql_changed( $changed_files['sql'] );

				update_option( 'tpba_backup_current', array( 'session_id' => $session_id, 'total_count' => $count_changed_files['all'] ) );
				$this->remove_cache_activities();
				$results['success'] = true;
				$results['data'] = esc_html__( 'Connected.', 'tp-backup-automator' );

				return $results;
			}

			$results['data'] = esc_html__( 'Nothing changed now to backup', 'tp-backup-automator' );
		} else if ( !empty( $response->data ) ) {
			$results['data'] = $response->data;
		}

		return $results;
	}

	/**
	 * Handle backup a file
	 * 
	 * @param string $dir
	 * @param string $file Short path of a file
	 * @param string $checksums Checksums of the file
	 * @param string $status File status
	 * 
	 * @return bool
	 */
	public function backup_file( $dir = 'wp-content', $file = '', $checksums = '', $status = 'added' ) {

		$realfile = WP_CONTENT_DIR . '/' . $file;

		if ( $dir == 'sql' ) {
			$realfile = ABSPATH . 'tpba-sql/' . $file;
		} else if ( $dir == 'root' ) {
			$realfile = ABSPATH . $file;
		}

		$file_exists = file_exists( $realfile );

		if ( $file_exists || $status == 'deleted' ) {

			$contents = '';

			if ( $file_exists ) {
				$contents = file_get_contents( $realfile );
			}

			$response = wp_remote_post( $this->service_api . 'file', array(
				'timeout' => 1500,
				'headers' => array(
					'remote' => 'FILE',
					'info' => json_encode( array(
						'checksums' => $checksums,
						'file' => $file,
						'status' => $status,
						'session' => $this->session_id,
						'dir' => $dir
					) ),
					'authentication' => $this->token,
				), 'body' => $contents
					) );


			return $this->remote_response( $response, $file );
		}

		return 0;
	}

	/**
	 * @param array $response Http Response
	 * @return array status, data, success
	 */
	public function remote_response( $response, $file = '' ) {

		$res = array(
			'status' => wp_remote_retrieve_response_code( $response ),
			'data' => sprintf( esc_html__( '%s has error from service.', 'tp-backup-automator' ), $file ),
			'success' => false,
			'server' => wp_remote_retrieve_body( $response )
		);

		/**
		 * Check operation timed out
		 */
		if ( isset( $response->errors['http_request_failed'][0] ) ) {
			$res['status'] = -1;
			$res['data'] = esc_html__( '%s is response too long.', 'tp-backup-automator' );
			return $res;
		}

		if ( $res['status'] != 200 ) {
			$res['data'] = esc_html__( 'Cannot create connect to service', 'tp-backup-automator' );
			return $res;
		}

		if ( !is_wp_error( $response ) ) {

			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response );

			if ( isset( $response->success ) ) {
				$res['success'] = $response->success;
			}
			if ( isset( $response->data ) ) {
				$res['data'] = $response->data;
			}
		}

		return $res;
	}

	/**
	 * Connect to service to get Restore Session 
	 * 
	 * @since 1.0.0
	 * @return type
	 */
	public function restore_connect() {

		$response = wp_remote_post( $this->service_api . 'restore-connect', array(
			'timeout' => 300,
			'headers' => array(
				'authentication' => $this->token,
			), 'body' => array( 'session_restore' => $this->session_id ) ) );


		$data = array( 'file_count' => 0, 'success' => false );

		$status = wp_remote_retrieve_response_code( $response );


		/**
		 * Check operation timed out
		 */
		if ( isset( $response->errors['http_request_failed'][0] ) ) {
			$res['data'] = esc_html__( 'Response to service is too long, pls try again.', 'tp-backup-automator' );
			return $res;
		}

		if ( $status == 404 || $status == 500 || !$status ) {
			$data['data'] = esc_html__( 'Cannot connect to service.', 'tp-backup-automator' );
			return $data;
		}

		if ( !is_wp_error( $response ) ) {

			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response );

			if ( !empty( $response->file_count ) ) {
				$data['file_count'] = $response->file_count;
				$data['success'] = true;
				$data['data'] = esc_html__( 'Connected', 'tp-backup-automator' );
			}

			if ( !empty( $response->session ) ) {
				update_option( 'tpba_restore_current', array(
					'total_count' => $response->file_count, 'session_id' => $this->session_id, 'security' => $response->session
				) );
			}
			if ( !empty( $response->domain ) ) {
				$data['session'] = $response->session;
				update_option( 'tpba_restore_domain', $response->domain );
				$this->remove_cache_activities();
				$log = new TPBA_Log();
				$log->truncate();
			}
		}

		return $data;
	}

	/**
	 * Restore file - Get file by session
	 * 
	 * @param int $index
	 * @return Array
	 * @since 1.0.0
	 */
	public function restore_file( $index ) {

		$response = wp_remote_post( $this->service_api . 'restore', array(
			'timeout' => 1500,
			'headers' => array(
				'authentication' => $this->token,
			), 'body' => array(
				'session_restore' => $this->session_id,
				'session' => $this->restore_security,
				'index' => $index,
			) ) );

		$data = array(
			'file' => '',
			'content' => '',
			'dir' => '',
			'server' => '',
			'checksums' => '',
			'success' => false,
			'status' => wp_remote_retrieve_response_code( $response )
		);
		
		/**
		 * Check operation timed out
		 */
		if ( isset( $response->errors['http_request_failed'][0] ) ) {
			$data['status'] = -1;
			$data['data'] = esc_html__( '%s is response too long.', 'tp-backup-automator' );
			return $data;
		}
		
		if ( $data['status'] != 200 ) {
			$data['server'] = esc_html__( 'Cannot create connect to service', 'tp-backup-automator' );
			return $data;
		}

		if ( !is_wp_error( $response ) ) {

			$response = wp_remote_retrieve_body( $response );
			$data['server'] = wp_remote_retrieve_body( $response );

			$response = explode( "|", $response, 4 );

			if ( !empty( $response[0] ) ) {
				$data['file'] = urldecode( $response[0] );
			}

			if ( !empty( $response[3] ) ) {
				if ( ($response[2] == 'sql') || strpos( $response[0], '.htacesss' ) > 0 || (strpos( $response[0], 'wp_config.php' ) > 0) ) {
					$data['content'] = tpba_update_new_domain( $response[3] );
				} else {
					$data['content'] = $response[3];
				}
			}

			if ( !empty( $response[2] ) ) {
				$data['dir'] = $response[2];
			}

			if ( !empty( $response[1] ) ) {
				$data['checksums'] = $response[1];
				$data['success'] = true;
			}
		} else {
			$data['server'] = wp_remote_retrieve_body( $response );
		}

		return $data;
	}

	/**
	 * Rollback when a session was not done
	 * @since 1.0.0
	 */
	public function rollback() {

		$response = wp_remote_post( $this->service_api . 'rollback', array(
			'timeout' => 1500,
			'headers' => array(
				'authentication' => $this->token,
			), 'body' => array(
				'session' => $this->session_id
			)
				) );

		return $this->remote_response( $response );
	}

	/**
	 * Get activity list
	 * @param string $type Activity type
	 * @return array
	 * @since 1.0.0
	 */
	public function get_activities( $type ) {

		$list = get_transient( 'tpba_activities_' . $type );

		if ( !empty( $list ) && is_array( $list ) && !WP_DEBUG ) {
			return $list;
		}

		$response = wp_remote_get( $this->service_api . 'activity/' . $this->token . '?type=' . $type, array( 'timeout' => 300 ) );
		/**
		 * Check operation timed out
		 */
		if ( isset( $response->errors['http_request_failed'][0] ) ) {
			throw new Exception( esc_html__( 'Response to service is too long, pls try again.', 'tp-backup-automator' ), -1 );
		}


		$status = wp_remote_retrieve_response_code( $response );

		if ( $status == 401 ) {
			throw new Exception( esc_html__( 'Email and product key are invalid.', 'tp-backup-automator' ), $status );
		}

		if ( $status != 200 ) {
			throw new Exception( esc_html__( 'Cannot connect to service.', 'tp-backup-automator' ), $status );
		}

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html__( 'Error from service.', 'tp-backup-automator' ), $status );
		}

		$results = wp_remote_retrieve_body( $response );

		$results = json_decode( $results );

		if ( !empty( $results->success ) ) {
			if ( !WP_DEBUG ) {
				set_transient( 'tpba_activities_' . $type, $results->data, 7 * DAY_IN_SECONDS );
			}
			return $results->data;
		}

		return array();
	}

	/**
	 * Flush activities cache
	 * @since 1.0.0
	 * @return void
	 */
	public function remove_cache_activities() {
		delete_transient( 'tpba_activities_restore' );
		delete_transient( 'tpba_activities_backup' );
		delete_transient( 'tpba_activities_all' );
	}

}

function tpba_reset_key() {
	if ( isset( $_GET['reset'] ) && $_GET['reset'] === 'true' && isset( $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'reset' ) ) {
			update_option( 'themespond_salt', '' );
			update_option( 'themespond_key', '' );
			update_option( 'themespond_email', '' );
			wp_safe_redirect( admin_url( 'tools.php?page=tp-backup-automator' ) );
			exit();
		}
	}
}

add_action( 'load-tools_page_tp-backup-automator', 'tpba_reset_key' );
