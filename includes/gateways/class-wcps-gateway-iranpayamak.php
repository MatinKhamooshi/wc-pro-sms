<?php
/**
 * درگاه ایران‌پیامک — مستندات: https://docs.iranpayamak.com/
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Gateway_IranPayamak extends WCPS_Gateway {

	const API_BASE = 'https://api.iranpayamak.com';

	public function get_id() {
		return 'iranpayamak';
	}

	public function get_label() {
		return 'ایران‌پیامک';
	}

	public function is_configured() {
		return ! empty( $this->settings['api_key'] ) && ! empty( $this->settings['line_number'] );
	}

	/**
	 * ارسال پیامک ساده — POST /ws/v1/sms/simple
	 */
	public function send( array $recipients, $text ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wcps_not_configured', 'کلید API یا شماره خط ایران‌پیامک تنظیم نشده است.' );
		}

		$result = $this->request(
			'POST',
			'/ws/v1/sms/simple',
			array(
				'text'          => $text,
				'line_number'   => $this->settings['line_number'],
				'recipients'    => array_values( $recipients ),
				'number_format' => $this->get_number_format(),
				'schedule'      => null,
			)
		);

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * ارسال پیامک با پترن — POST /ws/v1/sms/pattern
	 */
	public function send_pattern( $recipient, $code, array $attributes ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wcps_not_configured', 'کلید API یا شماره خط ایران‌پیامک تنظیم نشده است.' );
		}

		$result = $this->request(
			'POST',
			'/ws/v1/sms/pattern',
			array(
				'code'          => $code,
				'recipient'     => $recipient,
				'attributes'    => (object) $attributes,
				'line_number'   => $this->settings['line_number'],
				'number_format' => $this->get_number_format(),
			)
		);

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * دریافت موجودی — GET /ws/v1/account/balance
	 */
	public function get_balance() {
		if ( empty( $this->settings['api_key'] ) ) {
			return new WP_Error( 'wcps_not_configured', 'کلید API ایران‌پیامک وارد نشده است.' );
		}

		$data = $this->request( 'GET', '/ws/v1/account/balance' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array(
			'amount' => isset( $data['balanceAmount'] ) ? (float) $data['balanceAmount'] : 0,
			'count'  => isset( $data['balanceCount'] ) ? (int) $data['balanceCount'] : 0,
		);
	}

	private function get_number_format() {
		return ( isset( $this->settings['number_format'] ) && 'persian' === $this->settings['number_format'] ) ? 'persian' : 'english';
	}

	/**
	 * فراخوانی API ایران‌پیامک.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   مسیر endpoint.
	 * @param array|null $body   بدنه درخواست.
	 * @return mixed|WP_Error مقدار فیلد data پاسخ در صورت موفقیت.
	 */
	private function request( $method, $path, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Api-Key'      => $this->settings['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::API_BASE . $path, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wcps_http_error', 'خطا در اتصال به ایران‌پیامک: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$json        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status_code ) {
			return new WP_Error( 'wcps_unauthorized', 'کلید API نامعتبر است (خطای 401).' );
		}

		if ( ! is_array( $json ) ) {
			return new WP_Error( 'wcps_bad_response', 'پاسخ نامعتبر از سرور ایران‌پیامک (کد ' . $status_code . ').' );
		}

		if ( isset( $json['status'] ) && 'success' === $json['status'] ) {
			return isset( $json['data'] ) ? $json['data'] : true;
		}

		return new WP_Error( 'wcps_api_error', 'خطای ایران‌پیامک: ' . self::flatten_messages( $json ) );
	}

	/**
	 * پیام‌های خطای API ممکن است رشته، آرایه یا آبجکت باشند؛ به رشته تبدیل می‌شوند.
	 *
	 * @param array $json پاسخ JSON.
	 * @return string
	 */
	private static function flatten_messages( array $json ) {
		$messages = null;
		foreach ( array( 'messages', 'message' ) as $key ) {
			if ( ! empty( $json[ $key ] ) ) {
				$messages = $json[ $key ];
				break;
			}
		}

		if ( null === $messages ) {
			return 'خطای نامشخص';
		}

		if ( is_string( $messages ) ) {
			return $messages;
		}

		$flat = array();
		array_walk_recursive(
			(array) $messages,
			function ( $item ) use ( &$flat ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
		);

		return $flat ? implode( ' | ', $flat ) : 'خطای نامشخص';
	}
}
