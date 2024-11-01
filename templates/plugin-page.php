<?php
$nav = array(
	'' =>esc_html__( 'Dashboard', 'tp-backup-automator' ),
	'backups' =>esc_html__( 'Backup list', 'tp-backup-automator' ),
);

$tab_current = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';

$template = empty( $tab_current ) ? 'dashboard' : $tab_current;

if ( $template == 'restore' && !empty( $_GET['id'] ) ) {
	$template = 'backup-detail';
}



?>
<div class="wrap tpba">
	<h1><?php echo esc_html__('TP Backup Automator','tp-backup-automator' ) ?></h1>
	<br/>
	<div class="tp-backup-automator">

		<div class="tpba-tabs">
			<nav>
				<ul>
					<?php
					foreach ( $nav as $key => $value ) {
						$active = $key == $tab_current ? 'active' : '';
						$tab = $key != '' ? '&tab=' . $key : '';
						printf( '<li class="%s"><a href="%s">%s</a></li>', $active, admin_url( 'tools.php?page=tp-backup-automator' . $tab ), $value );
					}
					?>
				</ul>

				<a class="btn-help" href="https://www.themespond.com/" target="_blank" title="<?php echo esc_html__( 'Live chat support', 'tp-backup-automator' ) ?>">
					<i class="dashicons dashicons-format-chat"></i>
				</a>
			</nav>

			<div id="tpba_<?php echo esc_attr( $template ) ?>" class="tpba_panel">
				<?php tpba_template( $template ) ?>
			</div>

		</div>
	</div>
	
</div>

<script id="tpba_alert" type="text/template">
	<div class="tpba_alert">
		<strong></strong>
		<p></p>
	</div>
</script>

<script id="tpba_progress" type="text/template">
	<div class="tpba_progress">
		<div class="progress_logs">
			<div class="progress_logs__track"><?php echo esc_html__( 'Processed', 'tp-backup-automator' ) ?> <span class="count_file">0</span>/<span class="count_all">0</span> <?php echo esc_html__( 'files', 'tp-backup-automator' ) ?></div>
			<div class="progress_logs__message"><?php echo esc_html__( 'Your progress', 'tp-backup-automator' ) ?></div>
		</div>
		<div class="progress">
			<div class="progress-bar">
				<span class="progress-percent">0%</span>
			</div>
		</div>
	</div>
</script>