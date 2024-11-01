<?php

class TPBA_Cron {

	public static function init() {
		add_action( 'tpba_cron_backup', array( __CLASS__, 'do_backup' ) );
		add_action( 'tpba_cron_restore', array( __CLASS__, 'do_restore' ) );

		add_action( 'delete_transient_doing_cron', array( __CLASS__, 'cron_end' ) );
	}

	public static function register_backup() {
		if ( !wp_next_scheduled( 'tpba_cron_backup' ) ) {
			wp_schedule_single_event( time() - 1, 'tpba_cron_backup' );
			self::sendMessage( 0, esc_html__( 'Prepare files...', 'tp-backup-automator' ) );
		}
	}

	public static function register_restore() {
		if ( !wp_next_scheduled( 'tpba_cron_restore' ) ) {
			wp_schedule_single_event( time() - 1, 'tpba_cron_restore' );
			self::sendMessage( 0, esc_html__( 'Prepare files...', 'tp-backup-automator' ) );
		}
	}

	public static function do_backup() {

		/**
		 * Backup file
		 */
		$i = 0;
		$current = get_option( 'tpba_backup_current' );
		$service = new TPBA_Services();
		$service->remove_cache_activities();

		if ( !empty( $current ) && is_array( $current ) ) {

			$total_count = $current['total_count'];

			$session_id = $current['session_id'];

			while ( $i < $total_count ) {

				$res = self::backup_file( $i, $session_id );

				if ( $res ) {
					$i++;
					self::sendMessage( $i, $res['data'] );
				} else if ( $res == 0 ) {

					/**
					 * If is lost connection
					 * Allow reconnect one time
					 */
					$res = self::backup_file( $i, $session_id );

					if ( $res ) {
						$i++;
						self::sendMessage( $i, $res['data'] );
					} else {
						self::sendMessage( $total_count, esc_html__( 'The connection has been lost. Don\'t worry about it. Your data will be rolled back.' ) );
						sleep( 2 );
						return false;
					}
				} else {
					self::sendMessage( $total_count, esc_html__( 'Some files were deleted during the backup running, please check them. Don\'t worry about it. Your data will be rolled back.' ) );
					sleep( 2 );
					return false;
				}
			}

			self::sendMessage( $total_count, esc_html__( 'Almost done, please wait...', 'tp-backup-automator' ) );

			sleep( 2 );
		} else {
			self::sendMessage( 0, esc_html__( 'Data backup is not avaiable.', 'tp-backup-automator' ) );
		}
	}

	public static function backup_file( $index, $session_id ) {

		$log = new TPBA_Log( $session_id );

		$log_data = $log->get_row_by_index( $index );

		if ( !empty( $log_data ) ) {

			$service = new TPBA_Services( $session_id );

			$res = $service->backup_file( $log_data['dir'], $log_data['file'], $log_data['checksums'], $log_data['file_status'] );

			if ( !empty( $res['success'] ) ) {

				$res['data'] = sprintf( esc_html__( '%s was %s', 'tp-backup-automator' ), $log_data['file'], $log_data['file_status'] );
				$log->track( $log_data['ID'], $res['data'], 1 );
				$res['success'] = true;
			} else if ( $res === 0 ) {
				$log->track( $log_data['ID'], sprintf( esc_html__( 'File is not exist', 'tp-backup-automator' ), $log_data['file_status'] ), 0 );
				return -1;
			} else {
				//Lost connection
				return 0;
			}
		} else {
			$res['success'] = false;
			$res['data'] = sprintf( esc_html__( '%s not found.', 'tp-backup-automator' ), $log_data['file'] );
			$log->track( $log_data['ID'], $res, 0 );
		}

		return $res;
	}

	public static function backup_done() {

		/**
		 * Backup done
		 * Remove sql file
		 */
		if ( is_dir( ABSPATH . 'tpba-sql' ) ) {
			$scanner = new TPBA_Scanner();
			$scanner->delete_directory( ABSPATH . 'tpba-sql' );
		}

		/**
		 * Save current locals to master
		 */
		$master = new TPBA_Master();
		$master->backup_changed();
	}

	public static function do_restore() {

		/**
		 * Restore file
		 */
		$i = 0;

		$current = get_option( 'tpba_restore_current' );

		$service = new TPBA_Services();
		$service->remove_cache_activities();

		if ( !empty( $current ) && is_array( $current ) ) {

			$total_count = $current['total_count'];

			while ( $i < $total_count ) {

				$res = self::restore_file( $i, $current['session_id'], $current['security'] );
				if ( $res ) {
					$i++;
					self::sendMessage( $i, $res );
				} else {
					/**
					 * If is lost connection
					 * Allow reconnect one time
					 */
					$res = self::restore_file( $i, $current['session_id'], $current['security'] );

					if ( $res ) {
						$i++;
						self::sendMessage( $i, $res );
					} else {
						self::sendMessage( $total_count, esc_html__( 'Some files cannot downloaded during the restore running. Don\'t worry about it. Your data will be rolled back.' ) );
						sleep( 2 );
						return false;
					}
				}
			}

			self::sendMessage( $total_count, esc_html__( 'Almost done, please wait...', 'tp-backup-automator' ) );
			sleep( 1 );
		} else {
			self::sendMessage( 0, esc_html__( 'Data to backup is not avaiable.', 'tp-backup-automator' ) );
		}
	}

	public static function restore_file( $index, $session_id, $security_id ) {

		$service = new TPBA_Services( $session_id, $security_id );
		$scanner = new TPBA_Scanner();
		$log = new TPBA_Log();

		$file = $service->restore_file( $index );

		$short_file = tpba_sanitize_file_from_server( $file );

		if ( $file['success'] ) {

			$dir = $file['dir'] == 'root' ? '' : $file['dir'];

			$real_file = ABSPATH . 'tpba/' . $dir . '/' . $short_file;

			$res = $scanner->write_file( $file['content'], $real_file );

			$message = sprintf( esc_html__( '%s was not saved. ', 'tp-backup-automator' ), $short_file );

			if ( $res ) {
				$message = sprintf( esc_html__( 'Prepare %s was done. ', 'tp-backup-automator' ), $short_file );

				$log->add_file( $short_file, $file['checksums'], $file['dir'], 'added', 1, $message );
			}
		} else {
			$message = $file['server'];
			$log->add_file( $short_file, $file['checksums'], $file['dir'], 'added', 0, $message );
			return 0;
		}

		return $message;
	}

	public static function restore_done( $total_count, $session_id = '' ) {
		/**
		 * Replace wp-content files
		 */
		$scanner = new TPBA_Scanner();
		$wp_content = str_replace( ABSPATH, '', WP_CONTENT_DIR );
		$downloaded_folder = ABSPATH . 'tpba/';

		$scanner->replace_folder( $downloaded_folder . $wp_content, WP_CONTENT_DIR );

		/**
		 * Replace sql
		 */
		$list_sql = $scanner->scan_files( $downloaded_folder . 'sql' );

		if ( count( $list_sql ) ) {

			$sql_dump = new TPBA_Sql_dump();

			foreach ( $list_sql as $sql ) {
				/**
				 * Output: array('affected_rows' => 1, 'sql_file' => $sql, 'mysql_cli' => false, 'list_error' => $list_error);
				 */
				$sql_dump->import_SQL( $sql, false );
			}
		}

		/**
		 * Remove downloaded folder when all replace
		 */
		$scanner->delete_directory( $downloaded_folder );

		/**
		 * Sync all restored data to master
		 */
		$master = new TPBA_Master();
		return $master->restore_changed();
	}

	/**
	 * @param int $index
	 * @param string $data Message
	 */
	public static function sendMessage( $index, $data ) {
		update_option( 'tpba_cron_message', array( 'index' => $index, 'data' => $data ) );
	}

	/**
	 * Cron end
	 * @since 1.0.1
	 * @return void
	 */
	public static function cron_end() {

		$backup = get_option( 'tpba_backup_current' );
		$restore = get_option( 'tpba_restore_current' );

		$res = false;
		$data = '';
		$log = new TPBA_Log();

		if ( !empty( $backup['total_count'] ) && !empty( $backup['session_id'] ) ) {

			$total_count = sanitize_text_field( $backup['total_count'] );
			$session_id = sanitize_text_field( $backup['session_id'] );
			$service = new TPBA_Services( $session_id );

			if ( $log->is_done() ) {

				self::backup_done( $total_count, $session_id );
				$res = true;
				$data = esc_html__( 'You have created a backup successfully.', 'tp-backup-automator' );
			} else {
				$service->rollback();
				$data = esc_html__( 'Opps, this backup has not been done. All data will be rolled back.', 'tp-backup-automator' );
			}

			update_option( 'tpba_cron_result', array( 'type' => 'backup', 'success' => $res, 'data' => $data ) );
			$service->remove_cache_activities();
			delete_option( 'tpba_backup_current' );
		} else if ( !empty( $restore['total_count'] ) && !empty( $restore['session_id'] ) ) {//Resore done
			$total_count = sanitize_text_field( $restore['total_count'] );
			$session_id = sanitize_text_field( $restore['session_id'] );
			$service = new TPBA_Services( $session_id );
			$res = false;

			if ( $log->is_done() ) {
				$res = self::restore_done( $total_count, $session_id );
			}

			if ( $res ) {
				$data = esc_html__( 'Done! Your data has been restored successfully.', 'tp-backup-automator' );
			} else {
				$service->rollback();
				$data = esc_html__( 'Opps, the restoration process cannot be completed now. Dont worry! Your data won\'t be affected.', 'tp-backup-automator' );
			}

			delete_option( 'tpba_restore_current' );
			$service->remove_cache_activities();
			update_option( 'tpba_cron_result', array( 'type' => 'restore', 'success' => $res, 'data' => $data ) );
		}


		delete_option( 'tpba_cron_message' );
	}

}

TPBA_Cron::init();
