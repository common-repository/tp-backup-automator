<?php

/**
 * Master modal
 */
class TPBA_Master {

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
	 * @var string Dir name
	 */
	private $dir;

	/**
	 * @param string|int|array $master
	 */
	public function __construct( $master = 0 ) {

		$this->table = 'tpba_master';

		if ( !empty( $master ) && !is_array( $master ) ) {
			$master = $this->get_row( $master );
		}

		if ( is_array( $master ) ) {

			$this->setup_data( $master );
		}
	}

	public function setup_data( $master ) {
		$this->id = isset( $master['ID'] ) ? $master['ID'] : 0;
		$this->file = $master['file'];
		$this->checksums = $master['checksums'];
		$this->dir = $master['dir'];
	}

	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * @param int $id
	 * @return Object
	 */
	public function get_row( $id = 0 ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$wpdb->prefix$this->table` WHERE `ID` = %d", $id ), ARRAY_A );
	}

	/**
	 * Add a session to db
	 * @return int Inserted ID
	 */
	public function add() {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . $this->table, array(
			'file' => $this->file,
			'checksums' => $this->checksums,
			'dir' => $this->dir,
				), array(
			'%s',
			'%s',
			'%s',
			'%s',
		) );

		$this->id = $wpdb->insert_id;

		return $this->id;
	}

	/**
	 * Get rows by dir
	 * @param string  $dir
	 * @return array or 0
	 */
	public function get_rows_by_dir( $dir = 'wp-content' ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$wpdb->prefix$this->table` WHERE `dir` = %s", $dir ) );
		$list = array();
		foreach ( $results as $item ) {
			$list[$item->file] = $item->checksums;
		}

		return $list;
	}

	/**
	 * Get all rows
	 * @return array
	 */
	public function get_master() {
		return array(
			'wp-content' => $this->get_rows_by_dir( 'wp-content' ),
			'root' => $this->get_rows_by_dir( 'root' ),
			'sql' => $this->get_rows_by_dir( 'sql' )
		);
	}
	
	/**
	 * Get master row by index
	 * @since 1.0.0
	 */
	public function get_by_index( $index ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$wpdb->prefix$this->table` LIMIT %d, 1", $index ) );
	}

	/**
	 * @param string $file
	 * @param string $dir
	 * @return int
	 */
	public function is_exist( $file, $dir ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `$wpdb->prefix$this->table` WHERE `dir` = '%s' AND `file` = '%s'", $dir, $file ) );
	}
	
	/**
	 * File count
	 * @since 1.0.0
	 * @return number Count all row in master
	 */
	public function files_count() {
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(`ID`) FROM $wpdb->prefix$this->table" );
	}

	/**
	 * Master done
	 * @since 1.0.0
	 * @param Object $log Log row in db
	 */
	public function change( $log ) {
		global $wpdb;

		$res = 0;

		if ( $log->file_status == 'deleted' ) {
			$res = $wpdb->delete( $wpdb->prefix . $this->table, array( 'file' => $log->file, 'dir' => $log->dir ), array( '%s', '%s' ) );
		} else if ( $log->file_status == 'changed' ) {
			$wpdb->update( $wpdb->prefix . $this->table, array( 'checksums' => $log->checksums ), array( 'file' => $log->file, 'dir' => $log->dir ), array( '%s' ), array( '%s', '%s' ) );
			$res = 1;
		} else {

			$id = $this->is_exist( $log->file, $log->dir );
			if ( $id ) {
				$wpdb->update( $wpdb->prefix . $this->table, array( 'checksums' => $log->checksums ), array( 'ID' => $id ), array( '%s' ), array( '%d' ) );
				$res = 1;
			} else {
				$res = $wpdb->insert( $wpdb->prefix . $this->table, array(
					'file' => $log->file,
					'checksums' => $log->checksums,
					'dir' => $log->dir,
						), array(
					'%s',
					'%s',
					'%s',
						) );
			}
		}

		return $res;
	}
	
	/**
	 * Truncate table
	 * @since 1.0.0
	 */
	public function truncate() {
		if ( $this->files_count() ) {
			global $wpdb;
			$table = $wpdb->prefix . $this->table;
			$wpdb->query( "TRUNCATE TABLE $table" );
		}
	}
	
	/**
	 * Insert all log to master when restore done
	 * @since 1.0.0
	 * @return bool
	 */
	public function restore_changed() {

		$logDb = new TPBA_Log();
		$logs = $logDb->get_all();

		if ( $logs ) {

			$this->truncate();

			foreach ( $logs as $file ) {
				$this->setup_data( array( 'file' => $file->file, 'checksums' => $file->checksums, 'dir' => $file->dir ) );
				$this->add();
			}
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Insert all log to master when backup done
	 * @since 1.0.0
	 * @return void
	 */
	public function backup_changed() {

		$logDb = new TPBA_Log();
		if ( $logDb->is_done() ) {
			$logs = $logDb->get_all();

			if ( $logs ) {
				foreach ( $logs as $file ) {
					$this->change( $file );
				}
			}
		}
	}
	
	public function is_empty(){
		
	}

}
