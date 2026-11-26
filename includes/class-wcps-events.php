<?php
/**
 * تعریف رویدادهای پیامکی (مدیر و مشتری).
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Events {

	/**
	 * رویدادهای پیامک مدیر.
	 *
	 * @return array<string, array{label: string, context: string, description?: string}>
	 */
	public static function admin_events() {
		$events = array(
			'new_order' => array(
				'label'       => 'ثبت سفارش جدید',
				'context'     => 'order',
				'description' => 'به محض ایجاد سفارش جدید در فروشگاه ارسال می‌شود.',
			),
		);

		foreach ( self::order_statuses() as $status => $label ) {
			$events[ 'status_' . $status ] = array(
				'label'       => 'تغییر وضعیت سفارش به «' . $label . '»',
				'context'     => 'order',
				'description' => '',
			);
		}

		$events['low_stock'] = array(
			'label'       => 'کاهش موجودی محصول (کمتر از آستانه)',
			'context'     => 'product',
			'description' => 'وقتی موجودی محصول به آستانه «موجودی کم» ووکامرس برسد.',
		);

		$events['no_stock'] = array(
			'label'       => 'اتمام موجودی محصول',
			'context'     => 'product',
			'description' => 'وقتی موجودی محصول تمام شود.',
		);

		$events['new_user'] = array(
			'label'       => 'ثبت‌نام کاربر جدید',
			'context'     => 'user',
			'description' => 'با ثبت‌نام هر کاربر جدید در سایت ارسال می‌شود.',
		);

		return apply_filters( 'wcps_admin_events', $events );
	}

	/**
	 * رویدادهای پیامک مشتری.
	 *
	 * @return array<string, array{label: string, context: string, description?: string}>
	 */
	public static function customer_events() {
		$events = array(
			'new_order' => array(
				'label'       => 'ثبت سفارش',
				'context'     => 'order',
				'description' => 'پس از ثبت سفارش برای مشتری ارسال می‌شود.',
			),
		);

		foreach ( self::order_statuses() as $status => $label ) {
			$events[ 'status_' . $status ] = array(
				'label'       => 'تغییر وضعیت سفارش به «' . $label . '»',
				'context'     => 'order',
				'description' => '',
			);
		}

		$events['new_user'] = array(
			'label'       => 'خوش‌آمدگویی پس از ثبت‌نام',
			'context'     => 'user',
			'description' => 'در صورتی که شماره موبایل کاربر (billing_phone) موجود باشد ارسال می‌شود.',
		);

		return apply_filters( 'wcps_customer_events', $events );
	}

	/**
	 * وضعیت‌های سفارش ووکامرس بدون پیشوند wc-.
	 *
	 * @return array<string, string>
	 */
	public static function order_statuses() {
		$statuses = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$statuses[ preg_replace( '/^wc-/', '', $key ) ] = $label;
		}
		return $statuses;
	}
}
