/* global jQuery, wcpsAdmin */
( function ( $ ) {
	'use strict';

	// نمایش/مخفی‌کردن فیلدهای ساده/پترن بر اساس روش ارسال.
	$( document ).on( 'change', '.wcps-mode-select', function () {
		var $scope = $( this ).closest( '.wcps-event, .wcps-card' );
		var isPattern = 'pattern' === $( this ).val();
		$scope.find( '.wcps-mode-simple' ).toggle( ! isPattern );
		$scope.find( '.wcps-mode-pattern' ).toggle( isPattern );
	} );

	// هایلایت کارت رویدادهای فعال.
	$( document ).on( 'change', '.wcps-event-toggle input[type=checkbox]', function () {
		$( this ).closest( '.wcps-event' ).toggleClass( 'is-enabled', this.checked );
	} );

	// درج متغیر با کلیک روی چیپ — در آخرین textarea فوکوس‌شده همان کارت.
	$( document ).on( 'click', '.wcps-chip', function () {
		var chip = $( this ).text();
		var $card = $( this ).closest( '.wcps-event, .wcps-card' );
		var $target = $card.data( 'wcpsLastField' );

		if ( ! $target || ! $target.length ) {
			$target = $card.find( 'textarea' ).filter( ':visible' ).first();
		}
		if ( ! $target || ! $target.length ) {
			return;
		}

		var el = $target.get( 0 );
		var start = el.selectionStart || el.value.length;
		var end = el.selectionEnd || el.value.length;
		el.value = el.value.substring( 0, start ) + chip + el.value.substring( end );
		el.selectionStart = el.selectionEnd = start + chip.length;
		el.focus();
	} );

	$( document ).on( 'focus', '.wcps-event textarea, .wcps-card textarea', function () {
		$( this ).closest( '.wcps-event, .wcps-card' ).data( 'wcpsLastField', $( this ) );
	} );

	function setStatus( $el, type, message ) {
		$el.removeClass( 'wcps-status-ok wcps-status-fail wcps-status-loading' )
			.addClass( 'wcps-status-' + type )
			.html( message );
	}

	// بررسی اتصال و موجودی.
	function checkConnection() {
		var $status = $( '#wcps-connection-status' );
		var showBalance = '1' === $status.data( 'show-balance' ).toString();

		setStatus( $status, 'loading', 'در حال بررسی…' );

		$.post( wcpsAdmin.ajaxUrl, {
			action: 'wcps_check_connection',
			nonce: wcpsAdmin.nonce
		} )
			.done( function ( res ) {
				if ( res.success ) {
					var msg = '✔ ' + res.data.message;
					if ( showBalance ) {
						msg += ' — موجودی: ' + res.data.amount + ' ریال (' + res.data.count + ' پیامک)';
					}
					setStatus( $status, 'ok', msg );
				} else {
					setStatus( $status, 'fail', '✖ ' + ( res.data && res.data.message ? res.data.message : 'خطا در بررسی اتصال.' ) );
				}
			} )
			.fail( function () {
				setStatus( $status, 'fail', '✖ خطا در ارتباط با سرور سایت.' );
			} );
	}

	$( document ).on( 'click', '#wcps-check-connection', checkConnection );

	// ارسال پیامک تست.
	$( document ).on( 'click', '#wcps-send-test', function () {
		var $btn = $( this );
		var $result = $( '#wcps-test-result' );

		setStatus( $result, 'loading', 'در حال ارسال…' );
		$btn.prop( 'disabled', true );

		$.post( wcpsAdmin.ajaxUrl, {
			action: 'wcps_send_test',
			nonce: wcpsAdmin.nonce,
			recipient: $( '#wcps-test-recipient' ).val(),
			mode: $( '#wcps-test-mode' ).val(),
			text: $( '#wcps-test-text' ).val(),
			pattern_code: $( '#wcps-test-pattern-code' ).val(),
			pattern_vars: $( '#wcps-test-pattern-vars' ).val()
		} )
			.done( function ( res ) {
				if ( res.success ) {
					setStatus( $result, 'ok', '✔ ' + res.data.message );
				} else {
					setStatus( $result, 'fail', '✖ ' + ( res.data && res.data.message ? res.data.message : 'خطا در ارسال.' ) );
				}
			} )
			.fail( function () {
				setStatus( $result, 'fail', '✖ خطا در ارتباط با سرور سایت.' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	// پاک کردن گزارش‌ها.
	$( document ).on( 'click', '#wcps-clear-logs', function () {
		if ( ! window.confirm( 'همه گزارش‌ها پاک شوند؟' ) ) {
			return;
		}
		$.post( wcpsAdmin.ajaxUrl, {
			action: 'wcps_clear_logs',
			nonce: wcpsAdmin.nonce
		} ).done( function () {
			window.location.reload();
		} );
	} );

	// بررسی خودکار اتصال اگر «نمایش موجودی» فعال و کلید API ذخیره شده باشد.
	$( function () {
		var $status = $( '#wcps-connection-status' );
		if ( $status.length && '1' === $status.data( 'autoload' ).toString() ) {
			checkConnection();
		}
	} );
} )( jQuery );
