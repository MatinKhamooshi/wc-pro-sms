<?php
/**
 * درخواست‌های AJAX پیشخوان: بررسی اتصال/موجودی، ارسال تست، پاک کردن گزارش.
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_wcps_check_connection', array( $this, 'check_connection' ) );
		add_action( 'wp_ajax_wcps_send_test', array( $this, 'send_test' ) );
		add_action( 'wp_ajax_wcps_clear_logs', array( $this, 'clear_logs' ) );
	}

	private function verify_request() {
		check_ajax_referer( 'wcps_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
	}

	public function check_connection() {
		$this->verify_request();

		$gateway = WCPS_Gateway_Manager::get_active_gateway();

		if ( ! $gateway || ! $gateway->is_configured() ) {
			wp_send_json_error( array( 'message' => 'ابتدا کلید API و شماره خط را ذخیره کنید.' ) );
		}

		$balance = $gateway->get_balance();

		if ( is_wp_error( $balance ) ) {
			wp_send_json_error( array( 'message' => $balance->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => 'اتصال برقرار است.',
				'amount'  => number_format( $balance['amount'] ),
				'count'   => number_format( $balance['count'] ),
			)
		);
	}

	public function send_test() {
		$this->verify_request();

		$recipient = isset( $_POST['recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient'] ) ) : '';
		$mode      = ( isset( $_POST['mode'] ) && 'pattern' === $_POST['mode'] ) ? 'pattern' : 'simple';

		$recipients = WCPS_Notifications::parse_numbers( $recipient );
		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => 'شماره گیرنده معتبر نیست.' ) );
		}

		$gateway = WCPS_Gateway_Manager::get_active_gateway();
		if ( ! $gateway || ! $gateway->is_configured() ) {
			wp_send_json_error( array( 'message' => 'ابتدا درگاه پیامک را در تب «درگاه پیامک» پیکربندی و ذخیره کنید.' ) );
		}

		if ( 'pattern' === $mode ) {
			$code = isset( $_POST['pattern_code'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern_code'] ) ) : '';
			$vars = isset( $_POST['pattern_vars'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pattern_vars'] ) ) : '';

			if ( '' === $code ) {
				wp_send_json_error( array( 'message' => 'کد پترن را وارد کنید.' ) );
			}

			$attributes = WCPS_Variables::parse_pattern_attributes( $vars, array() );
			$result     = $gateway->send_pattern( $recipients[0], $code, $attributes );
		} else {
			$text = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';

			if ( '' === trim( $text ) ) {
				wp_send_json_error( array( 'message' => 'متن پیامک را وارد کنید.' ) );
			}

			$result = $gateway->send( array( $recipients[0] ), $text );
		}

		WCPS_Logger::log(
			'test',
			$recipients[0],
			$mode,
			! is_wp_error( $result ),
			is_wp_error( $result ) ? $result->get_error_message() : ''
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'پیامک تست با موفقیت ارسال شد.' ) );
	}

	public function clear_logs() {
		$this->verify_request();
		WCPS_Logger::clear();
		wp_send_json_success( array( 'message' => 'گزارش‌ها پاک شد.' ) );
	}
}
