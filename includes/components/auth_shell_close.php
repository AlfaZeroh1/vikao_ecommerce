  </div>
</section>
<script>
document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-toggle-pass]'); if (!b) return;
  const i = b.parentElement.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password';
  b.querySelector('i').className = i.type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
});
</script>
