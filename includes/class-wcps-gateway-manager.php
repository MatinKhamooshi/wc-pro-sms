<?php
/**
 * مدیریت درگاه‌های پیامک.
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Gateway_Manager {

	/**
	 * لیست کلاس‌های درگاه‌های موجود.
	 *
	 * @return array<string, string> شناسه => نام کلاس.
	 */
	public static function get_gateway_classes() {
		/**
		 * درگاه‌های جدید را با این فیلتر اضافه کنید:
		 * add_filter( 'wcps_gateways', fn( $g ) => $g + [ 'my_gateway' => 'My_Gateway_Class' ] );
		 */
		return apply_filters(
			'wcps_gateways',
			array(
				'iranpayamak' => 'WCPS_Gateway_IranPayamak',
			)
		);
	}

	/**
	 * لیست شناسه => برچسب برای نمایش در تنظیمات.
	 *
	 * @return array<string, string>
	 */
	public static function get_gateway_choices() {
		$choices = array();
		foreach ( self::get_gateway_classes() as $id => $class ) {
			if ( class_exists( $class ) ) {
				$instance       = new $class();
				$choices[ $id ] = $instance->get_label();
			}
		}
		return $choices;
	}

	/**
	 * نمونه درگاه فعال بر اساس تنظیمات ذخیره‌شده.
	 *
	 * @return WCPS_Gateway|null
	 */
	public static function get_active_gateway() {
		$settings = get_option( 'wcps_gateway', array() );
		$id       = isset( $settings['gateway'] ) ? $settings['gateway'] : 'iranpayamak';
		$classes  = self::get_gateway_classes();

		if ( ! isset( $classes[ $id ] ) || ! class_exists( $classes[ $id ] ) ) {
			return null;
		}

		return new $classes[ $id ]( $settings );
	}
}
