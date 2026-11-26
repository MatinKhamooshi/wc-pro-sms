<?php
/**
 * ثبت گزارش ساده ارسال‌ها (۵۰ مورد آخر).
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Logger {

	const OPTION   = 'wcps_logs';
	const MAX_LOGS = 50;

	/**
	 * ثبت یک رکورد گزارش.
	 *
	 * @param string $event     شناسه رویداد (مثلاً admin:new_order یا test).
	 * @param string $recipient گیرنده(ها).
	 * @param string $mode      simple یا pattern.
	 * @param bool   $success   نتیجه ارسال.
	 * @param string $message   پیام خطا در صورت شکست.
	 */
	public static function log( $event, $recipient, $mode, $success, $message = '' ) {
		$logs = get_option( self::OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift(
			$logs,
			array(
				'time'      => current_time( 'mysql' ),
				'event'     => $event,
				'recipient' => $recipient,
				'mode'      => $mode,
				'success'   => (bool) $success,
				'message'   => $message,
			)
		);

		update_option( self::OPTION, array_slice( $logs, 0, self::MAX_LOGS ), false );
	}

	/**
	 * دریافت گزارش‌ها.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( self::OPTION, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * پاک کردن گزارش‌ها.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
