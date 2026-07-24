/**
 * Single Client Hub — frontend interactions
 */
(function () {
  'use strict';

  if (typeof schHub === 'undefined') return;

  var root = document.getElementById('sch-hub');
  if (!root) return;

  var trigger = document.getElementById('sch-hub-trigger');
  var panel = document.getElementById('sch-hub-panel');
  var backdrop = document.getElementById('sch-hub-backdrop');
  var closeBtn = document.getElementById('sch-hub-close');
  var badge = document.getElementById('sch-hub-badge');
  var activeTab = 'cart';

  if (!trigger || !panel || !backdrop || !closeBtn) return;

  function positionPanel() {
    if (root.classList.contains('sch-hub-root--floating')) {
      panel.style.top = '';
      panel.style.right = '';
      return;
    }
    var rect = trigger.getBoundingClientRect();
    var gap = 10;
    var top = Math.round(rect.bottom + gap);
    var right = Math.round(Math.max(8, window.innerWidth - rect.right));
    var maxTop = window.innerHeight - 80;
    if (top > maxTop) {
      top = Math.max(8, Math.round(rect.top - gap));
    }
    panel.style.top = top + 'px';
    panel.style.right = right + 'px';
  }

  function openHub() {
    positionPanel();
    panel.hidden = false;
    backdrop.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    root.dataset.open = '1';
    loadTab(activeTab);
  }

  function closeHub() {
    panel.hidden = true;
    backdrop.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    root.dataset.open = '0';
  }

  function toggleHub() {
    if (root.dataset.open === '1') closeHub();
    else openHub();
  }

  trigger.addEventListener('click', toggleHub);
  closeBtn.addEventListener('click', closeHub);
  backdrop.addEventListener('click', closeHub);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.dataset.open === '1') closeHub();
  });
  window.addEventListener('resize', function () {
    if (root.dataset.open === '1') positionPanel();
  });

  function refreshBadgeFromCart() {
    api('hub/cart')
      .then(function (data) {
        updateBadge(data && typeof data.count !== 'undefined' ? data.count : 0);
      })
      .catch(function () {});
  }

  document.body.addEventListener('wc-blocks_added_to_cart', refreshBadgeFromCart);
  document.body.addEventListener('wc-blocks_removed_from_cart', refreshBadgeFromCart);
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on(
      'added_to_cart removed_from_cart updated_cart_totals updated_wc_div wc_fragments_refreshed',
      function () {
        badge = document.getElementById('sch-hub-badge');
        if (badge && badge.textContent) {
          var n = parseInt(badge.textContent, 10);
          if (!isNaN(n)) updateBadge(n);
        } else {
          refreshBadgeFromCart();
        }
      }
    );
  }

  root.querySelectorAll('.sch-hub-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      activeTab = tab.getAttribute('data-tab');
      root.querySelectorAll('.sch-hub-tab').forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      root.querySelectorAll('.sch-hub-pane').forEach(function (pane) {
        pane.hidden = pane.getAttribute('data-pane') !== activeTab;
      });
      loadTab(activeTab);
    });
  });

  function api(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['X-WP-Nonce'] = schHub.nonce;
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    options.credentials = options.credentials || 'same-origin';
    return fetch(schHub.restUrl + path, options).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          var msg = (data && data.message) || schHub.i18n.error;
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function loadTab(tab) {
    var pane = root.querySelector('.sch-hub-pane[data-pane="' + tab + '"]');
    if (!pane) return;
    pane.innerHTML = '<div class="sch-hub-loading">' + schHub.i18n.loading + '</div>';

    if (tab === 'cart') {
      api('hub/cart')
        .then(renderCart)
        .catch(function (err) {
          pane.innerHTML = '<p class="sch-msg sch-msg--error">' + escapeHtml(err.message) + '</p>';
        });
    } else {
      if (!schHub.isLoggedIn) {
        pane.innerHTML =
          '<p class="sch-empty">' +
          schHub.i18n.login +
          '</p><p><a class="sch-btn" href="' +
          schHub.accountUrl +
          '">Login</a></p>';
        return;
      }
      api('hub/account')
        .then(renderAccount)
        .catch(function (err) {
          pane.innerHTML = '<p class="sch-msg sch-msg--error">' + escapeHtml(err.message) + '</p>';
        });
    }
  }

  function renderCart(data) {
    var pane = root.querySelector('.sch-hub-pane[data-pane="cart"]');
    updateBadge(data.count || 0);

    if (!data.items || !data.items.length) {
      pane.innerHTML = '<p class="sch-empty">' + schHub.i18n.emptyCart + '</p>';
      appendCouponBlock(pane, data);
      return;
    }

    var html = data.items
      .map(function (item) {
        return (
          '<div class="sch-cart-item">' +
          '<button type="button" class="sch-cart-item__remove" data-remove-item="' +
          escapeHtml(item.key) +
          '" aria-label="' +
          escapeHtml(schHub.i18n.removeItem) +
          '">×</button>' +
          '<div class="sch-cart-item__body"><div class="sch-cart-item__name">' +
          escapeHtml(item.name) +
          '</div><div class="sch-cart-item__meta">×' +
          item.quantity +
          '</div></div>' +
          '<div class="sch-cart-item__price">' +
          item.total +
          '</div></div>'
        );
      })
      .join('');

    html +=
      '<div class="sch-cart-total"><span>' +
      schHub.i18n.total +
      '</span><span>' +
      data.total +
      '</span></div>';

    if (data.coupons_applied && data.coupons_applied.length) {
      html +=
        '<div class="sch-coupons"><div class="sch-muted">' +
        schHub.i18n.applied +
        '</div><div class="sch-coupons__chips">' +
        data.coupons_applied
          .map(function (c) {
            return (
              '<button type="button" class="sch-chip" data-remove-coupon="' +
              escapeHtml(c.code) +
              '">' +
              escapeHtml(c.code) +
              ' (−' +
              stripTags(c.discount) +
              ')</button>'
            );
          })
          .join('') +
        '</div></div>';
    }

    html +=
      '<div class="sch-hub-actions"><a href="' +
      data.checkout_url +
      '">' +
      schHub.i18n.checkout +
      '</a><a class="secondary" href="' +
      data.cart_url +
      '">' +
      schHub.i18n.viewCart +
      '</a></div>';

    pane.innerHTML = html;
    appendCouponBlock(pane, data);

    pane.querySelectorAll('[data-remove-coupon]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        api('hub/coupon/remove', { method: 'POST', body: { code: btn.getAttribute('data-remove-coupon') } })
          .then(renderCart)
          .catch(showPaneError);
      });
    });

    pane.querySelectorAll('[data-remove-item]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.disabled = true;
        api('hub/cart/remove', { method: 'POST', body: { cart_item_key: btn.getAttribute('data-remove-item') } })
          .then(function (cartData) {
            renderCart(cartData);
            if (typeof jQuery !== 'undefined') {
              jQuery(document.body).trigger('wc_fragment_refresh');
            }
          })
          .catch(function (err) {
            btn.disabled = false;
            showPaneError(err);
          });
      });
    });
  }

  function appendCouponBlock(pane, data) {
    var wrap = document.createElement('div');
    wrap.className = 'sch-coupons';
    var chips = '';
    if (data.reward_coupons && data.reward_coupons.length) {
      chips =
        '<div class="sch-muted">' +
        schHub.i18n.coupons +
        '</div><div class="sch-coupons__chips">' +
        data.reward_coupons
          .map(function (c) {
            return (
              '<button type="button" class="sch-chip" data-apply-coupon="' +
              escapeHtml(c.code) +
              '">' +
              escapeHtml(c.code) +
              '</button>'
            );
          })
          .join('') +
        '</div>';
    }
    wrap.innerHTML =
      chips +
      '<form class="sch-coupon-form" id="sch-coupon-form">' +
      '<input type="text" name="code" placeholder="' +
      schHub.i18n.coupon +
      '" required />' +
      '<button type="submit">' +
      schHub.i18n.apply +
      '</button></form><div class="sch-msg" id="sch-coupon-msg" hidden></div>';
    pane.appendChild(wrap);

    wrap.querySelectorAll('[data-apply-coupon]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyCoupon(btn.getAttribute('data-apply-coupon'));
      });
    });

    wrap.querySelector('#sch-coupon-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var code = wrap.querySelector('input[name="code"]').value.trim();
      applyCoupon(code);
    });
  }

  function applyCoupon(code) {
    var msg = document.getElementById('sch-coupon-msg');
    api('hub/coupon', { method: 'POST', body: { code: code } })
      .then(function (data) {
        if (msg) {
          msg.hidden = false;
          msg.className = 'sch-msg sch-msg--ok';
          msg.textContent = 'OK';
        }
        renderCart(data);
      })
      .catch(function (err) {
        if (msg) {
          msg.hidden = false;
          msg.className = 'sch-msg sch-msg--error';
          msg.textContent = err.message;
        }
      });
  }

  function renderAccount(data) {
    var pane = root.querySelector('.sch-hub-pane[data-pane="account"]');
    var html = '';

    if (data.subscriptions && data.subscriptions.length) {
      html += '<h3 class="sch-muted" style="margin:0 0 .5rem">' + 'Subscriptions' + '</h3>';
      html += data.subscriptions
        .map(function (s) {
          var actions = '';
          if (s.status === 'active') {
            actions =
              '<button type="button" class="sch-btn sch-btn--ghost" data-sub-action="pause" data-id="' +
              s.id +
              '">' +
              schHub.i18n.pause +
              '</button>' +
              '<button type="button" class="sch-btn sch-btn--danger" data-sub-action="cancel" data-id="' +
              s.id +
              '">' +
              schHub.i18n.cancel +
              '</button>';
          } else if (s.status === 'paused') {
            actions =
              '<button type="button" class="sch-btn" data-sub-action="resume" data-id="' +
              s.id +
              '">' +
              schHub.i18n.resume +
              '</button>';
          }
          return (
            '<div class="sch-sub-row"><div class="sch-sub-row__top"><span>#' +
            s.id +
            ' · ' +
            s.amount +
            '</span><span class="sch-status">' +
            escapeHtml(s.status_label) +
            '</span></div><div class="sch-muted">' +
            schHub.i18n.nextCharge +
            ': ' +
            escapeHtml(s.next_payment) +
            '</div><div class="sch-sub-row__actions">' +
            actions +
            '</div></div>'
          );
        })
        .join('');
    }

    if (!data.orders || !data.orders.length) {
      html += '<p class="sch-empty">' + schHub.i18n.noOrders + '</p>';
    } else {
      html += data.orders
        .map(function (o) {
          var btns =
            '<button type="button" class="sch-btn sch-btn--ghost" data-reorder="' +
            o.id +
            '">' +
            schHub.i18n.reorder +
            '</button>';
          if (o.can_subscribe) {
            btns +=
              '<button type="button" class="sch-btn" data-subscribe="' +
              o.id +
              '">' +
              schHub.i18n.subscribe +
              '</button>';
          }
          return (
            '<div class="sch-order-row"><div class="sch-order-row__top"><a href="' +
            o.view_url +
            '">#' +
            escapeHtml(o.number) +
            '</a><span class="sch-status">' +
            escapeHtml(o.status_label) +
            '</span></div><div class="sch-muted">' +
            escapeHtml(o.date) +
            ' · ' +
            o.total +
            '</div><div class="sch-order-row__actions">' +
            btns +
            '</div></div>'
          );
        })
        .join('');
    }

    html +=
      '<div class="sch-hub-actions" style="margin-top:1rem"><a class="secondary" href="' +
      data.account_url +
      '">My Account</a></div>';
    pane.innerHTML = html;

    pane.querySelectorAll('[data-reorder]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        api('hub/reorder', { method: 'POST', body: { order_id: btn.getAttribute('data-reorder') } })
          .then(function (res) {
            window.location.href = res.redirect || schHub.cartUrl;
          })
          .catch(showPaneError);
      });
    });

    pane.querySelectorAll('[data-subscribe]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        api('hub/subscribe', { method: 'POST', body: { order_id: btn.getAttribute('data-subscribe') } })
          .then(function (res) {
            window.location.href = res.redirect || schHub.checkoutUrl;
          })
          .catch(showPaneError);
      });
    });

    pane.querySelectorAll('[data-sub-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-sub-action');
        var map = { pause: 'sch_pause_subscription', resume: 'sch_resume_subscription', cancel: 'sch_cancel_subscription' };
        var fd = new FormData();
        fd.append('action', map[action]);
        fd.append('nonce', schHub.ajaxNonce);
        fd.append('subscription_id', btn.getAttribute('data-id'));
        fetch(schHub.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) {
            return r.json();
          })
          .then(function () {
            loadTab('account');
          })
          .catch(showPaneError);
      });
    });
  }

  function showPaneError(err) {
    var pane = root.querySelector('.sch-hub-pane[data-pane="' + activeTab + '"]');
    if (pane) {
      pane.insertAdjacentHTML(
        'afterbegin',
        '<p class="sch-msg sch-msg--error">' + escapeHtml(err.message || schHub.i18n.error) + '</p>'
      );
    }
  }

  function updateBadge(count) {
    if (!badge) return;
    if (count > 0) {
      badge.hidden = false;
      badge.textContent = String(count);
    } else {
      badge.hidden = true;
    }
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function stripTags(html) {
    var d = document.createElement('div');
    d.innerHTML = html || '';
    return d.textContent || d.innerText || '';
  }
})();
