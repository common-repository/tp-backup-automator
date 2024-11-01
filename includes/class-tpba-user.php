<?php

/**
 * User Service
 */
class TPBA_User {

	/**
	 * @var string $token Product Key
	 */
	protected $token;

	/**
	 * @var string $email Email to register a product key
	 */
	protected $email;

	/**
	 * @var string $tp_api Server Api url
	 */
	protected $tp_api;
	protected $service_api;

	public function __construct( $email = '', $token = '' ) {

		$this->tp_api = 'http://api.themespond.com/api/v1/';
		$this->service_api = 'http://api.themespond.com/api/v1/backup/';
		
		if ( !empty( $email ) ) {
			$this->email = $email;
			$this->token = $token;
		} else {
			$this->email = get_option( 'themespond_email' );
			$this->token = get_option( 'themespond_key' );
		}
	}

	public function get_token() {
		return $this->token;
	}

	public function get_email() {
		return $this->email;
	}

	/**
	 * Register a token
	 * @param string $email
	 * @return array
	 */
	public function register() {

		$authen = array(
			'email' => $this->email,
			'service' => 'tp_backup'
		);

		$response = wp_remote_post( $this->tp_api . 'register', array( 'body' => array(
				'action' => 'register',
				'authentication' => base64_encode( json_encode( $authen ) )
			) ) );


		$status = wp_remote_retrieve_response_code( $response );

		$results = array(
			'success' => false,
			'data' => ''
		);

		if ( $status == 404 || $status == 500 || !$status ) {
			$results['data'] = esc_html__( 'Cannot connect to service.', 'tp-backup-automator' );
		}

		if ( is_wp_error( $response ) ) {
			$results['data'] = esc_html__( 'Error from service.', 'tp-backup-automator' );
		} else {
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			$results['success'] = true;
			$results['data'] = isset( $results['data'] ) ? $results['data'] : '';
		}

		return $results;
	}

	/**
	 * Validate a token
	 * @param string $email
	 * @param string $token
	 * @return array
	 */
	public function validate() {

		$authen = array(
			'email' => $this->email,
			'token' => $this->token,
		);

		$response = wp_remote_post( $this->tp_api . 'validate', array( 'body' => array(
				'authentication' => base64_encode( json_encode( $authen ) )
			) ) );

		$status = wp_remote_retrieve_response_code( $response );

		$results = array(
			'success' => false,
			'data' => ''
		);

		if ( $status == 404 || $status == 500 || !$status ) {
			$results['data'] = esc_html__( 'Cannot connect to service.', 'tp-backup-automator' );
		}

		if ( is_wp_error( $response ) ) {
			$results['data'] = esc_html__( 'Error from service.', 'tp-backup-automator' );
		} else {
			$response = wp_remote_retrieve_body( $response );
			$results = json_decode( $response, true );
		}

		return $results;
	}

	/**
	 * Check if this user registered email and ready to use
	 * @return bool
	 */
	public function is_validate() {

		if ( !empty( $this->email ) && !empty( $this->token ) ) {

			$salt = md5( $this->email . $this->token );

			if ( $salt == get_option( 'themespond_salt' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save validate to a key
	 * @return void
	 */
	public function set_validate_key() {
		$salt = md5( $this->email . $this->token );
		update_option( 'themespond_salt', $salt );
		update_option( 'themespond_key', $this->token );
		update_option( 'themespond_email', $this->email );
	}

}
