/* Ostinati Orizzonti – script del sito (nessuna libreria esterna) */
(function () {
  'use strict';

  /* --- Menu su smartphone --- */
  var pulsante = document.querySelector('.menu-pulsante');
  var menu = document.getElementById('menu-principale');
  if (pulsante && menu) {
    var chiudi = function () {
      pulsante.setAttribute('aria-expanded', 'false');
      menu.classList.remove('e-aperta');
    };
    pulsante.addEventListener('click', function () {
      var aperto = pulsante.getAttribute('aria-expanded') === 'true';
      pulsante.setAttribute('aria-expanded', String(!aperto));
      menu.classList.toggle('e-aperta', !aperto);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') chiudi();
    });
    menu.addEventListener('click', function (e) {
      if (e.target.closest('a')) chiudi();
    });
  }

  /* --- Ombra della testata quando si scorre --- */
  var testata = document.querySelector('.testata');
  if (testata) {
    var aggiorna = function () {
      testata.classList.toggle('e-scorrevole', window.scrollY > 8);
    };
    aggiorna();
    window.addEventListener('scroll', aggiorna, { passive: true });
  }

  /* --- Comparsa delle sezioni durante lo scorrimento --- */
  var elementi = document.querySelectorAll('.rivela');
  // A comparsa finita tolgo la classe: così le animazioni al passaggio del
  // mouse (es. schede "Cosa vogliamo") restano rapide e senza ritardi.
  // Uso un timer (durata comparsa 700ms + ritardo a cascata + margine).
  var completa = function (el, ritardo) {
    setTimeout(function () {
      el.classList.remove('rivela', 'e-visibile');
      el.style.transitionDelay = '';
    }, ritardo + 900);
  };
  if ('IntersectionObserver' in window) {
    var osservatore = new IntersectionObserver(function (voci) {
      voci.forEach(function (voce) {
        if (voce.isIntersecting) {
          voce.target.classList.add('e-visibile');
          completa(voce.target, parseInt(voce.target.style.transitionDelay, 10) || 0);
          osservatore.unobserve(voce.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    elementi.forEach(function (el, i) {
      // piccolo ritardo a cascata tra elementi vicini (es. schede)
      el.style.transitionDelay = (i % 4) * 80 + 'ms';
      osservatore.observe(el);
    });
  } else {
    elementi.forEach(function (el) { el.classList.add('e-visibile'); });
  }

  /* --- Anno corrente nel piè di pagina --- */
  document.querySelectorAll('[data-anno]').forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  /* --- Newsletter (Brevo) --- */
  document.querySelectorAll('form[data-newsletter]').forEach(function (form) {
    var esito = form.querySelector('.modulo-newsletter__esito');
    form.addEventListener('submit', function (e) {
      var email = form.querySelector('input[type="email"]');
      var consenso = form.querySelector('input[name="OPT_IN"]');
      if (!email.value || !email.checkValidity()) {
        e.preventDefault();
        esito.textContent = 'Inserisci un indirizzo email valido.';
        email.focus();
        return;
      }
      if (!consenso.checked) {
        e.preventDefault();
        esito.textContent = 'Per iscriverti è necessario accettare l’informativa sulla privacy.';
        consenso.focus();
        return;
      }
      // Finché il modulo non è collegato a Brevo non inviamo nulla
      if (!form.getAttribute('action')) {
        e.preventDefault();
        esito.textContent = 'La newsletter sarà attiva a breve: torna a trovarci!';
      }
    });
  });

  /* --- Modulo di contatto --- */
  document.querySelectorAll('form[data-contatti]').forEach(function (form) {
    var esito = form.querySelector('.modulo-contatti__esito');
    var pulsante = form.querySelector('button[type="submit"]');
    var EMAIL_ASSOCIAZIONE = 'ass.culturaleostinatiorizzonti@gmail.com';

    // momento in cui il modulo è stato mostrato (i robot lo compilano in un attimo)
    var campoTempo = form.querySelector('[name="t"]');
    var mostratoAlle = Date.now();

    var mostraEsito = function (testo, tipo) {
      esito.textContent = testo;
      esito.className = 'modulo-contatti__esito' + (tipo ? ' e-' + tipo : '');
    };

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var nome = form.querySelector('[name="nome"]');
      var email = form.querySelector('[name="email"]');
      var messaggio = form.querySelector('[name="messaggio"]');
      var consenso = form.querySelector('[name="consenso"]');
      var errore = function (testo, campo) {
        mostraEsito(testo, 'errore');
        campo.focus();
      };
      if (!nome.value.trim()) return errore('Inserisci il tuo nome.', nome);
      if (!email.value || !email.checkValidity()) return errore('Inserisci un indirizzo email valido.', email);
      if (!messaggio.value.trim()) return errore('Scrivi il tuo messaggio.', messaggio);
      if (!consenso.checked) return errore('Per inviare il messaggio è necessario accettare l’informativa sulla privacy.', consenso);

      // tempo (in millisecondi) passato da quando il modulo è stato mostrato
      if (campoTempo) campoTempo.value = Date.now() - mostratoAlle;
      pulsante.disabled = true;
      mostraEsito('Invio in corso…');

      fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form) })
        .then(function (risposta) {
          return risposta.json().catch(function () { return { ok: false }; });
        })
        .then(function (dati) {
          if (dati.ok) {
            form.reset();
            mostratoAlle = Date.now();
            mostraEsito(dati.messaggio || 'Grazie! Il tuo messaggio è stato inviato.', 'ok');
          } else {
            mostraEsito(dati.messaggio || ('Non siamo riusciti a inviare il messaggio. Riprova più tardi o scrivici a ' + EMAIL_ASSOCIAZIONE + '.'), 'errore');
          }
        })
        .catch(function () {
          mostraEsito('Non siamo riusciti a inviare il messaggio. Controlla la connessione e riprova, oppure scrivici a ' + EMAIL_ASSOCIAZIONE + '.', 'errore');
        })
        .then(function () {
          pulsante.disabled = false;
        });
    });
  });
})();
