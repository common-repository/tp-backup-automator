<?php

/**
 * Template file
 * 
 * @since 1.0.0
 */
function tpba_template( $slug, $data = array() ) {

	if ( is_array( $data ) ) {
		extract( $data );
	}

	include TPBA_DIR . 'templates/' . $slug . '.php';
}

/**
 * Get content of template file
 * 
 * @since 1.0.0
 */
function tpba_get_template( $slug, $data = array() ) {
	ob_start();
	tpba_template( $slug, $data );
	return ob_get_clean();
}

/**
 * Server send event
 * Print message to client
 * @since 1.0
 */
function tpba_sse( $id = 'print', $message = '', $progress = 0 ) {

	$arr = array( 'message' => $message );

	if ( $progress ) {
		$arr['progress'] = $progress;
	}

	$arr = json_encode( $arr );
	echo "id: {$id}\n";
	echo "data: {$arr}\n\n";

	flush();
}

function tpba_testcode( $func ) {
	
	$execution_time = -1;
	
	if ( is_callable( $func ) ) {
		$time_start = microtime( true );
		call_user_func( $func );
		$time_end = microtime( true );
		$execution_time = ($time_end - $time_start) / 3600;
	}

	return $execution_time;
}

function format_address($address) {
    $output = str_replace(array('http://', 'https://', ' ', '://'), '', $address);
    return $output;
}

/**
 * Admin notice
 *
 * @since 1.1.1
 */
add_action( 'admin_notices', 'tpfw_notice_admin' );
if (!function_exists('tpfw_notice_admin')){
	function tpfw_notice_admin(){

		$logo = sprintf('<img style="width: 30px;height:auto;vertical-align: middle;" src="%s" alt="logo-themepond">',TPBA_URL.'/assets/images/logo-tp.png');
		$class = 'notice tp-notice';
		$message = sprintf(__('Explore more about our products such as: PSD Templates, Premium Plugins, WordPress Themes,... on ThemesPond. <a href="%s" target="_blank">View Now!</a>','tp-backup-automator'),esc_url('https://www.themespond.com/'));

		echo wp_kses_post( sprintf( '<div class="%1$s"><p>%3$s %2$s</p></div>', $class , $message , $logo));

	}

}