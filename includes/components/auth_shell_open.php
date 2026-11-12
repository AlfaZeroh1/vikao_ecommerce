<section class="mx-auto max-w-6xl px-4 sm:px-6 py-10 lg:py-16 grid lg:grid-cols-2 gap-10 items-center">
  <div class="hidden lg:block relative overflow-hidden rounded-4xl vk-hero text-white p-10 min-h-[560px]">
    <div class="absolute inset-0 vk-grid-bg opacity-60"></div>
    <div class="relative">
      <img src="<?= asset('img/logo-mark.svg') ?>" alt="" class="h-14 w-14 rounded-2xl shadow-lift">
      <h2 class="mt-8 font-display text-4xl font-bold leading-tight">Find your place.<br><span class="text-amber-400">List it in minutes.</span></h2>
      <ul class="mt-8 space-y-4 text-slate-200">
        <li class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-amber-400"><i class="fa-solid fa-house-chimney"></i></span><span><b class="text-white">Homes, land & commercial</b><br><span class="text-sm text-slate-300">For sale, for rent and short stays across Kenya.</span></span></li>
        <li class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-amber-400"><i class="fa-solid fa-crown"></i></span><span><b class="text-white">Official Vikao Store</b><br><span class="text-sm text-slate-300">Verified listings you can reserve with M-Pesa.</span></span></li>
        <li class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-amber-400"><i class="fa-solid fa-comments"></i></span><span><b class="text-white">Chat per ad</b><br><span class="text-sm text-slate-300">Every conversation tied to the listing you asked about.</span></span></li>
      </ul>
    </div>
  </div>
  <div class="vk-panel p-6 sm:p-10 shadow-soft max-w-lg w-full mx-auto">
    <h1 class="font-display text-3xl font-bold tracking-tight"><?= e($heading) ?></h1>
    <p class="mt-1 text-muted mb-6"><?= e($sub) ?></p>
