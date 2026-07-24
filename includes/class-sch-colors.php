<?php
/**
 * Hub brand colors (options + CSS variables).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Colors
 */
class SCH_Colors {

	/**
	 * Default palette.
	 *
	 * @return array{main:string,accent:string,text:string}
	 */
	public static function defaults() {
		return array(
			'main'   => '#1c1917',
			'accent' => '#0d9488',
			'text'   => '#fafaf9',
		);
	}

	/**
	 * Saved colors merged with defaults.
	 *
	 * @return array{main:string,accent:string,text:string}
	 */
	public static function get() {
		$defaults = self::defaults();

		return array(
			'main'   => self::sanitize_hex( get_option( 'sch_color_main', $defaults['main'] ), $defaults['main'] ),
			'accent' => self::sanitize_hex( get_option( 'sch_color_accent', $defaults['accent'] ), $defaults['accent'] ),
			'text'   => self::sanitize_hex( get_option( 'sch_color_text', $defaults['text'] ), $defaults['text'] ),
		);
	}

	/**
	 * @param mixed  $value    Raw.
	 * @param string $fallback Fallback hex.
	 * @return string
	 */
	public static function sanitize_hex( $value, $fallback = '#000000' ) {
		$hex = sanitize_hex_color( (string) $value );
		return $hex ? $hex : $fallback;
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * @param mixed  $value  Raw.
	 * @param string $option Option name.
	 * @return string
	 */
	public static function sanitize_option( $value, $option = '' ) {
		$defaults = self::defaults();
		$map      = array(
			'sch_color_main'   => 'main',
			'sch_color_accent' => 'accent',
			'sch_color_text'   => 'text',
		);
		$fallback = $defaults[ $map[ $option ] ?? 'main' ] ?? '#000000';

		return self::sanitize_hex( $value, $fallback );
	}

	/**
	 * Inline CSS overriding hub variables.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$c = self::get();

		return sprintf(
			'.sch-hub-root{--sch-bg:%1$s;--sch-surface:color-mix(in srgb,%1$s 88%%,#ffffff 12%%);--sch-ink:%2$s;--sch-accent:%3$s;}',
			$c['main'],
			$c['text'],
			$c['accent']
		);
	}
}
