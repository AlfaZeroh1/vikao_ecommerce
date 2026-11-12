<?php $extraScripts = $extraScripts ?? ''; ?>
</main>
<?php component('footer'); ?>
<?php component('mobile_tabbar'); ?>
<?php component('search_overlay'); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.8/sweetalert2.all.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $extraScripts ?>
</body>
</html>
