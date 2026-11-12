<footer class="bg-nav text-navink/80 mt-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 py-14 grid gap-10 md:grid-cols-12">
    <div class="md:col-span-4">
      <div class="flex items-center gap-3">
        <img src="<?= asset('img/logo-mark.svg') ?>" alt="" class="h-11 w-11 rounded-xl">
        <span class="font-display text-2xl font-bold text-white">vikao<span class="text-amber-400">.</span></span>
      </div>
      <p class="mt-4 text-sm leading-relaxed max-w-sm">Kenya's home for homes, land, commercial spaces, short stays and trusted property services — with an Official Store you can reserve from in a tap.</p>
      <div class="mt-5 flex gap-2">
        <?php foreach (['fa-x-twitter', 'fa-facebook-f', 'fa-instagram', 'fa-tiktok', 'fa-linkedin-in', 'fa-youtube'] as $s): ?>
          <a href="#" class="grid h-9 w-9 place-items-center rounded-lg bg-white/5 hover:bg-amber-500 hover:text-slate-900 transition" aria-label="social"><i class="fa-brands <?= $s ?>"></i></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="md:col-span-2">
      <h4 class="text-white font-bold mb-3">Explore</h4>
      <ul class="space-y-2 text-sm">
        <li><a class="hover:text-amber-400" href="<?= url('shop.php', ['cat' => 'residential-for-sale']) ?>">Homes for sale</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('shop.php', ['cat' => 'residential-for-rent']) ?>">Homes for rent</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('shop.php', ['cat' => 'land-for-sale']) ?>">Land & plots</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('shop.php', ['cat' => 'short-stay']) ?>">Short stays</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('shop.php', ['cat' => 'services']) ?>">Property services</a></li>
      </ul>
    </div>
    <div class="md:col-span-2">
      <h4 class="text-white font-bold mb-3">Account</h4>
      <ul class="space-y-2 text-sm">
        <li><a class="hover:text-amber-400" href="<?= url('sell.php') ?>">Post an ad</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('my-listings.php') ?>">My listings</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('orders.php') ?>">My orders</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('chats.php') ?>">Chats</a></li>
        <li><a class="hover:text-amber-400" href="<?= url('pms/index.php') ?>">Vikao PMS (staff)</a></li>
      </ul>
    </div>
    <div class="md:col-span-4">
      <h4 class="text-white font-bold mb-3">Stay safe & in the loop</h4>
      <ul class="space-y-2 text-sm mb-5">
        <li class="flex gap-2"><i class="fa-solid fa-shield-halved text-amber-400 mt-0.5"></i>Never pay before viewing a property in person.</li>
        <li class="flex gap-2"><i class="fa-solid fa-crown text-amber-400 mt-0.5"></i>Official Vikao listings are verified and payments go through Vikao only.</li>
      </ul>
      <div class="flex gap-2">
        <span class="inline-flex items-center gap-2 rounded-xl bg-white/5 px-3 py-2 text-xs"><i class="fa-brands fa-apple text-lg"></i>App Store · soon</span>
        <span class="inline-flex items-center gap-2 rounded-xl bg-white/5 px-3 py-2 text-xs"><i class="fa-brands fa-google-play text-lg"></i>Google Play · soon</span>
      </div>
    </div>
  </div>
  <div class="border-t border-white/10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 py-5 flex flex-col sm:flex-row gap-2 items-center justify-between text-xs">
      <p>© <?= date('Y') ?> Vikao · vikao.io · All rights reserved.</p>
      <p class="flex items-center gap-3"><span><i class="fa-solid fa-earth-africa"></i> Kenya</span><span class="opacity-50">Uganda · Tanzania · Rwanda — coming soon</span></p>
    </div>
  </div>
</footer>
