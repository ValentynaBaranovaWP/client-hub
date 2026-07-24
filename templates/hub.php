<?php
/**
 * Hub panel markup (footer). Trigger lives in header / shortcode / floating fallback.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

$portal = empty( $sch_hub_floating );
$root_class = 'sch-hub-root' . ( $portal ? ' sch-hub-root--portal' : ' sch-hub-root--floating' );
?>
<div id="sch-hub" class="<?php echo esc_attr( $root_class ); ?>" data-open="0">
	<?php
	if ( ! $portal ) {
		$sch_hub_trigger_floating = true;
		include SCH_PLUGIN_DIR . 'templates/hub-trigger.php';
	}
	?>

	<div class="sch-hub-panel" id="sch-hub-panel" hidden role="dialog" aria-label="<?php esc_attr_e( 'Client hub', 'single-client-hub' ); ?>">
		<div class="sch-hub-panel__head">
			<div class="sch-hub-tabs" role="tablist">
				<button type="button" class="sch-hub-tab is-active" data-tab="cart" role="tab" aria-selected="true"><?php esc_html_e( 'Cart', 'single-client-hub' ); ?></button>
				<button type="button" class="sch-hub-tab" data-tab="account" role="tab" aria-selected="false"><?php esc_html_e( 'Account', 'single-client-hub' ); ?></button>
			</div>
			<button type="button" class="sch-hub-close" id="sch-hub-close" aria-label="<?php esc_attr_e( 'Close', 'single-client-hub' ); ?>">×</button>
		</div>
		<div class="sch-hub-panel__body">
			<div class="sch-hub-pane" data-pane="cart" role="tabpanel">
				<div class="sch-hub-loading"><?php esc_html_e( 'Loading…', 'single-client-hub' ); ?></div>
			</div>
			<div class="sch-hub-pane" data-pane="account" role="tabpanel" hidden>
				<div class="sch-hub-loading"><?php esc_html_e( 'Loading…', 'single-client-hub' ); ?></div>
			</div>
		</div>
	</div>
	<div class="sch-hub-backdrop" id="sch-hub-backdrop" hidden></div>
</div>
