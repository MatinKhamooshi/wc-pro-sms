<?php
/**
 * حذف داده‌های افزونه هنگام حذف کامل.
 *
 * @package WC_Pro_SMS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wcps_gateway' );
delete_option( 'wcps_admin_sms' );
delete_option( 'wcps_customer_sms' );
delete_option( 'wcps_logs' );
