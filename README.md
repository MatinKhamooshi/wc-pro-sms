# پیامک پرو ووکامرس — WC Pro SMS

## فارسی

ارسال پیامک اطلاع‌رسانی به مدیر و مشتری در رویدادهای ووکامرس (سفارش، تغییر وضعیت، موجودی، ثبت‌نام کاربر) با پشتیبانی از پیامک ساده و پترن — درگاه ایران‌پیامک.

- پیامک مدیر و مشتری برای هر رویداد، با انتخاب متن ساده یا پترن
- متغیرهای پویا مثل `{order_number}`، `{billing_full_name}`، `{billing_state}`، `{shipping_state}`، `{product_name}`
- بررسی اتصال و موجودی، ارسال تست، گزارش ۵۰ ارسال آخر
- سازگار با HPOS؛ قابل توسعه برای درگاه‌های دیگر (فیلتر `wcps_gateways`)

**نصب:** پوشه را در `wp-content/plugins` بگذارید، فعال کنید و از «ووکامرس ← پیامک پرو» تنظیم کنید.

## English

WooCommerce SMS notifications for admins and customers (new order, status change, low/out of stock, new user) with plain-text or pattern sending via the IranPayamak gateway.

- Per-event admin and customer SMS, plain text or pattern
- Dynamic variables such as `{order_number}`, `{billing_full_name}`, `{billing_state}`, `{shipping_state}`, `{product_name}`
- Connection/balance check, test send, log of the last 50 messages
- HPOS compatible; extensible gateways via the `wcps_gateways` filter

**Install:** drop the folder into `wp-content/plugins`, activate it, then configure under WooCommerce → Pro SMS.

## Changelog

- **1.2.0** — New order variable `{shipping_state}` (shipping state/province name) / متغیر جدید `{shipping_state}` (نام استان ارسال).
- **1.1.0** — New order variable `{billing_state}` (state/province name, e.g. «تهران») / متغیر جدید `{billing_state}` (نام استان).
- **1.0.0** — Initial release / نسخه اولیه.
