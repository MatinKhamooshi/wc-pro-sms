<?php
/**
 * Plugin Name: پیامک پرو ووکامرس
 * Description: ارسال پیامک اطلاع‌رسانی به مدیر و مشتری در رویدادهای ووکامرس (سفارش، موجودی، کاربر و...) با پشتیبانی کامل از ارسال با پترن — درگاه ایران‌پیامک.
 * Version:     1.2.0
 * Author:      Matin Khamooshi
 * Author URI:  https://matinkhamooshi.ir/
 * Text Domain: wc-pro-sms
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 *
 * @package WC_Pro_SMS
 */

defined('ABSPATH') || exit;

define('WCPS_VERSION', '1.2.0');
define('WCPS_FILE', __FILE__);
define('WCPS_DIR', plugin_dir_path(__FILE__));
define('WCPS_URL', plugin_dir_url(__FILE__));

// سازگاری با HPOS (جداول سفارش سفارشی ووکامرس).
add_action(
	'before_woocommerce_init',
	function () {
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if (!class_exists('WooCommerce')) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>افزونه «پیامک پرو ووکامرس» برای کار کردن به ووکامرس فعال نیاز دارد.</p></div>';
				}
			);
			return;
		}

		require_once WCPS_DIR . 'includes/class-wcps-logger.php';
		require_once WCPS_DIR . 'includes/class-wcps-variables.php';
		require_once WCPS_DIR . 'includes/class-wcps-events.php';
		require_once WCPS_DIR . 'includes/gateways/class-wcps-gateway.php';
		require_once WCPS_DIR . 'includes/gateways/class-wcps-gateway-iranpayamak.php';
		require_once WCPS_DIR . 'includes/class-wcps-gateway-manager.php';
		require_once WCPS_DIR . 'includes/class-wcps-notifications.php';

		new WCPS_Notifications();

		if (is_admin()) {
			require_once WCPS_DIR . 'includes/class-wcps-admin.php';
			require_once WCPS_DIR . 'includes/class-wcps-ajax.php';
			new WCPS_Admin();
			new WCPS_Ajax();
		}
	}
);
