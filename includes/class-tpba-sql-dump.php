<?php

/**
 * Dump table SQL
 * 
 * 
 */
class TPBA_Sql_dump {

	private $rows;
	private $columns;
	private $rowcount;
	private $error;
	public $query;
	// Original database name
	private $db_name;
	// Name of table export
	private $table;
	// Use for ajax backup
	private $limit;
	// User for ajax backup
	private $start;
	// List column name of table
	private $list_columns_name;
	private $prefix;

	public function __construct( $table_name = '' ) {
		global $wpdb;
		$this->db_name = $wpdb->dbname;
		$this->table = $table_name;
		$this->prefix = $wpdb->prefix;
	}

	public function __set( $name, $value ) {
		$this->$name = $value;
	}

	public function __get( $name ) {
		return $this->$name;
	}

	public function toName() {
		return str_replace( $this->prefix, '', $this->table ) . '.sql';
	}

	/**
	 * Outputs the given data as SQL INSERT statements
	 * @since 1.0
	 */
	public function toSQL( $start = 0, $limit = 100 ) {

		$this->start = $start;
		$this->limit = $limit;

		$table = $this->table;
		$output = "";
		// CONTENT BACKUP SQL FOR TABLE
		// Delete if exist

		$output = "DROP TABLE IF EXISTS `$table`;\r\n-- --- \r\n";

		// Create table
		if ( $start == 0 ) {
			$output .= $this->get_sql_create_table();
		}

		// Content import
		$this->get_all_columns_for_table();
		$columns = $this->columns;
		$rows = $this->rows;



		foreach ( $rows as $row ) {
			$cols = "INSERT INTO `$table` (";
			foreach ( $columns as $column ) {
				// for custom queries we don't know the column types
				if ( property_exists( $column, "Type" ) ) {
					if ( $column->Key != 'PRI' ) {
						$cols .= $column->Field . ",";
					}
				} else {
					$cols .= $column->Field . ",";
				}
			}
			$cols = trim( $cols, "," );
			$cols .= ") VALUES (";
			$data = '';
			foreach ( $columns as $column ) {
				// for custom queries we don't know the column types
				if ( property_exists( $column, "Type" ) ) {
					if ( $column->Key != 'PRI' ) {
						$data .= $this->writeColumnInsertSQL( $column, $row[$column->Field] );
					}
				} else {
					$data .= $this->writeColumnInsertSQL( $column, $row[$column->Field] );
				}
			}
			$data = trim( $data, "," );
			$data .= ");\r\n-- --- \r\n";
			$cols .= $data;
			$output .= $cols;
		}
		return $output;
	}

	/**
	 * Send file SQL to service
	 * 
	 * @param type $start
	 * @param type $limit
	 * 
	 */
	public function sendFileSQL( $start = 0, $limit = 100 ) {
		$content = $this->toSQL( $start, $limit );

		$temp = tmpfile();
		fwrite( $temp, $content );
		$metaData = stream_get_meta_data( $temp );
		$temp_path = $metaData['uri'];

		$file_service = new TPBA_Services( 'sql' );
		$file_service->backup_file( $temp_path );
		fclose( $temp );
	}

	/**
	 * Create backup file SQL
	 * Create database table
	 * 
	 * @return String
	 * @since 1.0.0
	 */
	private function get_sql_create_table() {
		global $wpdb;

		// Get Create table code
		$create_table = $wpdb->get_results( "SHOW CREATE TABLE `$this->table`", ARRAY_N );
		return $create_table[0][1] . ";\r\n -- --- \r\n";
	}

	/**
	 * Get query for table
	 * 
	 * @since 1.0.0
	 */
	private function get_all_columns_for_table() {
		global $wpdb;

		// Get all column name of table	
		$list_columns = $wpdb->get_results( $wpdb->prepare( "SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`='%s'  AND `TABLE_NAME`='%s'", $this->db_name, $this->table ), ARRAY_A );
		// Get all column name of table	
		$list_columns_name = "";
		$i = 0;
		foreach ( $list_columns as $key => $columns ) {
			$max = count( $list_columns );
			foreach ( $columns as $key2 => $column ) {
				$i++;
				if ( $i < $max ) {
					$list_columns_name .= "`" . $column . "`,";
				} else {
					$list_columns_name .= "`" . $column . "`";
				}
			}
		}

		$this->list_columns_name = $list_columns_name;

		// Run this query
		$this->runQuery();
	}

	/**
	 * Outputs the value $value of column $column for use in a SQL insert statement
	 * 
	 *   @since 1.0.0
	 */
	private function writeColumnInsertSQL( $column, $value ) {

		// for custom queries we don't know the column types
		if ( !property_exists( $column, "Type" ) ) {
			return "'" . str_replace( "'", "''", $value ) . "',";
		}

		// text columns
		if ( $this->startsWith( $column->Type, "longtext" ) || $this->startsWith( $column->Type, "text" ) || $this->startsWith( $column->Type, "mediumtext" ) || $this->startsWith( $column->Type, "longtext" ) || $this->startsWith( $column->Type, "char" ) || $this->startsWith( $column->Type, "varchar" ) ) {
			return "'" . str_replace( "'", "''", $value ) . "',";
		}
		// date columns
		if ( $this->startsWith( $column->Type, "datetime" ) ) {
			return "'" . $value . "',";
		}
		// iinteger columns
		if ( $this->startsWith( $column->Type, "bigint" ) || $this->startsWith( $column->Type, "int" ) || $this->startsWith( $column->Type, "tinyint" ) || $this->startsWith( $column->Type, "smallint" ) || $this->startsWith( $column->Type, "mediumint" ) ) {
			return $value . ",";
		}
	}

	/**
	 * Runs a SQL query and sets the result in the properties of this class
	 * 
	 * @since 1.0.0
	 */
	function runQuery() {
		global $wpdb;

		// Get query
		$this->query = $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS $this->list_columns_name FROM $this->table LIMIT %d, %d", $this->start, $this->limit );
		$query = stripslashes( $this->query );

		$_SESSION["query"] = $query;

		$this->rows = $wpdb->get_results( $query, ARRAY_A );
		$this->error = $wpdb->last_error;
		$this->rowcount = count( $this->rows );

		if ( count( $this->rows ) > 0 ) {
			foreach ( $this->rows[0] as $key => $value ) {
				$obj = new stdClass();
				$obj->Field = $key;
				$this->columns[] = $obj;
			}
		}
	}

	/**
	 * Export this table to import manual 
	 * 
	 * @since 1.0.0
	 */
	function forceDownload( $format ) {

		header( "Cache-Control: public" );
		header( "Content-Description: WordPress Database Browser Table Export" );
		header( "Content-Disposition: attachment; filename=$this->db_name.$this->table.$format" );
		header( "Content-Type: application/octet-stream" );
	}

	/**
	 * Import SQL by CLI or PHP
	 * 
	 * @global type $wpdb
	 * @param String $sql_path
	 * @param boolean $delete Delete file
	 * @return array SQL restore
	 */
	public function import_SQL( $sql_path, $delete = false ) {
		global $wpdb;
		$check = true;

		//CLI enable
		if ( function_exists( 'exec' ) && ( $mysql = exec( 'which mysql' ) ) ) {
			$details = explode( ':', DB_HOST, 2 );
			$params = array( defined( 'DB_CHARSET' ) && DB_CHARSET ? DB_CHARSET : 'utf8', DB_USER, DB_PASSWORD, $details[0], isset( $details[1] ) ? $details[1] : 3306, DB_NAME, $sql_path );
			exec( sprintf( '%s %s', escapeshellcmd( $mysql ), vsprintf( '-A --default-character-set=%s -u%s -p%s -h%s -P%s %s < %s', array_map( 'escapeshellarg', $params ) ) ), $output, $r );


			if ( $delete ) {
				@unlink( $sql_path );
			}
			return array( 'affected_rows' => 1, 'sql_file' => $sql_path, 'mysql_cli' => true );
		}

		// CLI disable
		$list_error = array();
		$sql_source = file_get_contents( $sql_path );
		$sql_queries = explode( "-- ---", $sql_source );
		
		foreach ( $sql_queries as $sql_query ) {
			if ( !ctype_space( $sql_query ) ) {
				$sql_query = $sql_query . "";
				//p($sql_query);
				$check = $wpdb->query( $sql_query );
				$error = $wpdb->last_error;
				if ( $error !== '' ) {
					array_push( $list_error, $error );
				}
			}
		}

		return array( 'affected_rows' => 1, 'sql_file' => $sql_path, 'mysql_cli' => false, 'list_error' => $list_error );
	}

}
