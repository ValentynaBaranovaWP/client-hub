(function ($) {
  'use strict';

  $(function () {
    $('#sch-sim-webhook').on('click', function () {
      var orderId = $('#sch-sim-order').val();
      var gateway = $('#sch-sim-gateway').val();
      var event = $('#sch-sim-event').val();
      var $out = $('#sch-sim-result');

      if (!orderId) {
        $out.text('Enter an Order ID');
        return;
      }

      $.ajax({
        url: schAdmin.restUrl + 'webhook/' + gateway + '/simulate',
        method: 'POST',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', schAdmin.nonce);
        },
        data: {
          order_id: orderId,
          event: event
        }
      })
        .done(function (res) {
          $out.text(JSON.stringify(res, null, 2));
        })
        .fail(function (xhr) {
          $out.text((schAdmin.i18n && schAdmin.i18n.error) + ': ' + xhr.status + ' ' + (xhr.responseText || ''));
        });
    });
  });
})(jQuery);
