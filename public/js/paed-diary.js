// Paed-Diary JS: Sidebar-Toggle für das pädagogische Tagebuch
// Die Sidebar ist beim Laden bereits via body.paed-hide-sidebar (Blade) ausgeblendet.
// CSS in sidebar.css und paed-diary.css steuert das Layout vollständig.

document.addEventListener('DOMContentLoaded', function () {
  try {
    var body = document.body;

    // Erzeuge Floating Toggle-Button (nur einmal)
    if (!document.querySelector('.paed-toggle-btn')) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'paed-toggle-btn';
      btn.setAttribute('aria-label', 'Sidebar ein- oder ausblenden');
      btn.title = 'Sidebar ein- oder ausblenden';
      btn.dataset.state = 'hidden'; // Startzustand: versteckt

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var nowHidden = body.classList.toggle('paed-hide-sidebar');
        btn.dataset.state = nowHidden ? 'hidden' : 'visible';
        // Kurze Verzögerung damit CSS-Transition abläuft, dann resize feuern
        setTimeout(function () {
          window.dispatchEvent(new Event('resize'));
        }, 320);
      });

      document.body.appendChild(btn);
    }

    // Initial resize damit Diary-Tabelle Breite neu berechnet
    window.dispatchEvent(new Event('resize'));

  } catch (err) {
    console && console.error && console.error('paed-diary init error', err);
  }
});


