<?php
/** M-Pesa payment (step 3). STK push is simulated with an on-screen phone. */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$order = one("SELECT * FROM orders WHERE uuid = ? AND user_id = ?", [(string)($_GET['order'] ?? ''), $me['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect('orders.php'); }
if ($order['status'] !== 'pending_payment') redirect('order.php?id=' . $order['uuid']);
$snap = json_decode($order['listing_snapshot'], true);
$phone = preg_replace('/^\+254/', '0', (string)($order['contact_phone'] ?: $me['phone']));
$simulate = (bool)config('payments.mpesa.simulate', true);

$pageTitle = 'Pay with M-Pesa';
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-5xl px-4 sm:px-6 pt-8">
  <?php component('checkout_steps', ['step' => 3]); ?>
  <div class="grid md:grid-cols-[1fr_auto] gap-8 items-start">
    <section class="vk-panel p-6 sm:p-8">
      <div class="flex items-center gap-4">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-[#00A651] text-white font-black text-xs leading-none text-center">M<br>PESA</span>
        <div><h1 class="font-display text-2xl font-bold">Pay with M-Pesa</h1><p class="text-muted text-sm">Order <b class="text-ink"><?= e($order['order_number']) ?></b> · <?= e(excerpt($snap['title'], 40)) ?></p></div>
      </div>
      <div class="mt-6 rounded-2xl bg-surface2 p-4 flex items-center justify-between">
        <span class="text-muted font-semibold">Amount</span><span class="font-display text-3xl font-bold text-accent"><?= e(money($order['amount_due'], $order['currency_code'])) ?></span>
      </div>

      <!-- step A: phone -->
      <form id="stk-form" class="mt-6 space-y-4" novalidate>
        <div data-field-wrap>
          <label class="vk-label" for="phone">M-Pesa phone number</label>
          <div class="flex gap-2"><span class="vk-input !w-auto flex items-center gap-2 font-semibold">🇰🇪</span>
            <input id="phone" name="phone" value="<?= e($phone) ?>" inputmode="tel" class="vk-input text-lg font-bold tracking-wide" placeholder="07XX XXX XXX"></div>
          <p class="mt-1 text-xs text-muted">We'll send a payment request to this number. Enter your M-Pesa PIN on your phone to approve.</p>
        </div>
        <button class="vk-btn w-full !py-4 text-base text-white bg-[#00A651] hover:brightness-110" id="stk-btn"><i class="fa-solid fa-paper-plane"></i>Send payment request</button>
      </form>

      <!-- step B: waiting -->
      <div id="waiting" class="mt-6 hidden text-center">
        <div class="mx-auto h-20 w-20 rounded-full border-4 border-[#00A651]/20 border-t-[#00A651] animate-spin"></div>
        <h2 class="mt-5 font-display text-xl font-bold">Check your phone</h2>
        <p class="text-muted">An M-Pesa prompt was sent to <b class="text-ink" id="sent-to"></b>. Enter your PIN to complete payment.</p>
        <p class="mt-2 text-xs text-muted">Request expires in <b id="countdown">60</b>s</p>
        <button type="button" class="mt-4 text-sm font-bold text-accent" id="resend"><i class="fa-solid fa-rotate"></i> Didn't get it? Resend</button>
      </div>

      <!-- step C: failed -->
      <div id="failed" class="mt-6 hidden rounded-2xl bg-rose-500/10 p-5 text-center">
        <i class="fa-solid fa-circle-xmark text-4xl text-rose-500"></i>
        <h2 class="mt-2 font-bold text-lg">Payment not completed</h2>
        <p class="text-sm text-muted" id="fail-reason"></p>
        <button type="button" class="vk-btn-accent mt-4" id="retry"><i class="fa-solid fa-rotate-right"></i>Try again</button>
      </div>

      <ul class="mt-8 grid sm:grid-cols-3 gap-3 text-xs text-muted">
        <li class="flex gap-2"><i class="fa-solid fa-lock text-emerald-500 mt-0.5"></i>Encrypted & secure</li>
        <li class="flex gap-2"><i class="fa-solid fa-receipt text-emerald-500 mt-0.5"></i>Receipt by SMS & email</li>
        <li class="flex gap-2"><i class="fa-solid fa-rotate-left text-emerald-500 mt-0.5"></i>Refundable reservation</li>
      </ul>
      <?php if ($simulate): ?><p class="mt-6 rounded-xl border border-dashed border-amber-500/40 bg-amber-500/5 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"><i class="fa-solid fa-flask"></i> <b>Simulation mode:</b> no real money moves. Use the phone on the right to approve with any 4-digit PIN.</p><?php endif; ?>
    </section>

    <!-- simulated phone -->
    <?php if ($simulate): ?>
    <div class="mx-auto md:sticky md:top-32">
      <div class="vk-phone">
        <div class="vk-phone-screen" id="phone-screen">
          <div class="flex items-center justify-between px-6 pt-3 text-[11px] font-semibold"><span><?= date('H:i') ?></span><span class="h-5 w-20 rounded-full bg-black/80"></span><span><i class="fa-solid fa-signal"></i> <i class="fa-solid fa-battery-three-quarters"></i></span></div>
          <div id="phone-idle" class="flex flex-col items-center justify-center text-center px-6 pt-24">
            <i class="fa-solid fa-mobile-screen text-5xl opacity-70"></i>
            <p class="mt-4 font-semibold opacity-90">Waiting for M-Pesa request…</p>
            <p class="text-xs opacity-70 mt-1">Tap “Send payment request”</p>
          </div>
          <div id="phone-prompt" class="hidden px-4 pt-10">
            <div class="rounded-2xl bg-white text-slate-800 p-4 shadow-2xl">
              <p class="text-[11px] font-bold uppercase tracking-wider text-[#00A651]">SIM Toolkit · M-PESA</p>
              <p class="mt-2 text-sm leading-snug">Do you want to pay <b><?= e(money($order['amount_due'], $order['currency_code'])) ?></b> to <b>VIKAO LTD</b> account no. <b><?= e($order['order_number']) ?></b>?</p>
              <p class="mt-3 text-xs text-slate-500">Enter M-PESA PIN:</p>
              <div class="mt-2 flex justify-center gap-3" id="pin-dots"><span class="vk-pin-dot !border-slate-400"></span><span class="vk-pin-dot !border-slate-400"></span><span class="vk-pin-dot !border-slate-400"></span><span class="vk-pin-dot !border-slate-400"></span></div>
              <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="rounded-xl bg-slate-100 py-2 text-sm font-bold" data-sim="cancel">Cancel</button>
                <button type="button" class="rounded-xl bg-[#00A651] py-2 text-sm font-bold text-white" id="pin-ok">OK</button>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2" id="keypad">
              <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, '⌫', 0, '✓'] as $k): ?><button type="button" class="vk-key" data-key="<?= $k ?>"><?= $k ?></button><?php endforeach; ?>
            </div>
            <button type="button" class="mt-3 w-full text-[11px] opacity-80 underline" data-sim="insufficient">Simulate “insufficient balance”</button>
          </div>
          <div id="phone-done" class="hidden flex-col items-center justify-center text-center px-6 pt-24">
            <span class="grid h-20 w-20 place-items-center rounded-full bg-white text-[#00A651] text-4xl"><i class="fa-solid fa-check"></i></span>
            <p class="mt-4 font-bold">Confirmed</p><p class="text-xs opacity-80 mt-1" id="sms-text"></p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php ob_start(); ?>
<script>
(function () {
  const ORDER = <?= json_encode($order['uuid']) ?>;
  let payment = null, pin = '', pollT = null, cdT = null;
  const show = (id) => { ['#phone-idle', '#phone-prompt', '#phone-done'].forEach(s => $(s).addClass('hidden').removeClass('flex')); $(id).removeClass('hidden'); if (id === '#phone-done') $(id).addClass('flex'); };
  function dots() { $('#pin-dots span').each((i, d) => $(d).toggleClass('on !bg-slate-700', i < pin.length)); }

  $('#stk-form').on('submit', function (e) {
    e.preventDefault(); const $b = $('#stk-btn'); VK.btnLoading($b, true, 'Sending request…');
    VK.api('payments.php', { action: 'stk_push', order: ORDER, phone: $('#phone').val(), method: 'mpesa' }, { silent: true }).then(r => {
      payment = r.payment; pin = ''; dots();
      $('#stk-form, #failed').addClass('hidden'); $('#waiting').removeClass('hidden'); $('#sent-to').text(r.phone);
      VK.toast(r.message); show('#phone-prompt'); startCountdown(); startPolling();
    }).catch(j => { VK.btnLoading($b, false); VK.showErrors($('#stk-form'), j.errors); VK.toast(j.message, 'error'); });
  });

  function startCountdown() {
    let s = 60; clearInterval(cdT); $('#countdown').text(s);
    cdT = setInterval(() => { s--; $('#countdown').text(s); if (s <= 0) { clearInterval(cdT); fail('The request timed out. Please try again.'); } }, 1000);
  }
  function startPolling() {
    clearInterval(pollT);
    pollT = setInterval(() => {
      $.get(VK.url('api/payments.php'), { action: 'status', payment }).then(r => {
        if (r.status === 'success') success(r);
        else if (['failed', 'cancelled', 'timeout'].includes(r.status)) fail(r.result_desc);
      });
    }, 2000);
  }
  function stop() { clearInterval(pollT); clearInterval(cdT); }
  function fail(reason) {
    stop(); show('#phone-idle'); $('#waiting').addClass('hidden'); $('#failed').removeClass('hidden'); $('#fail-reason').text(reason || 'Payment failed.');
  }
  function success(r) {
    stop(); show('#phone-done');
    $('#sms-text').text(r.receipt + ' Confirmed. KSh paid to VIKAO LTD.');
    $('#waiting').html('<span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-500 text-white text-4xl"><i class="fa-solid fa-check"></i></span><h2 class="mt-4 font-display text-2xl font-bold">Payment received!</h2><p class="text-muted">M-Pesa receipt <b>' + r.receipt + '</b>. Placing your order…</p>');
    setTimeout(() => location.href = r.redirect, 1800);
  }
  function simulate(result) {
    if (!payment) return;
    $('#pin-ok').prop('disabled', true).text('Processing…');
    VK.api('payments.php', { action: 'simulate', payment, result, pin }).always(() => $('#pin-ok').prop('disabled', false).text('OK'));
  }
  $(document).on('click', '[data-key]', function () {
    const k = String($(this).data('key'));
    if (k === '⌫') pin = pin.slice(0, -1); else if (k === '✓') { $('#pin-ok').trigger('click'); return; } else if (pin.length < 4) pin += k;
    dots();
  });
  $('#pin-ok').on('click', () => { if (pin.length !== 4) { VK.toast('Enter a 4-digit PIN', 'warning'); return; } simulate('success'); });
  $('[data-sim]').on('click', function () { simulate($(this).data('sim')); });
  $('#retry, #resend').on('click', () => { stop(); $('#failed, #waiting').addClass('hidden'); $('#stk-form').removeClass('hidden'); VK.btnLoading($('#stk-btn'), false); show('#phone-idle'); });
})();
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
