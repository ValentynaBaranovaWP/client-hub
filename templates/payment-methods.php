<?php
/**
 * My Account — saved payment methods.
 *
 * @package SingleClientHub
 * @var array $methods
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sch-payment-methods">
	<h2><?php esc_html_e( 'Saved payment methods', 'single-client-hub' ); ?></h2>

	<?php if ( empty( $methods ) ) : ?>
		<p><?php esc_html_e( 'No saved cards yet. Add a demo card or pay via Stripe / Hold.', 'single-client-hub' ); ?></p>
	<?php else : ?>
		<ul class="sch-methods-list">
			<?php foreach ( $methods as $m ) : ?>
				<li>
					<strong><?php echo esc_html( strtoupper( (string) $m['brand'] ) ); ?></strong>
					•••• <?php echo esc_html( (string) $m['last4'] ); ?>
					<?php if ( ! empty( $m['exp_month'] ) ) : ?>
						<span class="sch-muted">(<?php echo esc_html( (string) $m['exp_month'] . '/' . (string) $m['exp_year'] ); ?>)</span>
					<?php endif; ?>
					<?php if ( ! empty( $m['is_default'] ) ) : ?>
						<em><?php esc_html_e( 'default', 'single-client-hub' ); ?></em>
					<?php endif; ?>
					<?php if ( empty( $m['external'] ) ) : ?>
						<button type="button" class="button sch-set-default" data-id="<?php echo esc_attr( (string) $m['id'] ); ?>"><?php esc_html_e( 'Set as default', 'single-client-hub' ); ?></button>
						<button type="button" class="button sch-delete-method" data-id="<?php echo esc_attr( (string) $m['id'] ); ?>"><?php esc_html_e( 'Delete', 'single-client-hub' ); ?></button>
					<?php else : ?>
						<span class="sch-muted">Stripe</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<hr>
	<h3><?php esc_html_e( 'Add demo card (mock tokenization)', 'single-client-hub' ); ?></h3>
	<form id="sch-add-method-form">
		<p>
			<label><?php esc_html_e( 'Brand', 'single-client-hub' ); ?>
				<select name="brand">
					<option value="visa">Visa</option>
					<option value="mastercard">Mastercard</option>
				</select>
			</label>
			<label><?php esc_html_e( 'Last 4', 'single-client-hub' ); ?>
				<input type="text" name="last4" maxlength="4" pattern="[0-9]{4}" value="4242" required>
			</label>
			<label><?php esc_html_e( 'MM', 'single-client-hub' ); ?>
				<input type="number" name="exp_month" min="1" max="12" value="12" required>
			</label>
			<label><?php esc_html_e( 'YYYY', 'single-client-hub' ); ?>
				<input type="number" name="exp_year" min="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 3 ) ); ?>" required>
			</label>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'single-client-hub' ); ?></button>
		</p>
	</form>
</div>
<script>
(function(){
  function post(action, data){
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sch_hub' ) ); ?>');
    Object.keys(data||{}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method:'POST', body:fd, credentials:'same-origin' }).then(function(r){ return r.json(); });
  }
  document.querySelectorAll('.sch-delete-method').forEach(function(btn){
    btn.addEventListener('click', function(){
      post('sch_delete_saved_method', { method_id: btn.getAttribute('data-id') }).then(function(){ location.reload(); });
    });
  });
  document.querySelectorAll('.sch-set-default').forEach(function(btn){
    btn.addEventListener('click', function(){
      post('sch_set_default_method', { method_id: btn.getAttribute('data-id') }).then(function(){ location.reload(); });
    });
  });
  var form = document.getElementById('sch-add-method-form');
  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var fd = new FormData(form);
      post('sch_add_saved_method', {
        brand: fd.get('brand'),
        last4: fd.get('last4'),
        exp_month: fd.get('exp_month'),
        exp_year: fd.get('exp_year')
      }).then(function(){ location.reload(); });
    });
  }
})();
</script>
