<div class="alert-placeholder"></div>

<div class="progress-placeholder"></div>

<?php
$activities = array();
$is_connect = true;
try {
	$service = new TPBA_Services();
	$activities = $service->get_activities( 'all' );

	if ( empty( $activities ) ) {
		$master = new TPBA_Master();
		$master->truncate();
	}
} catch ( Exception $ex ) {
	$is_connect = false;
	$url = add_query_arg( 'reset', 'true', admin_url( 'tools.php?page=tp-backup-automator' ) );
	$url = wp_nonce_url( $url, 'reset' );
	$button = $ex->getCode() == 401 ? sprintf( '<a href="%s">%s</a>', $url, esc_html__( 'Re-validate email', 'tp-backup-automator' ) ) : '';
	echo '<div class="tpba_alert tpba_alert--error">' . $ex->getMessage() . ' ' . $button . '</div>';
}

if ( $is_connect ):
	?>
	<table class="activity-list">

		<thead>
			<tr>
				<th>
					<h2><?php echo esc_html__( 'Recent activities', 'tp-backup-automator' ) ?></h2>

					<?php
					$current = get_option( 'tpba_backup_current' );

					if ( !$current && !get_option( 'tpba_restore_current' ) ):
						printf( '<button class="js-backup-manually button"><span class="dashicons dashicons-external"></span> %s</button>', esc_html__( 'Create a backup Now', 'tp-backup-automator' ) );
					endif;
					?>
				</th>
			</tr>
		</thead>

		<tbody>
			<?php
			if ( !empty( $activities ) ):

				$restore_current = get_option( 'tpba_restore_current' );

				$session_id = !empty( $restore_current['security'] ) ? $restore_current['security'] : '';

				foreach ( $activities as $item ) :

					if ( isset( $current['session_id'] ) && $current['session_id'] == $item->session ) {
						continue;
					}

					$datetime = str_replace( '.000000', '', $item->start_backup->date );

					$backupdate = DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, new DateTimeZone( $item->start_backup->timezone ) );

					$timenow = new DateTime( 'now', new DateTimeZone( $item->start_backup->timezone ) );

					$humandatetime = human_time_diff( $backupdate->getTimestamp(), $timenow->getTimestamp() );

					$content = '';

					$tagDate = '<abbr title="' . $backupdate->format( 'd/m/Y H:i:s' ) . '">' . $humandatetime . '</abbr>';

					$cssClass = 'item-' . $item->type;

					if ( $item->type == 'backup' ) {
						$content = '<span class="dashicons dashicons-external"></span>';

						if ( $item->complete ) {
							$cssClass .= ' item-done';
							$content .= sprintf( esc_html__( 'You have created a backup with %d changed files about %s ago.', 'tp-backup-automator' ), $item->total_count, $tagDate );
						} else {
							$cssClass .= ' item-error';
							$content .= sprintf( esc_html__( 'You have some error with a backup with %d changed files about %s ago.', 'tp-backup-automator' ), $item->total_count, $tagDate );
						}
					} else {
						$content = '<span class="dashicons dashicons-image-rotate-left"></span>';

						if ( $session_id == $item->session ) {
							$cssClass .= ' item-done';
							$content .= sprintf( esc_html__( 'You are restoring data with %d files, begin from %s ago.', 'tp-backup-automator' ), $item->total_count, $tagDate );
						} else {
							$content .= sprintf( esc_html__( 'You have restored all data with %d files about %s ago.', 'tp-backup-automator' ), $item->total_count, $tagDate );
						}
					}
					?>
					<tr class="<?php echo esc_attr( $cssClass ) ?>">
						<td>
							<?php echo wp_kses_post( $content ) ?>
						</td>
					</tr>
					<?php
				endforeach;
			else:
				echo '<tr><td>';
				echo '<div class="tpba_alert tpba_alert--warning mb-0 mt-3">';
				echo esc_html__( 'You have no activity to display.', 'tp-backup-automator' );
				echo '</div>';
				echo '</td></tr>';
			endif;
			?>
		</tbody>

	</table>
	<?php







 endif;