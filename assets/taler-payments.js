/* global TalerPayments, QRCode */
(function () {
  'use strict';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function setText(el, text) {
    if (!el) return;
    el.textContent = text == null ? '' : String(text);
  }

  function openModal(modal) {
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('taler-modal-open');
    document.body.classList.add('taler-modal-open');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('taler-modal-open');
    document.body.classList.remove('taler-modal-open');
  }

  function clearQr(qrBox) {
    if (!qrBox) return;
    while (qrBox.firstChild) qrBox.removeChild(qrBox.firstChild);
  }

  

  /**
   * Replace the pay button node after setting taler:// href.
   *
   * The Taler Wallet extension's "hijack" support observes DOM additions
   * and registers click handlers for <a href^="taler">. It does NOT observe
   * attribute changes, so updating an existing link's href can be missed.
   */
  function replacePayBtnWithHref(payBtn, href) {
    if (!payBtn || !payBtn.parentNode) return payBtn;
    var clone = payBtn.cloneNode(true);
    clone.href = href;
    clone.removeAttribute('aria-disabled');
    // IMPORTANT: the wallet extension's mutation hook calls `overrideAllAnchor(addedNode)`
    // and that function only does `addedNode.querySelectorAll('a[href^=taler]')`.
    // If the added node IS the <a> itself, it won't be matched. We therefore insert a
    // wrapper element that *contains* the <a> as a descendant.
    var wrapper = document.createElement('span');
    wrapper.setAttribute('data-taler-pay-btn-wrapper', '1');
    // Avoid layout changes: wrapper should not introduce a box.
    wrapper.style.display = 'contents';
    wrapper.appendChild(clone);

    payBtn.parentNode.replaceChild(wrapper, payBtn);
    return clone;
  }

  async function createOrder(amount, summary) {
    var body = new URLSearchParams();
    body.set('action', 'taler_wp_create_order');
    body.set('_ajax_nonce', TalerPayments.nonce);
    body.set('amount', amount);
    body.set('summary', summary);

    var res = await fetch(TalerPayments.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });

    // WP returns JSON but may use 200/4xx/5xx depending on handler.
    var json;
    try {
      json = await res.json();
    } catch (e) {
      throw new Error(TalerPayments.i18n.errorGeneric);
    }

    if (!json || !json.success) {
      var msg = (json && json.data && json.data.message) ? json.data.message : TalerPayments.i18n.errorGeneric;
      throw new Error(msg);
    }

    return json.data.taler_pay_uri;
  }

  function init() {
    if (!window.TalerPayments) return;

    var modal = $('#taler-payments-modal');
    if (!modal) return;

    var amountEl = $('#taler-modal-amount');
    var summaryEl = $('#taler-modal-summary');
    var statusEl = $('#taler-modal-status');
    var errorEl = $('#taler-modal-error');
    var payBtn = $('#taler-modal-pay-btn');
    var walletHelp = $('#taler-modal-wallet-help');
    var walletLink = $('#taler-modal-wallet-link');
    var qrHelp = $('#taler-modal-qr-help');
    var qrBox = $('#taler-modal-qr');

    // i18n text.
    setText($('#taler-modal-title'), TalerPayments.i18n.title);
    setText(payBtn, TalerPayments.i18n.payInBrowser);
    if (walletHelp) {
      walletHelp.innerHTML =
        escapeHtml(TalerPayments.i18n.walletInstallText) +
        ' ' +
        '<a id="taler-modal-wallet-link" href="' +
        escapeHtml(TalerPayments.walletInfoUrl) +
        '" target="_blank" rel="noreferrer">' +
        escapeHtml(TalerPayments.i18n.walletInstallLinkText) +
        '</a>';
      walletLink = $('#taler-modal-wallet-link');
    }
    if (walletLink) walletLink.href = TalerPayments.walletInfoUrl;
    setText(qrHelp, TalerPayments.i18n.qrHelp);

    // Close handlers.
    modal.addEventListener('click', function (e) {
      var t = e.target;

      // Don't allow clicking the pay link while disabled.
      var payLink = t && t.closest ? t.closest('#taler-modal-pay-btn') : null;
      if (payLink && payLink.getAttribute && payLink.getAttribute('aria-disabled') === 'true') {
        e.preventDefault();
        return;
      }

      if (t && t.hasAttribute && t.hasAttribute('data-taler-close')) {
        e.preventDefault();
        closeModal(modal);
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
        closeModal(modal);
      }
    });

    // Open handlers (event delegation so it works with multiple shortcodes).
    document.addEventListener('click', function (e) {
      var link = e.target && e.target.closest ? e.target.closest('.taler-pay-button') : null;
      if (!link) return;

      e.preventDefault();

      var amount = link.getAttribute('data-taler-amount') || 'KUDOS:1.00';
      var summary = link.getAttribute('data-taler-summary') || 'Donation';

      setText(amountEl, amount);
      setText(summaryEl, summary);
      setText(statusEl, TalerPayments.i18n.creatingOrder);
      setText(errorEl, '');
      if (payBtn) payBtn.setAttribute('aria-disabled', 'true');
      if (payBtn) payBtn.href = '#';
      clearQr(qrBox);

      openModal(modal);

      createOrder(amount, summary)
        .then(function (payUri) {
          setText(statusEl, '');
          if (payBtn) {
            payBtn = replacePayBtnWithHref(payBtn, payUri);
          }

          // Always clear right before rendering to avoid duplicates on rapid clicks (rapid clicks are secured /cannot appear for now/, but just in case ...)
          clearQr(qrBox);

          // QRCode generator might not be available (optimizer/script issues).
          if (typeof window.QRCode !== 'function') {
            var msg = document.createElement('div');
            msg.className = 'taler-modal__errorInline';
            msg.textContent = 'QR generator not available.';
            if (qrBox) qrBox.appendChild(msg);
            return;
          }

          var cl = window.QRCode.CorrectLevel;
          var correctLevel = cl && typeof cl.M === 'number' ? cl.M : undefined;

          new window.QRCode(qrBox, {
            text: payUri,
            width: 220,
            height: 220,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: correctLevel
          });
          
        })
        .catch(function (err) {
          setText(statusEl, '');
          setText(errorEl, err && err.message ? err.message : TalerPayments.i18n.errorGeneric);
          clearQr(qrBox);
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

