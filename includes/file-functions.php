<?php

/**
 * Replace old domain to new domain of SQL file
 * 
 * @param string $content Content File
 * @since 1.0.0
 * 
 */
function tpba_update_new_domain( $content ) {
	global $wpdb;
	$old_domain = get_option( 'tpba_restore_domain' );
	$current_domain = get_site_url();
	$current_domain = format_address( $current_domain );

	return str_replace( $old_domain, $current_domain, $content );
}

/**
 * Compare repository with current files
 * 
 * @param array $session_files File from session
 * @param string $local_files Folder contain files
 * 
 * @return array Group files with status
 * @since 1.0.0
 */
function tpba_compare_files( $session_files, $local_files ) {

	$checked_files = array();

	$changed_files = array_diff_assoc( $local_files, $session_files );

	$new_files = array_diff_key( $local_files, $session_files );
	$deleted_files = array_diff_key( $session_files, $local_files );

	$checked_files['deleted'] = $deleted_files;
	$checked_files['added'] = $new_files;
	$checked_files['changed'] = array_diff_key( $changed_files, $new_files );
	return $checked_files;
}

/**
 * Get all changed files from compare
 * @since 1.0.0
 * 
 * @param array $session_files File from session
 * @param string $local_files Folder contain files
 * @return array wp-content and sql file changed
 */
function tpba_get_changed_files( $session_files, $local_files ) {
	$sql = tpba_compare_files( $session_files['sql'], $local_files['sql'] );
	$root = tpba_compare_files( $session_files['root'], $local_files['root'] );
	$content = tpba_compare_files( $session_files['wp-content'], $local_files['wp-content'] );
	$checked_files = array( 'sql' => $sql, 'root' => $root, 'wp-content' => $content );

	return $checked_files;
}

/**
 * Count file changed
 * @since 1.0.0
 * 
 * @param array File after compared to local files
 * @return array
 */
function tpba_count_changed_files( $compare_files ) {

	$count_content = count( $compare_files['wp-content']['deleted'] );
	$count_content += count( $compare_files['wp-content']['added'] );
	$count_content += count( $compare_files['wp-content']['changed'] );

	$count_sql = count( $compare_files['sql']['deleted'] );
	$count_sql += count( $compare_files['sql']['added'] );
	$count_sql += count( $compare_files['sql']['changed'] );

	$count_root = count( $compare_files['root']['deleted'] );
	$count_root += count( $compare_files['root']['added'] );
	$count_root += count( $compare_files['root']['changed'] );

	return array(
		'wp-content' => $count_content,
		'root' => $count_root,
		'sql' => $count_sql,
		'all' => ($count_content + $count_root + $count_sql)
	);
}

/**
 * Scan all local file in wp-content with wp-config.php
 * @since 1.0.0
 * @return array Short path file with checksums
 */
function tpba_local_files() {

	$files = array();

	$files['sql'] = array();

	$sql = new TPBA_Sql();
	$tables = $sql->get_all_table();
	$scanner = new TPBA_Scanner();

	$ignore_tables = tpba_get_ignore_tables();

	foreach ( $tables as $table ) {

		if ( !in_array( $table, $ignore_tables ) ) {

			$sql_dump = new TPBA_Sql_dump();
			$sql_dump->table = $table;

			$contents = $sql_dump->toSQL( 0, 100000 );

			$files['sql'][$sql_dump->toName()] = md5( $contents );
		}
	}

	$files['root'] = array();

	if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
		$files['root']['wp-config.php'] = $scanner->checksum_file( ABSPATH . 'wp-config.php' );
	}

	if ( file_exists( ABSPATH . '.htaccess' ) ) {
		$files['root']['.htaccess'] = $scanner->checksum_file( ABSPATH . '.htaccess' );
	}

	/**
	 * Get wp-content files
	 */
	$files['wp-content'] = $scanner->checksum_files( WP_CONTENT_DIR, true );

	return $files;
}

/**
 * Save sql to a folder before backup
 * @param array $sql
 * @return void
 */
function tpba_dump_sql_changed( $sql ) {
	$scanner = new TPBA_Scanner();
	$sqls = $sql['changed'] + $sql['added'];

	foreach ( $sqls as $table => $checksums ) {

		$sql_dump = new TPBA_Sql_dump();

		$table_name = str_replace( '.sql', '', $table );

		$sql_dump->__set( 'table', $sql_dump->__get( 'prefix' ) . '' . $table_name );

		$scanner->write_file( $sql_dump->toSQL( 0, 100000 ), ABSPATH . 'tpba-sql/' . $sql_dump->toName() );
	}
}

/**
 * Get ignore tables
 * @since 1.0.1
 * @return array
 */
function tpba_get_ignore_tables() {
	global $wpdb;

	$ignore_tables = array(
		$wpdb->prefix . 'tpba_master',
		$wpdb->prefix . 'tpba_logs'
	);
	return $ignore_tables;
}

/**
 * Sanitize file path from server
 * @since 1.0.1
 * @param array file
 * @return string Short file
 */
function tpba_sanitize_file_from_server( $file ) {
	return substr( $file['file'], strlen( $file['dir'] . '/' ) );
}
