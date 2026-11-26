<?php
/**
 * کلاس پایه درگاه پیامک. برای افزودن درگاه جدید این کلاس را extend کنید
 * و درگاه را با فیلتر `wcps_gateways` ثبت کنید.
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

abstract class WCPS_Gateway {

	/**
	 * تنظیمات ذخیره‌شده درگاه (کلید API، شماره خط و...).
	 *
	 * @var array
	 */
	protected $settings = array();

	public function __construct( array $settings = array() ) {
		$this->settings = $settings;
	}

	/**
	 * شناسه یکتای درگاه.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * نام نمایشی درگاه.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * ارسال پیامک ساده به یک یا چند گیرنده.
	 *
	 * @param string[] $recipients شماره‌های گیرنده.
	 * @param string   $text       متن پیامک.
	 * @return true|WP_Error
	 */
	abstract public function send( array $recipients, $text );

	/**
	 * ارسال پیامک با پترن به یک گیرنده.
	 *
	 * @param string $recipient  شماره گیرنده.
	 * @param string $code       کد پترن.
	 * @param array  $attributes متغیرهای پترن (کلید => مقدار).
	 * @return true|WP_Error
	 */
	abstract public function send_pattern( $recipient, $code, array $attributes );

	/**
	 * دریافت موجودی حساب.
	 *
	 * @return array|WP_Error آرایه‌ای شامل amount و count در صورت موفقیت.
	 */
	abstract public function get_balance();

	/**
	 * بررسی وضعیت اتصال به درگاه.
	 *
	 * @return true|WP_Error
	 */
	public function check_connection() {
		$balance = $this->get_balance();
		return is_wp_error( $balance ) ? $balance : true;
	}

	/**
	 * آیا اطلاعات لازم برای اتصال وارد شده است؟
	 *
	 * @return bool
	 */
	abstract public function is_configured();
}
