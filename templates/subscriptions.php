<?php
/**
 * My Account — subscriptions list.
 *
 * @package SingleClientHub
 * @var array $subs
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sch-subscriptions-account">
	<h2><?php esc_html_e( 'My subscriptions', 'single-client-hub' ); ?></h2>

	<?php if ( empty( $subs ) ) : ?>
		<p><?php esc_html_e( 'No active subscriptions. Check out with the subscription option or click Subscribe on a past order.', 'single-client-hub' ); ?></p>
	<?php else : ?>
		<table class="shop_table shop_table_responsive my_account_orders">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'single-client-hub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'single-client-hub' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'single-client-hub' ); ?></th>
					<th><?php esc_html_e( 'Next charge', 'single-client-hub' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'single-client-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $subs as $sub ) : ?>
					<tr>
						<td>#<?php echo (int) $sub['id']; ?></td>
						<td><?php echo esc_html( SCH_Subscriptions::status_label( $sub['status'] ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $sub['amount'], array( 'currency' => $sub['currency'] ) ) ); ?></td>
						<td><?php echo $sub['next_payment'] ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $sub['next_payment'] ) ) ) : '—'; ?></td>
						<td class="sch-sub-actions" data-id="<?php echo (int) $sub['id']; ?>">
							<?php if ( 'active' === $sub['status'] ) : ?>
								<button type="button" class="button sch-sub-pause"><?php esc_html_e( 'Pause', 'single-client-hub' ); ?></button>
								<button type="button" class="button sch-sub-cancel"><?php esc_html_e( 'Cancel', 'single-client-hub' ); ?></button>
							<?php elseif ( 'paused' === $sub['status'] ) : ?>
								<button type="button" class="button sch-sub-resume"><?php esc_html_e( 'Resume', 'single-client-hub' ); ?></button>
								<button type="button" class="button sch-sub-cancel"><?php esc_html_e( 'Cancel', 'single-client-hub' ); ?></button>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<script>
(function(){
  function post(action, id){
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sch_hub' ) ); ?>');
    fd.append('subscription_id', id);
    return fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
  }
  document.querySelectorAll('.sch-sub-actions').forEach(function(cell){
    var id = cell.getAttribute('data-id');
    var pause = cell.querySelector('.sch-sub-pause');
    var resume = cell.querySelector('.sch-sub-resume');
    var cancel = cell.querySelector('.sch-sub-cancel');
    if (pause) pause.addEventListener('click', function(){ post('sch_pause_subscription', id).then(function(){ location.reload(); }); });
    if (resume) resume.addEventListener('click', function(){ post('sch_resume_subscription', id).then(function(){ location.reload(); }); });
    if (cancel) cancel.addEventListener('click', function(){
      if (confirm('<?php echo esc_js( __( 'Cancel subscription?', 'single-client-hub' ) ); ?>')) {
        post('sch_cancel_subscription', id).then(function(){ location.reload(); });
      }
    });
  });
})();
</script>
