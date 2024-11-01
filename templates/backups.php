<div class="alert-placeholder"></div>

<div class="progress-placeholder"></div>

<?php
$activities = array();
try {
	$service = new TPBA_Services();
	$activities = $service->get_activities( 'backup' );
} catch ( Exception $ex ) {
	echo '<div class="tpba_alert tpba_alert--error">' . $ex->getMessage() . '</div>';
}

if ( $activities ):
	?>
	<table>

		<thead>
			<tr>
				<th>
					<?php echo esc_html__( 'WP Files', 'tp-backup-automator' ) ?>
				</th>
				<th>
					<?php echo esc_html__( 'Sql tables', 'tp-backup-automator' ) ?>
				</th>
				<th>
					<?php echo esc_html__( 'All changes', 'tp-backup-automator' ) ?>
				</th>
				<th>
					<?php echo esc_html__( 'Status', 'tp-backup-automator' ) ?>
				</th>
				<th>
					<?php echo esc_html__( 'Datetime', 'tp-backup-automator' ) ?>
				</th>
				<th></th>
			</tr>
		</thead>

		<tbody>
			<?php
			$restore_current = get_option( 'tpba_restore_current' );

			$session_id = !empty( $restore_current['session_id'] ) ? $restore_current['session_id'] : '';

			$current = get_option( 'tpba_backup_current' );

			foreach ( $activities as $item ) :

				if ( (isset( $current['session_id'] ) && $current['session_id'] == $item->session) || !$item->complete) {
					continue;
				}

				$currentRestore = $session_id == $item->session ? 'current' : '';

				$datetime = str_replace( '.000000', '', $item->start_backup->date );

				$backupdate = DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, new DateTimeZone( $item->start_backup->timezone ) );

				$timenow = new DateTime( 'now', new DateTimeZone( $item->start_backup->timezone ) );

				$humandatetime = human_time_diff( $backupdate->getTimestamp(), $timenow->getTimestamp() );
				?>
				<tr class="<?php echo esc_attr( $currentRestore ) ?>">

					<td><?php echo esc_html( $item->file_count ) ?></td>
					<td><?php echo esc_html( $item->sql_count ) ?></td>
					<td><?php echo esc_html( $item->total_count ) ?></td>
					<td>
						<?php
						$statusText =esc_html__( 'Done', 'tp-backup-automator' );
						$buttonClass = 'button-primary';
						$disabled = '';
						if ( empty( $item->complete ) ) {
							$statusText =esc_html__( 'Error', 'tp-backup-automator' );
							$disabled = 'disabled';
							$buttonClass = 'button-secondary';
						}

						echo esc_html( $statusText );
						?>
					</td>
					<td>
						<abbr title="<?php echo esc_attr( $backupdate->format( 'd/m/Y H:i:s' ) ) ?>" ><?php echo esc_html( $humandatetime ) . ' ' .esc_html__( 'ago', 'tp-backup-automator' ) ?></abbr><br/>
					</td>
					<td>
						<button <?php echo esc_attr( $disabled ) ?> data-session_id="<?php echo esc_attr( $item->session ) ?>" class="button js-restore <?php echo esc_attr( $buttonClass ) ?>"><?php echo esc_html( 'Restore now', 'tp-backup-automator' ) ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>

	</table>

	<?php
else:
	echo '<div class="tpba_alert tpba_alert--warning mb-0 mt-3">';
	echo esc_html__( 'You have no backup list to display.', 'tp-backup-automator' );
	echo '</div>';
 endif;