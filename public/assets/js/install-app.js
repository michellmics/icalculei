// Vibe2000 - convite para instalar o app (PWA do site). Mesmo modelo do projeto direitaconservada.
//
// Qualquer elemento com data-install-app vira o botão "Instalar o app".
// Elementos com data-install-area (ex.: o quadro da lateral) aparecem junto com os botões.
//   Android/PC com instalador (Chrome, Edge…): "Instalar agora" abre o instalador do navegador.
//   Android sem instalador / iPhone / iPad: o convite mostra o passo a passo animado
//   (menu ⋮ → "Instalar app", ou Compartilhar → "Adicionar à Tela de Início").
//   Já aberto como app: nada aparece.
// No celular, o convite abre sozinho depois de alguns segundos (e some por 7 dias se a pessoa disser "Agora não").
(() => {
  "use strict";

  const isInstalled = matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  const isAndroid = /android/i.test(navigator.userAgent);
  const isPhone = isIos || isAndroid || matchMedia("(pointer: coarse) and (max-width: 820px)").matches;
  const APP_ICON = "/icons/site-192.png";
  const DISMISS_KEY = "vibe2000-install-dismissed";
  const DISMISS_DAYS = 7;
  const AUTO_OPEN_SECONDS = 8;
  let installPrompt = null; // o evento "dá para instalar" do navegador (Android / PC)

  function showInstallButtons(canInstall) {
    document.querySelectorAll("[data-install-app], [data-install-area]").forEach((element) => {
      element.hidden = !canInstall;
    });
  }

  if (isInstalled) {
    showInstallButtons(false);
    return;
  }
  if (isPhone) {
    showInstallButtons(true);
  }

  // "Agora não" vale por 7 dias (só conveniência: sem armazenamento, o convite aparece de novo, e tudo bem)
  function wasDismissedRecently() {
    try {
      return Date.now() - Number(localStorage.getItem(DISMISS_KEY) || 0) < DISMISS_DAYS * 86400000;
    } catch {
      return false;
    }
  }
  function rememberDismissal() {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch {
      // navegação privada: tudo bem
    }
  }

  window.addEventListener("beforeinstallprompt", (promptEvent) => {
    promptEvent.preventDefault(); // guarda para quando a pessoa tocar no botão
    installPrompt = promptEvent;
    showInstallButtons(true);
    document.querySelector(".install-invite")?.classList.add("has-installer");
  });
  window.addEventListener("appinstalled", () => {
    installPrompt = null;
    showInstallButtons(false);
    closeInvite();
  });

  async function installNow() {
    if (!installPrompt) {
      return false;
    }
    installPrompt.prompt();
    const choice = await installPrompt.userChoice.catch(() => null);
    installPrompt = null;
    if (choice?.outcome === "accepted") {
      showInstallButtons(false);
      closeInvite();
    }
    return true;
  }

  /* ---------- o convite ---------- */
  const shareIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const addIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
  const menuIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="19" r="2" fill="currentColor"/></svg>';

  // Passo a passo e desenho da tela do celularzinho, conforme o aparelho
  function guideForDevice() {
    if (isIos) {
      return {
        steps: [
          `Toque em <b>Compartilhar</b> <i class="install-icon">${shareIcon}</i> na barra do Safari`,
          `Escolha <b>Adicionar à Tela de Início</b> <i class="install-icon">${addIcon}</i>`,
          "Pronto! As calculadoras ficam na sua tela",
        ],
        note: "No iPhone, abra o site pelo Safari para instalar.",
        bar: `<div class="phone-bar is-bottom"><span></span><span class="phone-target">${shareIcon}</span><span></span></div>`,
        menu: `<div class="phone-menu is-bottom"><div>Copiar</div><div>Adicionar aos Favoritos</div><div class="phone-target">${addIcon} Adicionar à Tela de Início</div></div>`,
      };
    }
    return {
      steps: [
        `Toque no menu <i class="install-icon">${menuIcon}</i> do navegador`,
        "Escolha <b>Instalar app</b> ou <b>Adicionar à tela inicial</b>",
        "Pronto! As calculadoras ficam na sua tela",
      ],
      note: "Funciona no Chrome, Edge e Samsung Internet.",
      bar: `<div class="phone-bar is-top"><span class="phone-url">${location.host}</span><span class="phone-target">${menuIcon}</span></div>`,
      menu: `<div class="phone-menu is-top"><div>Nova guia</div><div>Favoritos</div><div class="phone-target">${addIcon} Instalar app</div></div>`,
    };
  }

  function openInvite() {
    if (document.querySelector(".install-invite")) {
      return;
    }
    const guide = guideForDevice();
    const otherApps = Array.from({ length: 4 }, () => "<i></i>").join("");
    const invite = document.createElement("div");
    invite.className = `install-invite${installPrompt ? " has-installer" : ""}${isIos ? " is-ios" : " is-android"}`;
    invite.innerHTML = `
      <div class="install-card" role="dialog" aria-modal="true" aria-labelledby="install-title" tabindex="-1">
        <button type="button" class="install-close" data-close-install aria-label="Fechar">×</button>
        <div class="install-top">
          <span class="install-glow" aria-hidden="true"></span>
          <img class="install-app-icon" src="${APP_ICON}" alt="">
        </div>
        <h2 id="install-title">Calculadoras no bolso</h2>
        <p class="install-subtitle">Instale o <b>Vibe2000</b>: abre num toque, em tela cheia, funciona até sem internet e não ocupa espaço.</p>

        <div class="install-quick">
          <button type="button" class="action-button install-now" data-install-now>📲 Instalar agora</button>
          <small>É grátis e leva 2 segundos.</small>
        </div>

        <div class="install-guide">
          <div class="install-phone" aria-hidden="true">
            <div class="phone-screen">
              <div class="phone-scene scene-1">${guide.bar}<span class="phone-finger"></span></div>
              <div class="phone-scene scene-2">${guide.bar}${guide.menu}<span class="phone-finger"></span></div>
              <div class="phone-scene scene-3"><div class="phone-home">${otherApps}<b><img src="${APP_ICON}" alt=""><small>Vibe2000</small></b></div></div>
            </div>
          </div>
          <ol class="install-steps">
            ${guide.steps.map((step, index) => `<li class="step-${index + 1}"><span>${index + 1}</span><div>${step}</div></li>`).join("")}
          </ol>
        </div>
        <p class="install-note">${guide.note}</p>

        <button type="button" class="install-later" data-close-install>Agora não</button>
      </div>`;
    invite.addEventListener("click", async (clickEvent) => {
      if (clickEvent.target.closest("[data-install-now]")) {
        if (!(await installNow())) {
          invite.classList.remove("has-installer"); // o navegador não deixou: mostra o passo a passo
        }
        return;
      }
      if (clickEvent.target === invite || clickEvent.target.closest("[data-close-install]")) {
        rememberDismissal();
        closeInvite();
      }
    });
    document.addEventListener("keydown", closeOnEscape);
    document.body.appendChild(invite);
    document.documentElement.classList.add("install-open");
    requestAnimationFrame(() => invite.classList.add("is-open"));
    invite.querySelector(".install-card").focus();
  }

  function closeOnEscape(keyEvent) {
    if (keyEvent.key === "Escape") {
      rememberDismissal();
      closeInvite();
    }
  }

  function closeInvite() {
    const invite = document.querySelector(".install-invite");
    document.removeEventListener("keydown", closeOnEscape);
    document.documentElement.classList.remove("install-open");
    if (!invite) {
      return;
    }
    invite.classList.remove("is-open");
    invite.classList.add("is-leaving");
    setTimeout(() => invite.remove(), 350);
  }

  document.addEventListener("click", async (clickEvent) => {
    if (!clickEvent.target.closest("[data-install-app]")) {
      return;
    }
    clickEvent.preventDefault();
    if (isPhone) {
      openInvite(); // no celular, o convite explica (e tem o "Instalar agora" quando dá)
    } else if (!(await installNow())) {
      openInvite();
    }
  });

  // No celular, o convite abre sozinho depois de alguns segundos, se não foi dispensado há pouco
  // e se não tem outra janela aberta por cima (como o aviso de cookies).
  if (isPhone && !wasDismissedRecently()) {
    setTimeout(function tryToOpen() {
      if (wasDismissedRecently() || document.querySelector(".install-invite")) {
        return;
      }
      const cookieBanner = document.getElementById("cookie-banner");
      const somethingOpen = (cookieBanner && !cookieBanner.hidden)
        || [...document.querySelectorAll('dialog[open], [aria-modal="true"]')].some((element) => element.getClientRects().length);
      if (somethingOpen) {
        setTimeout(tryToOpen, 5000);
        return;
      }
      openInvite();
    }, AUTO_OPEN_SECONDS * 1000);
  }
})();
