<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) { verify_csrf(); logout_user(); flash('success', 'You have been signed out.'); }
redirect('index.php');
