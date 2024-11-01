<?php

class TPBA_Install {

	/**
	 * Create table backup data
	 * @since 1.0.0
	 */
	public static function install() {

		global $wpdb;
		
		$collate = '';
		
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		if ( !$wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}tpba_master'" ) ) {
			
			$table = "CREATE TABLE {$wpdb->prefix}tpba_master (
			  ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			  file varchar(500) NULL,
			  checksums varchar(50) NULL,
			  dir varchar(500) NULL,
			  PRIMARY KEY  (ID),
			  UNIQUE KEY ID (ID)
			) $collate;";

			$wpdb->query( $table );
		}

		if ( !$wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}tpba_logs'" ) ) {
			
			$table = "CREATE TABLE {$wpdb->prefix}tpba_logs (
			  ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			  file varchar (500) NULL, 
			  checksums char (50) NULL, 
			  session_id char(50) NULL, 
			  dir varchar (500) NULL, 
			  file_status char (50) NULL,
			  status tinyint (1) NULL, 
			  message text,
			  PRIMARY KEY  (ID), 
			  UNIQUE KEY ID (ID)
			) $collate;";

			$wpdb->query( $table );
		}
	}
	
	/**
	 * Un Install plugin
	 * @since 1.0.1
	 */
	public static function uninstall(){
			
		delete_transient('doing_cron');
		delete_option('tpba_restore_current');
		delete_option('tpba_backup_current');
		
	}
}
