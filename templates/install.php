<?php
$registerd_email = $user->get_email();
$registerd_token = $user->get_token();

$form_current = empty( $registerd_email ) ? 0 : 1;

if ( !empty( $registerd_token ) ) {
	$form_current = 2;
}

$email = empty( $registerd_email ) ? get_option( 'admin_email' ) : $registerd_email;
?>

<div class="wrap">
	<h1></h1>
	<div class="tpba-install tpba_panel">

		<header class="tpba_panel__header">

			<div class="tpba-logo">
				<img src="<?php echo esc_url( TPBA_URL . 'assets/images/logo.jpg' ) ?>" alt="tpba logo"/>
			</div>

			<h3><?php echo esc_html__( 'TP Backup Automator Installer', 'tp-backup-automator' ) ?></h3>
			<p><?php echo esc_html__( 'Backup & Restore your WordPress data and keep it safe.', 'tp-backup-automator' ) ?></p>

			<a class="btn-help" href="https://www.themespond.com/" target="_blank" title="<?php echo esc_attr__( 'Live chat support', 'tp-backup-automator' ) ?>">
				<i class="dashicons dashicons-format-chat"></i>
			</a>

		</header>

		<div class="tpba_panel__content">
			<div class="tp_installer" data-step="<?php echo esc_attr( $form_current ) ?>">

				<ul class="tp_installer__steps">
					<li data-step>
						<?php echo esc_html__( 'Get a product key via email', 'tp-backup-automator' ) ?> <span>1</span>
					</li>
					<li data-step>
						<?php echo esc_html__( 'Validate product key from the email', 'tp-backup-automator' ) ?> <span>2</span>
					</li>
					<li data-step>
						<?php echo esc_html__( 'Your product key is activated', 'tp-backup-automator' ) ?> <span>3</span>
					</li>
				</ul>

				<div class="tp_installer__forms">

					<div data-form="0">
						<input type="email" name="email" value="<?php echo esc_attr( $email ) ?>"/>
						<button class="js-get-token" type="submit"><?php echo esc_html__( 'Get a free key', 'tp-backup-automator' ) ?></button>
						<?php wp_nonce_field( 'tpba_register_token', 'nonce_field_get_token' ) ?>
						<p><?php echo esc_html__( 'If you have a product key?', 'tp-backup-automator' ) ?> <a data-forward href="#"><?php echo esc_html__( 'Active now', 'tp-backup-automator' ) ?></a></p>
						<ul class="tp-errors"></ul>
					</div>

					<div data-form="1">
						<input type="text" name="token" placeholder="<?php echo esc_attr__( 'Enter product key', 'tp-backup-automator' ) ?>" value=""/>
						<input type="hidden" name="email" value="<?php echo esc_attr( $email ) ?>"/>
						<button class="js-validate-token" type="submit"><?php echo esc_html__( 'Validate key', 'tp-backup-automator' ) ?></button>
						<?php wp_nonce_field( 'tpba_validate_token', 'nonce_field_validate_token' ) ?>

						<p><?php echo esc_html__( 'Do you received a product key from email?', 'tp-backup-automator' ) ?> <a data-back href="#"><?php echo esc_html__( 'Back to resend email', 'tp-backup-automator' ) ?></a></p>
						<ul class="tp-errors"></ul>
					</div>

					<div data-form="2">
						<?php
						$cssClass = 'validate-result';
						if ( $user->get_token() != '' && $user->get_email() != '' && !$user->is_validate() ) {
							$cssClass .= ' validate-result--failure';
						}
						?>
						<div class="<?php echo esc_attr( $cssClass ) ?>">
							<span class="dispaly-icon"></span>

							<h4 class="title-success"><?php echo esc_html__( 'Your product key is activated.', 'tp-backup-automator' ); ?></h4>
							<h4 class="title-invalid"><?php echo esc_html__( 'Activation failed because your product key is not available.', 'tp-backup-automator' ) ?></h4>
							<div class="redirect_alert" style="display: none"><?php echo wp_kses_post( sprintf( __( 'Start using in %s seconds or <a href="#">Using now</a>', 'tp-backup-automator' ), '<span>5</span>' ) ) ?></div>

							<div class="frm-change-code">
							</div>
						</div>
					</div>

				</div>
			</div>

		</div>
	</div>
</div>
