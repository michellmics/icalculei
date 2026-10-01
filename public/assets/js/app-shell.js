// iCalculei - recursos do app instalado (PWA), no site e no painel. Mesmo modelo do projeto direitaconservada.
//
// 1. Puxar para atualizar: aberto como app, o Chrome e o Safari desligam o gesto nativo; no navegador comum
//    ele já existe, então aqui só age em modo app. No topo da página, puxar para baixo mostra a bolinha ↻;
//    soltou depois do ponto → recarrega. Não atrapalha campos de texto, mapas, gráficos, menus abertos ou gesto de lado.
// 2. Barra de navegação no rodapé (Voltar, Avançar, Início, Atualizar): o app instalado não tem os botões
//    do navegador. Aparece só no app e em telas de celular (o CSS cuida do tamanho).
(() => {
  "use strict";

  const isApp = matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
  if (!isApp) {
    return;
  }
  document.documentElement.classList.add("is-app");

  /* ---------- Barra de navegação do rodapé ---------- */
  const appNav = document.getElementById("app-nav");
  if (appNav) {
    appNav.hidden = false;
    appNav.addEventListener("click", (clickEvent) => {
      const button = clickEvent.target.closest("[data-app-action]");
      if (!button) {
        return;
      }
      const action = button.dataset.appAction;
      if (action === "back") {
        history.back();
      } else if (action === "forward") {
        history.forward();
      } else if (action === "reload") {
        location.reload();
      }
    });
  }

  /* ---------- Puxar para atualizar ---------- */
  if (!("ontouchstart" in window)) {
    return;
  }
  const PULL_TO_RELOAD = 70; // px puxados (já com a resistência) para recarregar
  const PULL_MAX = 110;
  let ball = null;
  let startY = null;
  let startX = 0;
  let pulled = 0;
  let directionDecided = false; // já sabe se o gesto é vertical (nosso) ou lateral (ignora)
  let reloading = false;

  function createBall() {
    ball = document.createElement("div");
    ball.className = "pull-ball";
    ball.setAttribute("aria-hidden", "true");
    ball.innerHTML = "<span>↻</span>";
    document.body.appendChild(ball);
  }

  // Algum pedaço rolável entre o dedo e a página, que ainda não está no topo? Então a rolagem é dele
  function insideScrolledArea(element) {
    for (let node = element; node && node !== document.body; node = node.parentElement) {
      if (node.scrollTop > 0 && /(auto|scroll)/.test(getComputedStyle(node).overflowY)) {
        return true;
      }
    }
    return false;
  }

  function shouldIgnore(target) {
    return window.scrollY > 0
      || document.documentElement.classList.contains("install-open") // convite de instalação aberto
      || document.querySelector(".site-header.is-menu-open, .admin-topbar.is-menu-open") // menu sanduíche aberto
      || target.closest("input, textarea, select, canvas, iframe, video, [contenteditable], .leaflet-container, [data-no-pull]")
      || insideScrolledArea(target);
  }

  function showBall(distance, animate) {
    if (!ball) {
      createBall();
    }
    ball.classList.toggle("animate", animate);
    ball.classList.toggle("ready", distance >= PULL_TO_RELOAD);
    ball.style.transform = `translate(-50%, ${distance - 50}px)`;
    ball.style.opacity = String(Math.min(1, distance / PULL_TO_RELOAD));
    ball.firstChild.style.transform = `rotate(${distance * 3}deg)`;
  }

  window.addEventListener("touchstart", (touchEvent) => {
    if (reloading || touchEvent.touches.length !== 1 || shouldIgnore(touchEvent.target)) {
      startY = null;
      return;
    }
    startY = touchEvent.touches[0].clientY;
    startX = touchEvent.touches[0].clientX;
    pulled = 0;
    directionDecided = false;
  }, { passive: true });

  window.addEventListener("touchmove", (touchEvent) => {
    if (startY === null) {
      return;
    }
    const deltaY = touchEvent.touches[0].clientY - startY;
    const deltaX = touchEvent.touches[0].clientX - startX;
    if (!directionDecided) {
      if (Math.abs(deltaY) < 8 && Math.abs(deltaX) < 8) {
        return;
      }
      directionDecided = true;
      if (deltaY <= 0 || Math.abs(deltaX) > Math.abs(deltaY)) {
        startY = null; // subindo ou de lado: não é com a gente
        return;
      }
    }
    if (window.scrollY > 0) {
      startY = null;
      return;
    }
    touchEvent.preventDefault(); // segura o "quique" da página enquanto puxa
    pulled = Math.min(PULL_MAX, Math.max(0, deltaY) * 0.5); // resistência: o dedo anda mais que a bolinha
    showBall(pulled, false);
  }, { passive: false });

  function release() {
    if (startY === null) {
      return;
    }
    startY = null;
    if (pulled >= PULL_TO_RELOAD) {
      reloading = true;
      showBall(PULL_TO_RELOAD, true);
      ball.classList.add("spinning");
      location.reload();
    } else if (ball) {
      showBall(0, true);
    }
    pulled = 0;
  }
  window.addEventListener("touchend", release, { passive: true });
  window.addEventListener("touchcancel", release, { passive: true });
})();
