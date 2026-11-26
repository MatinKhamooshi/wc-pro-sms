<?php
/**
 * اتصال به رویدادهای ووکامرس و ارسال پیامک‌ها.
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Notifications {

	public function __construct() {
		add_action( 'woocommerce_new_order', array( $this, 'on_new_order' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_low_stock', array( $this, 'on_low_stock' ), 20 );
		add_action( 'woocommerce_no_stock', array( $this, 'on_no_stock' ), 20 );
		add_action( 'user_register', array( $this, 'on_user_register' ), 20 );
	}

	/**
	 * سفارش جدید.
	 *
	 * @param int           $order_id شناسه سفارش.
	 * @param WC_Order|null $order    سفارش.
	 */
	public function on_new_order( $order_id, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$vars = WCPS_Variables::order_vars( $order );

		$this->dispatch( 'admin', 'new_order', $vars );
		$this->dispatch( 'customer', 'new_order', $vars, $order->get_billing_phone() );
	}

	/**
	 * تغییر وضعیت سفارش.
	 *
	 * @param int      $order_id شناسه سفارش.
	 * @param string   $from     وضعیت قبلی.
	 * @param string   $to       وضعیت جدید.
	 * @param WC_Order $order    سفارش.
	 */
	public function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
		}

		$vars = WCPS_Variables::order_vars(
			$order,
			array( 'order_status_prev' => wc_get_order_status_name( $from ) )
		);

		$event = 'status_' . $to;

		$this->dispatch( 'admin', $event, $vars );
		$this->dispatch( 'customer', $event, $vars, $order->get_billing_phone() );
	}

	/**
	 * موجودی کم محصول.
	 *
	 * @param WC_Product $product محصول.
	 */
	public function on_low_stock( $product ) {
		if ( $product instanceof WC_Product ) {
			$this->dispatch( 'admin', 'low_stock', WCPS_Variables::product_vars( $product ) );
		}
	}

	/**
	 * اتمام موجودی محصول.
	 *
	 * @param WC_Product $product محصول.
	 */
	public function on_no_stock( $product ) {
		if ( $product instanceof WC_Product ) {
			$this->dispatch( 'admin', 'no_stock', WCPS_Variables::product_vars( $product ) );
		}
	}

	/**
	 * ثبت‌نام کاربر جدید.
	 *
	 * @param int $user_id شناسه کاربر.
	 */
	public function on_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$vars = WCPS_Variables::user_vars( $user );

		$this->dispatch( 'admin', 'new_user', $vars );
		$this->dispatch( 'customer', 'new_user', $vars, $vars['user_mobile'] );
	}

	/**
	 * ارسال پیامک برای یک رویداد بر اساس تنظیمات ذخیره‌شده.
	 *
	 * @param string      $side               admin یا customer.
	 * @param string      $event_key          کلید رویداد.
	 * @param array       $vars               متغیرهای رویداد.
	 * @param string|null $customer_recipient شماره مشتری (فقط برای side=customer).
	 */
	private function dispatch( $side, $event_key, array $vars, $customer_recipient = null ) {
		$option = get_option( 'admin' === $side ? 'wcps_admin_sms' : 'wcps_customer_sms', array() );
		$config = isset( $option['events'][ $event_key ] ) ? $option['events'][ $event_key ] : array();

		if ( empty( $config['enabled'] ) ) {
			return;
		}

		if ( 'admin' === $side ) {
			$recipients = self::parse_numbers( isset( $option['recipients'] ) ? $option['recipients'] : '' );
		} else {
			$recipients = self::parse_numbers( (string) $customer_recipient );
		}

		if ( empty( $recipients ) ) {
			return;
		}

		$gateway = WCPS_Gateway_Manager::get_active_gateway();
		if ( ! $gateway || ! $gateway->is_configured() ) {
			WCPS_Logger::log( $side . ':' . $event_key, implode( '،', $recipients ), '-', false, 'درگاه پیامک پیکربندی نشده است.' );
			return;
		}

		$mode = ( isset( $config['mode'] ) && 'pattern' === $config['mode'] ) ? 'pattern' : 'simple';

		if ( 'pattern' === $mode ) {
			$code = isset( $config['pattern_code'] ) ? trim( $config['pattern_code'] ) : '';
			if ( '' === $code ) {
				WCPS_Logger::log( $side . ':' . $event_key, implode( '،', $recipients ), 'pattern', false, 'کد پترن وارد نشده است.' );
				return;
			}

			$attributes = WCPS_Variables::parse_pattern_attributes(
				isset( $config['pattern_vars'] ) ? $config['pattern_vars'] : '',
				$vars
			);

			foreach ( $recipients as $recipient ) {
				$result = $gateway->send_pattern( $recipient, $code, $attributes );
				WCPS_Logger::log(
					$side . ':' . $event_key,
					$recipient,
					'pattern',
					! is_wp_error( $result ),
					is_wp_error( $result ) ? $result->get_error_message() : ''
				);
			}
			return;
		}

		$text = WCPS_Variables::replace( isset( $config['text'] ) ? $config['text'] : '', $vars );
		if ( '' === trim( $text ) ) {
			return;
		}

		$result = $gateway->send( $recipients, $text );
		WCPS_Logger::log(
			$side . ':' . $event_key,
			implode( '،', $recipients ),
			'simple',
			! is_wp_error( $result ),
			is_wp_error( $result ) ? $result->get_error_message() : ''
		);
	}

	/**
	 * جداسازی و نرمال‌سازی شماره‌ها (خط جدید، کاما و ویرگول فارسی) و تبدیل ارقام فارسی/عربی.
	 *
	 * @param string $raw ورودی خام.
	 * @return string[]
	 */
	public static function parse_numbers( $raw ) {
		$numbers = array();

		foreach ( preg_split( '/[\r\n,،]+/u', (string) $raw ) as $number ) {
			$number = self::normalize_digits( trim( $number ) );
			$number = preg_replace( '/[^0-9+]/', '', $number );
			if ( '' !== $number && strlen( $number ) >= 8 ) {
				$numbers[] = $number;
			}
		}

		return array_values( array_unique( $numbers ) );
	}

	/**
	 * تبدیل ارقام فارسی و عربی به انگلیسی.
	 *
	 * @param string $value ورودی.
	 * @return string
	 */
	public static function normalize_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$english = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $arabic, $english, str_replace( $persian, $english, (string) $value ) );
	}
}
