<?php
/**
 * ساخت و جایگزینی متغیرهای پیامک ({order_id}، {billing_phone} و...).
 *
 * @package WC_Pro_SMS
 */

defined( 'ABSPATH' ) || exit;

class WCPS_Variables {

	/**
	 * متغیرهای عمومی سایت.
	 *
	 * @return array<string, string>
	 */
	public static function site_vars() {
		return array(
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'  => home_url(),
		);
	}

	/**
	 * متغیرهای سفارش.
	 *
	 * @param WC_Order $order سفارش.
	 * @param array    $extra متغیرهای اضافی (مثل وضعیت قبلی).
	 * @return array<string, string>
	 */
	public static function order_vars( WC_Order $order, array $extra = array() ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' × ' . $item->get_quantity();
		}

		$date  = $order->get_date_created();
		$price = function ( $amount ) use ( $order ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES );
		};

		$vars = array(
			'order_id'              => (string) $order->get_id(),
			'order_number'          => $order->get_order_number(),
			'order_status'          => wc_get_order_status_name( $order->get_status() ),
			'order_total'           => $price( $order->get_total() ),
			'order_total_plain'     => (string) $order->get_total(),
			'order_subtotal'        => $price( $order->get_subtotal() ),
			'order_shipping_total'  => $price( $order->get_shipping_total() ),
			'order_discount_total'  => $price( $order->get_discount_total() ),
			'coupon_codes'          => implode( '، ', $order->get_coupon_codes() ),
			'order_date'            => $date ? wp_date( 'Y/m/d H:i', $date->getTimestamp() ) : '',
			'order_items'           => implode( '، ', $items ),
			'order_items_count'     => (string) $order->get_item_count(),
			'order_url'             => $order->get_view_order_url(),
			'order_pay_url'         => $order->get_checkout_payment_url(),
			'order_admin_url'       => $order->get_edit_order_url(),
			'payment_method'        => $order->get_payment_method_title(),
			'shipping_method'       => $order->get_shipping_method(),
			'transaction_id'        => $order->get_transaction_id(),
			'customer_note'         => $order->get_customer_note(),
			'billing_email'         => $order->get_billing_email(),
		);

		return array_merge(
			self::site_vars(),
			$vars,
			self::address_vars( $order, 'billing' ),
			self::address_vars( $order, 'shipping' ),
			$extra
		);
	}

	/**
	 * متغیرهای آدرس صورتحساب یا ارسال: billing_* یا shipping_*.
	 *
	 * {prefix}_full_address = «استان، شهر، آدرس» (بخش‌های خالی حذف می‌شوند).
	 *
	 * @param WC_Order $order  سفارش.
	 * @param string   $prefix billing|shipping.
	 * @return array<string, string>
	 */
	private static function address_vars( WC_Order $order, $prefix ) {
		$get = function ( $field ) use ( $order, $prefix ) {
			return (string) $order->{"get_{$prefix}_{$field}"}();
		};

		$country   = $get( 'country' );
		$countries = WC()->countries->get_countries();
		$state     = self::state_name( $country, $get( 'state' ) );
		$city      = $get( 'city' );
		$address   = trim( $get( 'address_1' ) . ' ' . $get( 'address_2' ) );

		return array(
			"{$prefix}_first_name"   => $get( 'first_name' ),
			"{$prefix}_last_name"    => $get( 'last_name' ),
			"{$prefix}_full_name"    => trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) ),
			"{$prefix}_company"      => $get( 'company' ),
			"{$prefix}_phone"        => $get( 'phone' ),
			"{$prefix}_country"      => isset( $countries[ $country ] ) ? html_entity_decode( $countries[ $country ], ENT_QUOTES ) : $country,
			"{$prefix}_state"        => $state,
			"{$prefix}_city"         => $city,
			"{$prefix}_address"      => $address,
			"{$prefix}_postcode"     => $get( 'postcode' ),
			"{$prefix}_full_address" => implode( '، ', array_filter( array( $state, $city, $address ), 'strlen' ) ),
		);
	}

	/**
	 * نام استان از روی کد آن (ووکامرس استان را به‌صورت کد ذخیره می‌کند، مثلاً THR).
	 * اگر نامی پیدا نشود، همان مقدار خام برگردانده می‌شود.
	 *
	 * @param string $country کد کشور.
	 * @param string $state   کد یا نام استان.
	 * @return string
	 */
	private static function state_name( $country, $state ) {
		$states = WC()->countries->get_states( $country );
		if ( is_array( $states ) && isset( $states[ $state ] ) ) {
			return html_entity_decode( $states[ $state ], ENT_QUOTES );
		}
		return (string) $state;
	}

	/**
	 * متغیرهای محصول.
	 *
	 * @param WC_Product $product محصول.
	 * @return array<string, string>
	 */
	public static function product_vars( WC_Product $product ) {
		$stock = $product->get_stock_quantity();

		$vars = array(
			'product_id'     => (string) $product->get_id(),
			'product_name'   => $product->get_name(),
			'product_sku'    => $product->get_sku(),
			'product_price'  => html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES ),
			'stock_quantity' => null === $stock ? '' : (string) $stock,
		);

		return array_merge( self::site_vars(), $vars );
	}

	/**
	 * متغیرهای کاربر.
	 *
	 * @param WP_User $user کاربر.
	 * @return array<string, string>
	 */
	public static function user_vars( WP_User $user ) {
		$mobile = get_user_meta( $user->ID, 'billing_phone', true );

		$vars = array(
			'user_id'         => (string) $user->ID,
			'user_login'      => $user->user_login,
			'user_email'      => $user->user_email,
			'user_first_name' => $user->first_name,
			'user_last_name'  => $user->last_name,
			'user_full_name'  => trim( $user->first_name . ' ' . $user->last_name ),
			'user_mobile'     => $mobile ? $mobile : '',
			'register_date'   => wp_date( 'Y/m/d H:i' ),
		);

		return array_merge( self::site_vars(), $vars );
	}

	/**
	 * جایگزینی متغیرها در متن.
	 *
	 * @param string $text متن حاوی {placeholder}.
	 * @param array  $vars متغیرها.
	 * @return string
	 */
	public static function replace( $text, array $vars ) {
		$search  = array();
		$replace = array();
		foreach ( $vars as $key => $value ) {
			$search[]  = '{' . $key . '}';
			$replace[] = (string) $value;
		}
		return str_replace( $search, $replace, (string) $text );
	}

	/**
	 * تبدیل خطوط «متغیر پترن = مقدار» به آرایه attribute ها با جایگزینی متغیرها.
	 *
	 * مثال ورودی:
	 *   name = {billing_full_name}
	 *   order = {order_number}
	 *
	 * @param string $raw  متن خام تنظیمات.
	 * @param array  $vars متغیرهای رویداد.
	 * @return array<string, string>
	 */
	public static function parse_pattern_attributes( $raw, array $vars ) {
		$attributes = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $key, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' !== $key ) {
				$attributes[ $key ] = self::replace( $value, $vars );
			}
		}

		return $attributes;
	}

	/**
	 * لیست متغیرهای هر زمینه برای نمایش راهنما در تنظیمات.
	 *
	 * @param string $context order|product|user.
	 * @return string[]
	 */
	public static function hints( $context ) {
		$common = array( 'site_name', 'site_url' );

		$map = array(
			'order'   => array(
				'order_id', 'order_number', 'order_status', 'order_status_prev', 'order_total', 'order_total_plain',
				'order_subtotal', 'order_shipping_total', 'order_discount_total', 'coupon_codes',
				'order_date', 'order_items', 'order_items_count', 'order_url', 'order_pay_url', 'order_admin_url',
				'payment_method', 'shipping_method', 'transaction_id', 'customer_note', 'billing_email',
			),
			'product' => array( 'product_id', 'product_name', 'product_sku', 'product_price', 'stock_quantity' ),
			'user'    => array( 'user_id', 'user_login', 'user_email', 'user_first_name', 'user_last_name', 'user_full_name', 'user_mobile', 'register_date' ),
		);

		if ( 'order' === $context ) {
			$fields = array( 'full_address', 'first_name', 'last_name', 'full_name', 'company', 'phone', 'country', 'state', 'city', 'address', 'postcode' );
			foreach ( array( 'billing', 'shipping' ) as $prefix ) {
				foreach ( $fields as $field ) {
					$map['order'][] = $prefix . '_' . $field;
				}
			}
		}

		return array_merge( isset( $map[ $context ] ) ? $map[ $context ] : array(), $common );
	}
}
