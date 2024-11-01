<?php

class TPBA_Scanner {

	/**
	 * @var string $dir_path Full path of the dir
	 */
	private $dir;

	/**
	 * @var array $key Include elements to checksums
	 */
	private $keys = array( 'ino', 'uid', 'gid', 'size', 'blksize', 'blocks' );

	public function __construct( $dir = 'wp-content' ) {
		$this->config_dir( $dir );
	}

	/**
	 * Update dir
	 * 
	 * @param string $dir New dir
	 * @return string Full path of dir
	 */
	public function config_dir( $dir ) {

		if ( $dir == 'plugins' ) {
			$dir = trailingslashit( WP_PLUGIN_DIR );
		} else if ( $dir == 'themes' ) {
			$dir = trailingslashit( WP_CONTENT_DIR ) . 'themes/';
		} else if ( $dir == 'uploads' ) {
			$uploadir = wp_upload_dir();
			$dir = trailingslashit( $uploadir['basedir'] );
		} else {
			$dir = trailingslashit( WP_CONTENT_DIR );
		}

		$this->dir = $dir;
	}

	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * Execute checksum a file
	 * @param string $file Path of the file
	 * @return string|bool md5_file
	 */
	public function exec_checksum( $file ) {

		if ( !function_exists( 'exec' ) ) {
			return false;
		}

		$out = array();

		$method_bin = 'md5sum';

		$checksum = '';

		exec( sprintf( '%s %s', escapeshellcmd( $method_bin ), escapeshellarg( $file ) ), $out );

		if ( !empty( $out ) ) {
			$checksum = trim( array_shift( explode( ' ', array_pop( $out ) ) ) );
		}

		if ( !empty( $checksum ) ) {
			return $checksum;
		}

		return false;
	}

	/**
	 * @param type $file
	 * @return string md5_file
	 */
	public function checksum_file( $file ) {

		$use_exec = false;

		if ( filesize( $file ) >= 104857600 ) {
			$use_exec = true;
		}

		if ( $use_exec ) {
			$checksum = $this->exec_checksum( $file );
			if ( !empty( $checksum ) ) {
				return $checksum;
			}
		}

		return md5_file( $file );
	}

	public function dir_checksum( $base, &$list, $recursive = true ) {

		if ( $list == null ) {
			$list = array();
		}

		$shortbase = substr( $base, strlen( $this->dir ) );

		if ( !$shortbase ) {
			$shortbase = '/';
		}

		$stat = stat( $base );

		$directories = array();

		$files = (array) $this->scan_dir( $base );

		array_push( $files, $base );

		foreach ( $files as $file ) {
			if ( $file !== $base && @is_dir( $file ) ) {
				$directories[] = $file;
				continue;
			}
			$stat = @stat( $file );
			if ( !$stat ) {
				continue;
			}
			$shortstat = array();

			foreach ( $this->keys as $key ) {
				if ( isset( $stat[$key] ) ) {
					$shortstat[$key] = $stat[$key];
				}
			}
			$list[$shortbase][basename( $file )] = $shortstat;
		}

		$list[$shortbase] = md5( serialize( $list[$shortbase] ) );

		if ( !$recursive ) {
			return $list;
		}

		foreach ( $directories as $dir ) {
			$this->dir_checksum( $dir, $list, $recursive );
		}

		return $list;
	}

	/**
	 * @param string $path Full path
	 */
	public function scan_dir( $path ) {
		$files = array();

		if ( false === is_readable( $path ) ) {
			return array();
		}

		$dh = opendir( $path );

		if ( false === $dh ) {
			return array();
		}

		while ( false !== ( $file = readdir( $dh ) ) ) {
			if ( $file == '.' || $file == '..' )
				continue;
			$files[] = "$path/$file";
		}

		closedir( $dh );
		sort( $files );
		return $files;
	}

	public function plugins() {

		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		$paths = $this->scan_dir( WP_PLUGIN_DIR );

		$plugins = array();

		foreach ( $paths as $path ) {

			$base = basename( $path );

			$baseinfo = $this->plugin_info( $base );

			$list = array();

			if ( isset( $baseinfo['Version'] ) ) {
				if ( !is_file( $path ) ) {

					$checksums = $this->dir_checksum( $path, $list, false );

					$plugins[$base] = array(
						'version' => $baseinfo['Version'],
						'checksums' => $checksums[$base],
						'name' => $baseinfo['Name'],
						'file' => $path
					);
				} else {
					$plugins[$path] = $this->checksum_file( $path );
				}
			} else if ( is_dir( $path ) ) {

				$checksums = $this->dir_checksum( $path, $list, false );
				$plugins[$path] = $checksums[$base];
			} else {
				$plugins[$path] = $this->checksum_file( $path );
			}
		}

		return $plugins;
	}

	/**
	 * Show info
	 * @param string $plugin_dir
	 */
	public function plugin_info( $plugin_dir ) {
		$plugins = get_plugins();

		foreach ( $plugins as $plugin => $info ) {
			$arr = explode( '/', $plugin );
			if ( $arr[0] == $plugin_dir ) {
				return $info;
			}
		}

		return false;
	}

	/**
	 * Get all file of folder - Recursive
	 * 
	 * @param String $path fullpath of dir
	 * @param array $list_files
	 */
	public function scan_files( $path = '', $list_files = array() ) {

		$elements = $this->scan_dir( $path );

		foreach ( $elements as $element ) {

			if ( !is_dir( $element ) ) {
				array_push( $list_files, $element );
			} else {
				//array_push( $list_files, $element );
				$list_files = $list_files + $this->scan_files( $element, $list_files );
			}
		}

		return $list_files;
	}

	public function isvalid_file( $file ) {

		if ( strpos( $file, '.', strlen( $file ) - 1 ) ) {
			return false;
		}

		$ignore_files = array( '.DS_Store', '.git', 'desktop.ini', 'humbs.db', '.dropbox', '.dropbox.attr', 'icon\r', '.log' );

		foreach ( $ignore_files as $ext ) {
			if ( strpos( $file, $ext ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check sum all file in a folder
	 */
	public function checksum_files( $path, $shortbase = false, $list_files = array() ) {

		if ( is_dir( $path ) ) {

			$elements = $this->scan_dir( $path );

			foreach ( $elements as $element ) {

				if ( is_file( $element ) ) {
					if ( $this->isvalid_file( $element ) ) {

						$file = $element;
						if ( $shortbase ) {
							$element = substr( $element, strlen( trailingslashit( $this->dir ) ) );
						}

						$list_files[$element] = $this->checksum_file( $file );
					}
				} else {
					//array_push( $list_files, $element );
					$list_files = $list_files + $this->checksum_files( $element, $shortbase, $list_files );
				}
			}
		}

		return $list_files;
	}

	/**
	 * Write file to backup folder
	 * 
	 * @param String $content File Content
	 * @param stirng $filename Real path file
	 * @return int File length
	 */
	public function write_file( $content, $filename ) {

		$dir = $this->dir_of_file( $filename );

		if ( !is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		$file = fopen($filename, 'w' ) or die( 'Cannot open file: ' . $filename );
		fwrite( $file, $content );
		return fclose( $file );
	}

	/**
	 * Get dir of file
	 * @since 1.0.0
	 * @return string
	 */
	public function dir_of_file( $file ) {
		$basename = basename( $file );
		$file = str_replace( $basename, '', $file );
		return trailingslashit( $file );
	}

	/**
	 * Replace folder 
	 * 
	 * @return boolean Replace
	 */
	public function replace_folder( $folder_source, $folder_dest ) {

		$check_replace = true;

		$check_folder = $this->check_exist_folder( $folder_dest );

		// Remove original folder
		if ( $check_folder ) {
			$this->delete_directory( $folder_dest );
		}
		// Create folder
		@mkdir( $folder_dest, 0777, true );

		// Copy folder source to folder destination
		$check_replace = $this->recurse_copy( $folder_source, $folder_dest );

		// Remove source folder
		$check_replace = $this->delete_directory( $folder_source );

		return $check_replace;
	}

	/**
	 * Replace folder desc by folder source
	 * @param type $folder_source
	 * @param type $folder_dest
	 * 
	 * @return boolean Copy success or fail
	 */
	private function recurse_copy( $folder_source, $folder_dest ) {
		$check = true;
		$dir = opendir( $folder_source );
		@mkdir( $folder_dest );

		while ( false !== ( $file = readdir( $dir )) ) {
			if ( ( $file != '.' ) && ( $file != '..' ) ) {
				if ( is_dir( $folder_source . '/' . $file ) ) {
					echo 'copy folder ' . $file;
					$this->recurse_copy( $folder_source . '/' . $file, $folder_dest . '/' . $file );
				} else {
					$check = copy( $folder_source . '/' . $file, $folder_dest . '/' . $file );
					//@chmod($folder_dest . '/' . $file, 0777);
					if ( $check ) {
						//echo 'Success copy file : ' . $file;
					} else {
						//echo 'Failed copy : ' . $file;
						$check = FALSE;
					}
				}
			}
		}
		closedir( $dir );

		return $check;
	}

	/**
	 * Check exist folder
	 * 
	 * @param String $folder
	 * @return boolean
	 * 
	 */
	private function check_exist_folder( $folder ) {
		// Get canonicalized absolute pathname
		$path = realpath( $folder );

		// If it exist, check if it's a directory
		return ($path !== false AND is_dir( $path )) ? true : false;
	}

	/**
	 * Delete folder if have file in it
	 * 
	 * @param type $path
	 */
	public function delete_directory( $path ) {

		if ( function_exists( 'exec' ) ) {
			exec( 'rm -rf ' . escapeshellarg( $path ) );
			return;
		}
		if ( is_dir( $path ) === true ) {
			$files = array_diff( scandir( $path ), array( '.', '..' ) );

			foreach ( $files as $file ) {
				delete_directory( realpath( $path ) . '/' . $file );
			}

			return rmdir( $path );
		} else if ( is_file( $path ) === true ) {
			return unlink( $path );
		}

		return false;
	}

}
