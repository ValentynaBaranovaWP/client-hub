<?php
/**
 * Mask sensitive fields in payment payloads.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Payment_Masker
 */
class SCH_Payment_Masker {

	/**
	 * Keys (case-insensitive) to mask.
	 *
	 * @var string[]
	 */
	private static $sensitive_keys = array(
		'card_number',
		'cardnumber',
		'pan',
		'cvv',
		'cvc',
		'cvv2',
		'password',
		'secret',
		'api_key',
		'apikey',
		'token',
		'authorization',
		'iban',
		'account_number',
		'client_secret',
		'webhook_secret',
		'pin',
	);

	/**
	 * Mask payload recursively.
	 *
	 * @param mixed $data Raw data (array|string|object).
	 * @return mixed
	 */
	public static function mask( $data ) {
		if ( ! SCH_Plugin::should_mask() ) {
			return $data;
		}

		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return wp_json_encode( self::mask_array( $decoded ) );
			}
			return self::mask_string_patterns( $data );
		}

		if ( is_array( $data ) ) {
			return self::mask_array( $data );
		}

		if ( is_object( $data ) ) {
			return self::mask_array( (array) $data );
		}

		return $data;
	}

	/**
	 * Mask array keys.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function mask_array( array $data ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			$key_l = strtolower( (string) $key );
			if ( self::is_sensitive_key( $key_l ) ) {
				$out[ $key ] = self::mask_value( $value, $key_l );
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = self::mask_array( $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * @param string $key Lowercase key.
	 * @return bool
	 */
	private static function is_sensitive_key( $key ) {
		foreach ( self::$sensitive_keys as $sensitive ) {
			if ( false !== strpos( $key, $sensitive ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed  $value Value.
	 * @param string $key   Key.
	 * @return string
	 */
	private static function mask_value( $value, $key ) {
		$str = (string) $value;
		if ( in_array( $key, array( 'iban', 'account_number' ), true ) || false !== strpos( $key, 'iban' ) ) {
			$len = strlen( $str );
			if ( $len <= 8 ) {
				return str_repeat( '*', $len );
			}
			return substr( $str, 0, 4 ) . str_repeat( '*', max( 0, $len - 8 ) ) . substr( $str, -4 );
		}
		if ( false !== strpos( $key, 'card' ) || 'pan' === $key ) {
			$digits = preg_replace( '/\D/', '', $str );
			$last4  = substr( $digits, -4 );
			return '**** **** **** ' . ( $last4 ? $last4 : '****' );
		}
		if ( strlen( $str ) <= 4 ) {
			return '****';
		}
		return substr( $str, 0, 2 ) . str_repeat( '*', strlen( $str ) - 4 ) . substr( $str, -2 );
	}

	/**
	 * Mask common patterns in free-form strings.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function mask_string_patterns( $text ) {
		$text = preg_replace( '/\b(\d{4}[\s-]?){3}\d{4}\b/', '**** **** **** ****', $text );
		$text = preg_replace( '/\bUA\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{3}\b/i', 'UA** **** **** **** **** **** ***', $text );
		return $text;
	}
}
