<?php
/**
 * Hub trigger button (header / shortcode / floating fallback).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

$count    = ( WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
$floating = ! empty( $sch_hub_trigger_floating );
$classes  = array( 'sch-hub-trigger' );
$classes[] = $floating ? 'sch-hub-trigger--floating' : 'sch-hub-trigger--header';
?>
<button
	type="button"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	aria-expanded="false"
	aria-controls="sch-hub-panel"
	aria-label="<?php esc_attr_e( 'Client hub', 'single-client-hub' ); ?>"
	id="sch-hub-trigger"
>
	<span class="sch-hub-trigger__icon" aria-hidden="true">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 7h16l-1.2 11.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8L4 7Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 7V5.5A3.5 3.5 0 0 1 11.5 2h1A3.5 3.5 0 0 1 16 5.5V7" stroke="currentColor" stroke-width="1.7"/></svg>
	</span>
	<span class="sch-hub-badge" id="sch-hub-badge" <?php echo $count ? '' : 'hidden'; ?>>
		<?php echo esc_html( (string) $count ); ?>
	</span>
</button>
