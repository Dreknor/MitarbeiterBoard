// Paed-Diary JS: zusätzliche Anpassungen, damit die Sidebar verschwindet
// und ein Toggle-Button sie bei Bedarf wieder einblendet
// Wird nur auf der Route /paed-diary per Blade eingebunden

document.addEventListener('DOMContentLoaded', function () {
  try {
    var body = document.body;
    var sidebar = document.querySelector('.sidebar');
    var main = document.querySelector('.main-panel');

    // Standardmäßig Sidebar ausblenden (wie bisher)
    if (sidebar) {
      body.classList.add('paed-hide-sidebar');
    }

    // Setze main-panel Layout passend
    if (main) {
      if (body.classList.contains('paed-hide-sidebar')) {
        main.style.marginLeft = '0';
        main.style.width = '100%';
      } else {
        main.style.marginLeft = '';
        main.style.width = '';
      }
    }

    // Erzeuge Floating Toggle-Button (nur einmal)
    var existingBtn = document.querySelector('.paed-toggle-btn');
    if (!existingBtn) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'paed-toggle-btn';
      btn.setAttribute('aria-label', 'Sidebar ein- oder ausblenden');
      btn.title = 'Sidebar ein- oder ausblenden';

      // Datenstate steuert das Icon via CSS
      btn.dataset.state = body.classList.contains('paed-hide-sidebar') ? 'hidden' : 'visible';

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var nowHidden = body.classList.toggle('paed-hide-sidebar');

        // Update Button State
        btn.dataset.state = nowHidden ? 'hidden' : 'visible';

        // Passe main-panel an
        if (main) {
          if (nowHidden) {
            main.style.marginLeft = '0';
            main.style.width = '100%';
          } else {
            main.style.marginLeft = '';
            main.style.width = '';
          }
        }

        // Fire resize so other components recalc layout
        window.dispatchEvent(new Event('resize'));
      });

      document.body.appendChild(btn);
    }

    // Trigger initial resize
    window.dispatchEvent(new Event('resize'));

  } catch (err) {
    // Vermeide Console-Fehler in Produktion
    console && console.error && console.error('paed-diary init error', err);
  }
});
