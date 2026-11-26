<?php
/**
 * صفحه تنظیمات پلاگین در پیشخوان.
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Admin {

	const PAGE_SLUG = 'wc-pro-sms';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			'پیامک پرو ووکامرس',
			'پیامک پرو',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wcps_gateway_group',
			'wcps_gateway',
			array( 'sanitize_callback' => array( $this, 'sanitize_gateway' ) )
		);
		register_setting(
			'wcps_admin_sms_group',
			'wcps_admin_sms',
			array( 'sanitize_callback' => array( $this, 'sanitize_admin_sms' ) )
		);
		register_setting(
			'wcps_customer_sms_group',
			'wcps_customer_sms',
			array( 'sanitize_callback' => array( $this, 'sanitize_customer_sms' ) )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wcps-admin', WCPS_URL . 'assets/admin.css', array(), WCPS_VERSION );
		wp_enqueue_script( 'wcps-admin', WCPS_URL . 'assets/admin.js', array( 'jquery' ), WCPS_VERSION, true );
		wp_localize_script(
			'wcps-admin',
			'wcpsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wcps_ajax' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Sanitize callbacks
	 * ------------------------------------------------------------------- */

	public function sanitize_gateway( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$choices = WCPS_Gateway_Manager::get_gateway_choices();

		return array(
			'gateway'       => isset( $input['gateway'], $choices[ $input['gateway'] ] ) ? $input['gateway'] : 'iranpayamak',
			'api_key'       => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'line_number'   => isset( $input['line_number'] ) ? sanitize_text_field( $input['line_number'] ) : '',
			'number_format' => ( isset( $input['number_format'] ) && 'persian' === $input['number_format'] ) ? 'persian' : 'english',
			'show_balance'  => empty( $input['show_balance'] ) ? 0 : 1,
		);
	}

	public function sanitize_admin_sms( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'recipients' => isset( $input['recipients'] ) ? sanitize_textarea_field( $input['recipients'] ) : '',
			'events'     => $this->sanitize_events( $input, WCPS_Events::admin_events() ),
		);
	}

	public function sanitize_customer_sms( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'events' => $this->sanitize_events( $input, WCPS_Events::customer_events() ),
		);
	}

	private function sanitize_events( array $input, array $events ) {
		$clean = array();
		$raw   = isset( $input['events'] ) && is_array( $input['events'] ) ? $input['events'] : array();

		foreach ( $events as $key => $event ) {
			$row = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();

			$clean[ $key ] = array(
				'enabled'      => empty( $row['enabled'] ) ? 0 : 1,
				'mode'         => ( isset( $row['mode'] ) && 'pattern' === $row['mode'] ) ? 'pattern' : 'simple',
				'text'         => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
				'pattern_code' => isset( $row['pattern_code'] ) ? sanitize_text_field( $row['pattern_code'] ) : '',
				'pattern_vars' => isset( $row['pattern_vars'] ) ? sanitize_textarea_field( $row['pattern_vars'] ) : '',
			);
		}

		return $clean;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tabs = array(
			'gateway'  => 'درگاه پیامک',
			'admin'    => 'پیامک مدیر',
			'customer' => 'پیامک مشتری',
			'test'     => 'ارسال تست',
			'logs'     => 'گزارش ارسال',
		);

		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'gateway'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'gateway';
		}
		?>
		<div class="wrap wcps-wrap" dir="rtl">
			<h1>پیامک پرو ووکامرس</h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>"
						class="nav-tab <?php echo $slug === $current ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wcps-tab-content">
				<?php
				switch ( $current ) {
					case 'admin':
						$this->render_events_tab( 'admin' );
						break;
					case 'customer':
						$this->render_events_tab( 'customer' );
						break;
					case 'test':
						$this->render_test_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					default:
						$this->render_gateway_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_gateway_tab() {
		$settings = wp_parse_args(
			get_option( 'wcps_gateway', array() ),
			array(
				'gateway'       => 'iranpayamak',
				'api_key'       => '',
				'line_number'   => '',
				'number_format' => 'english',
				'show_balance'  => 1,
			)
		);
		$choices  = WCPS_Gateway_Manager::get_gateway_choices();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wcps_gateway_group' ); ?>

			<div class="wcps-card">
				<h2>تنظیمات درگاه پیامک</h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wcps-gateway">درگاه فعال</label></th>
						<td>
							<select id="wcps-gateway" name="wcps_gateway[gateway]">
								<?php foreach ( $choices as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['gateway'], $id ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">درگاه‌های بیشتر در نسخه‌های بعدی اضافه می‌شوند.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcps-api-key">کلید API</label></th>
						<td>
							<input type="password" id="wcps-api-key" class="regular-text" dir="ltr" autocomplete="off"
								name="wcps_gateway[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>">
							<p class="description">کلید API را از پنل کاربری <a href="https://iranpayamak.com" target="_blank" rel="noopener">ایران‌پیامک</a> دریافت کنید.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcps-line-number">شماره خط ارسال</label></th>
						<td>
							<input type="text" id="wcps-line-number" class="regular-text" dir="ltr"
								name="wcps_gateway[line_number]" value="<?php echo esc_attr( $settings['line_number'] ); ?>">
							<p class="description">مثال: 50002178584000</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wcps-number-format">قالب اعداد پیامک</label></th>
						<td>
							<select id="wcps-number-format" name="wcps_gateway[number_format]">
								<option value="english" <?php selected( $settings['number_format'], 'english' ); ?>>انگلیسی (0-9)</option>
								<option value="persian" <?php selected( $settings['number_format'], 'persian' ); ?>>فارسی (۰-۹)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">نمایش مانده موجودی</th>
						<td>
							<label>
								<input type="checkbox" name="wcps_gateway[show_balance]" value="1" <?php checked( $settings['show_balance'], 1 ); ?>>
								مانده موجودی حساب در همین صفحه نمایش داده شود
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="wcps-card">
				<h2>وضعیت اتصال</h2>
				<p>
					<button type="button" class="button" id="wcps-check-connection">بررسی اتصال و موجودی</button>
					<span id="wcps-connection-status" class="wcps-status"
						data-autoload="<?php echo ( $settings['show_balance'] && $settings['api_key'] ) ? '1' : '0'; ?>"
						data-show-balance="<?php echo $settings['show_balance'] ? '1' : '0'; ?>"></span>
				</p>
			</div>

			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
		<?php
	}

	private function render_events_tab( $side ) {
		$is_admin = ( 'admin' === $side );
		$events   = $is_admin ? WCPS_Events::admin_events() : WCPS_Events::customer_events();
		$option   = get_option( $is_admin ? 'wcps_admin_sms' : 'wcps_customer_sms', array() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( $is_admin ? 'wcps_admin_sms_group' : 'wcps_customer_sms_group' ); ?>

			<?php if ( $is_admin ) : ?>
				<div class="wcps-card">
					<h2>شماره(های) مدیر</h2>
					<textarea name="wcps_admin_sms[recipients]" rows="3" class="large-text" dir="ltr"
						placeholder="09121234567&#10;09351234567"><?php echo esc_textarea( isset( $option['recipients'] ) ? $option['recipients'] : '' ); ?></textarea>
					<p class="description">هر شماره در یک خط، یا با کاما جدا کنید. پیامک همه رویدادهای این بخش به این شماره‌ها ارسال می‌شود.</p>
				</div>
			<?php else : ?>
				<div class="wcps-card wcps-card-info">
					<p>پیامک‌های این بخش به <strong>شماره موبایل صورتحساب مشتری</strong> هر سفارش ارسال می‌شود.</p>
				</div>
			<?php endif; ?>

			<?php
			$option_name = $is_admin ? 'wcps_admin_sms' : 'wcps_customer_sms';
			foreach ( $events as $key => $event ) {
				$config = isset( $option['events'][ $key ] ) ? $option['events'][ $key ] : array();
				$this->render_event_card( $option_name, $key, $event, $config );
			}
			?>

			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
		<?php
	}

	private function render_event_card( $option_name, $key, array $event, array $config ) {
		$config = wp_parse_args(
			$config,
			array(
				'enabled'      => 0,
				'mode'         => 'simple',
				'text'         => '',
				'pattern_code' => '',
				'pattern_vars' => '',
			)
		);

		$name  = $option_name . '[events][' . $key . ']';
		$id    = 'wcps-event-' . sanitize_html_class( $option_name . '-' . $key );
		$hints = WCPS_Variables::hints( $event['context'] );
		?>
		<div class="wcps-card wcps-event <?php echo $config['enabled'] ? 'is-enabled' : ''; ?>">
			<div class="wcps-event-head">
				<label class="wcps-event-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $config['enabled'], 1 ); ?>>
					<strong><?php echo esc_html( $event['label'] ); ?></strong>
				</label>
				<?php if ( ! empty( $event['description'] ) ) : ?>
					<span class="description"><?php echo esc_html( $event['description'] ); ?></span>
				<?php endif; ?>
			</div>

			<div class="wcps-event-body">
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-mode"><strong>روش ارسال:</strong></label>
					<select id="<?php echo esc_attr( $id ); ?>-mode" class="wcps-mode-select" name="<?php echo esc_attr( $name ); ?>[mode]">
						<option value="simple" <?php selected( $config['mode'], 'simple' ); ?>>پیامک ساده (متن دلخواه)</option>
						<option value="pattern" <?php selected( $config['mode'], 'pattern' ); ?>>پیامک با پترن</option>
					</select>
				</p>

				<div class="wcps-mode-simple" <?php echo 'pattern' === $config['mode'] ? 'style="display:none"' : ''; ?>>
					<textarea name="<?php echo esc_attr( $name ); ?>[text]" rows="3" class="large-text wcps-text-field"
						placeholder="مثال: سفارش {order_number} به مبلغ {order_total} ثبت شد."><?php echo esc_textarea( $config['text'] ); ?></textarea>
				</div>

				<div class="wcps-mode-pattern" <?php echo 'pattern' !== $config['mode'] ? 'style="display:none"' : ''; ?>>
					<p>
						<label><strong>کد پترن:</strong></label><br>
						<input type="text" dir="ltr" class="regular-text" name="<?php echo esc_attr( $name ); ?>[pattern_code]"
							value="<?php echo esc_attr( $config['pattern_code'] ); ?>" placeholder="SJ3FgPrE0C">
					</p>
					<p>
						<label><strong>متغیرهای پترن</strong> (هر خط: <code dir="ltr">نام‌متغیر = مقدار</code>):</label>
						<textarea name="<?php echo esc_attr( $name ); ?>[pattern_vars]" rows="3" class="large-text" dir="ltr"
							placeholder="name = {billing_full_name}&#10;order = {order_number}"><?php echo esc_textarea( $config['pattern_vars'] ); ?></textarea>
					</p>
				</div>

				<details class="wcps-hints">
					<summary>متغیرهای قابل استفاده</summary>
					<div class="wcps-hint-chips">
						<?php foreach ( $hints as $hint ) : ?>
							<code class="wcps-chip" title="برای درج کلیک کنید">{<?php echo esc_html( $hint ); ?>}</code>
						<?php endforeach; ?>
					</div>
				</details>
			</div>
		</div>
		<?php
	}

	private function render_test_tab() {
		?>
		<div class="wcps-card">
			<h2>ارسال پیامک تست</h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wcps-test-recipient">شماره گیرنده</label></th>
					<td>
						<input type="text" id="wcps-test-recipient" class="regular-text" dir="ltr" placeholder="09121234567">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wcps-test-mode">روش ارسال</label></th>
					<td>
						<select id="wcps-test-mode" class="wcps-mode-select">
							<option value="simple">پیامک ساده</option>
							<option value="pattern">پیامک با پترن</option>
						</select>
					</td>
				</tr>
			</table>

			<div class="wcps-mode-simple">
				<p><label for="wcps-test-text"><strong>متن پیامک:</strong></label></p>
				<textarea id="wcps-test-text" rows="3" class="large-text">این یک پیامک تست از افزونه پیامک پرو ووکامرس است.</textarea>
			</div>

			<div class="wcps-mode-pattern" style="display:none">
				<p>
					<label for="wcps-test-pattern-code"><strong>کد پترن:</strong></label><br>
					<input type="text" id="wcps-test-pattern-code" class="regular-text" dir="ltr" placeholder="SJ3FgPrE0C">
				</p>
				<p>
					<label for="wcps-test-pattern-vars"><strong>متغیرهای پترن</strong> (هر خط: <code dir="ltr">نام = مقدار</code>):</label>
					<textarea id="wcps-test-pattern-vars" rows="3" class="large-text" dir="ltr" placeholder="name = Ali&#10;code = 1234"></textarea>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary" id="wcps-send-test">ارسال پیامک تست</button>
				<span id="wcps-test-result" class="wcps-status"></span>
			</p>
		</div>
		<?php
	}

	private function render_logs_tab() {
		$logs = WCPS_Logger::get_logs();
		?>
		<div class="wcps-card">
			<h2>گزارش ۵۰ ارسال آخر</h2>

			<p>
				<button type="button" class="button" id="wcps-clear-logs">پاک کردن گزارش‌ها</button>
			</p>

			<?php if ( empty( $logs ) ) : ?>
				<p>هنوز گزارشی ثبت نشده است.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>زمان</th>
							<th>رویداد</th>
							<th>گیرنده</th>
							<th>روش</th>
							<th>نتیجه</th>
							<th>پیام</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['time'] ); ?></td>
								<td><code><?php echo esc_html( $log['event'] ); ?></code></td>
								<td dir="ltr"><?php echo esc_html( $log['recipient'] ); ?></td>
								<td><?php echo esc_html( 'pattern' === $log['mode'] ? 'پترن' : ( '-' === $log['mode'] ? '-' : 'ساده' ) ); ?></td>
								<td>
									<?php if ( ! empty( $log['success'] ) ) : ?>
										<span class="wcps-ok">موفق</span>
									<?php else : ?>
										<span class="wcps-fail">ناموفق</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
