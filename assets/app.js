(function () {
  var toggle = document.querySelector('.sidebar-toggle');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');

  if (!toggle || !sidebar) return;

  function setOpen(open) {
    sidebar.classList.toggle('is-open', open);
    if (overlay) overlay.classList.toggle('is-visible', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'メニューを閉じる' : 'メニューを開く');
    document.body.classList.toggle('sidebar-open', open);
  }

  toggle.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('is-open'));
  });

  if (overlay) {
    overlay.addEventListener('click', function () {
      setOpen(false);
    });
  }

  sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
    link.addEventListener('click', function () {
      setOpen(false);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setOpen(false);
  });
})();
