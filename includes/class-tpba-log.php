<?php

class TPBA_Log {

	/**
	 * @var string WP Db table name
	 */
	private $table;

	/**
	 * @var int ID
	 */
	private $id;

	/**
	 * @var string File
	 */
	private $file;

	/**
	 * @var string Checksums
	 */
	private $checksums;

	/**
	 * @var string Session ID
	 */
	private $session_id;

	/**
	 * @var string Dir name
	 */
	private $dir;

	/**
	 * @var string File status
	 */
	private $file_status;

	/**
	 * @var int Status
	 */
	private $status;

	/**
	 * Contruct method
	 */
	public function __construct( $session_id = '' ) {

		$this->table = 'tpba_logs';

		$this->session_id = $session_id;
	}

	public function __get( $name ) {
		return $this->$name;
	}

	public function truncate() {
		global $wpdb;
		$table = $wpdb->prefix . $this->table;
		$wpdb->query( "TRUNCATE TABLE $table" );
	}

	/**
	 * Add a session to db
	 * @return int Inserted ID
	 */
	public function add_file( $file, $checksums, $dir, $file_status, $status = 0, $message = '' ) {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . $this->table, array(
			'file' => sanitize_text_field( $file ),
			'checksums' => sanitize_text_field( $checksums ),
			'session_id' => sanitize_text_field( $this->session_id ),
			'dir' => sanitize_text_field( $dir ),
			'file_status' => sanitize_text_field( $file_status ),
			'status' => sanitize_text_field( $status ),
			'message' => sanitize_text_field( $message )
				), array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d'
		) );

		$this->id = $wpdb->insert_id;

		return $this->id;
	}

	private function add_log_by_status( $logs, $dir, $status ) {

		if ( !empty( $logs[$status] ) ) {
			foreach ( $logs[$status] as $file => $checksums ) {
				$this->add_file( $file, $checksums, $dir, $status );
			}
		}
	}

	public function add( $changed_files ) {

		$sql = $changed_files['sql'];

		$this->add_log_by_status( $sql, 'sql', 'changed' );
		$this->add_log_by_status( $sql, 'sql', 'added' );
		$this->add_log_by_status( $sql, 'sql', 'deleted' );


		if ( isset( $changed_files['root'] ) ) {
			$root = $changed_files['root'];
			$this->add_log_by_status( $root, 'root', 'changed' );
			$this->add_log_by_status( $root, 'root', 'added' );
			$this->add_log_by_status( $root, 'root', 'deleted' );
		}

		$content = $changed_files['wp-content'];
		$this->add_log_by_status( $content, 'wp-content', 'changed' );
		$this->add_log_by_status( $content, 'wp-content', 'added' );
		$this->add_log_by_status( $content, 'wp-content', 'deleted' );
	}
	

	/**
	 * @return array ARRAY_A
	 */
	public function get_row( $log_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$wpdb->prefix$this->table` WHERE `ID` = %d", $log_id ), ARRAY_A );
	}

	/**
	 * @param string $session_id
	 * @param int $log_index 
	 * @return array ARRAY_A
	 */
	public function get_row_by_index( $log_index ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$wpdb->prefix$this->table` WHERE `session_id` = %s LIMIT %d, 1", $this->session_id, $log_index ), ARRAY_A );
	}
	
	public function get_all() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM `$wpdb->prefix$this->table`" );
	}

	public function track( $id, $message, $status = 1 ) {
		if ( $id ) {
			$this->id = $id;
		}
		global $wpdb;
		return $wpdb->update( $wpdb->prefix . $this->table, array( 'status' => $status, 'message' => $message ), array( 'ID' => $this->id ), array( '%d', '%s' ), array( '%d' ) );
	}

	public function is_done() {
		global $wpdb;
		$done =  $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->prefix$this->table WHERE `status` != %d", 1 ) );
		if($done){
			return false;
		}
		
		return true;
	}

}
