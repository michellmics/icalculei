// iCalculei - scripts de todas as páginas públicas:
// contador de visitas, aviso de cookies, anúncios e formulário de contato.

(() => {
  "use strict";

  /* ---------- Menu sanduíche (celular) ---------- */
  // Abre e fecha as categorias; fecha ao escolher uma, tocar fora ou apertar Esc
  const siteMenuButton = document.querySelector(".site-menu-button");
  const siteHeader = document.querySelector(".site-header");
  if (siteMenuButton && siteHeader) {
    const setSiteMenuOpen = (isOpen) => {
      siteHeader.classList.toggle("is-menu-open", isOpen);
      siteMenuButton.setAttribute("aria-expanded", String(isOpen));
      siteMenuButton.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
    };
    siteMenuButton.addEventListener("click", () => setSiteMenuOpen(!siteHeader.classList.contains("is-menu-open")));
    document.getElementById("site-menu").addEventListener("click", (clickEvent) => {
      if (clickEvent.target.closest("a")) {
        setSiteMenuOpen(false);
      }
    });
    document.addEventListener("click", (clickEvent) => {
      if (!siteHeader.contains(clickEvent.target)) {
        setSiteMenuOpen(false);
      }
    });
    document.addEventListener("keydown", (keyEvent) => {
      if (keyEvent.key === "Escape") {
        setSiteMenuOpen(false);
      }
    });
  }

  /* ---------- Aviso de cookies (LGPD) ---------- */
  // A escolha fica guardada neste navegador. Anúncios personalizados só com permissão.
  const COOKIE_STORAGE_KEY = "icalculei-cookie-consent";
  const cookieBanner = document.getElementById("cookie-banner");
  const cookieOptions = document.getElementById("cookie-options");
  const advertisingCheckbox = document.getElementById("cookie-advertising");

  function readCookieConsent() {
    try {
      return JSON.parse(localStorage.getItem(COOKIE_STORAGE_KEY));
    } catch {
      return null;
    }
  }

  function saveCookieConsent(allowsAdvertising) {
    try {
      localStorage.setItem(COOKIE_STORAGE_KEY, JSON.stringify({ advertising: allowsAdvertising, savedAt: new Date().toISOString() }));
    } catch {
      // Sem armazenamento (navegação privada): a escolha vale só nesta visita
    }
    cookieBanner.hidden = true;
    // Recarrega para os anúncios respeitarem a nova escolha
    if (document.querySelector("ins.adsbygoogle")) {
      window.location.reload();
    }
  }

  function openCookieBanner(showOptions) {
    advertisingCheckbox.checked = Boolean(readCookieConsent()?.advertising);
    cookieOptions.hidden = !showOptions;
    document.getElementById("cookie-save").hidden = !showOptions;
    document.getElementById("cookie-customize").hidden = showOptions;
    cookieBanner.hidden = false;
  }

  if (cookieBanner) {
    document.getElementById("cookie-accept-all").addEventListener("click", () => saveCookieConsent(true));
    document.getElementById("cookie-necessary-only").addEventListener("click", () => saveCookieConsent(false));
    document.getElementById("cookie-customize").addEventListener("click", () => openCookieBanner(true));
    document.getElementById("cookie-save").addEventListener("click", () => saveCookieConsent(advertisingCheckbox.checked));
    document.getElementById("cookie-settings-link").addEventListener("click", () => openCookieBanner(true));
    if (!readCookieConsent()) {
      openCookieBanner(false);
    }
  }

  /* ---------- Anúncios do Google ---------- */
  // Sem permissão para personalizar, o Google mostra anúncios não personalizados.
  const adSlots = document.querySelectorAll("ins.adsbygoogle");
  if (adSlots.length > 0) {
    window.adsbygoogle = window.adsbygoogle || [];
    if (!readCookieConsent()?.advertising) {
      window.adsbygoogle.requestNonPersonalizedAds = 1;
    }
    adSlots.forEach(() => {
      try {
        window.adsbygoogle.push({});
      } catch {
        // Bloqueador de anúncios ou script do Google indisponível: o site continua normal
      }
    });
  }

  /* ---------- Contador de visitas ---------- */
  const pageKey = document.body.dataset.page || "inicio";
  const VISIT_ENDPOINT = "/api/visita";
  const PING_INTERVAL_MILLISECONDS = 30000;

  function sendVisitEvent(eventData, useBeacon = false) {
    const body = JSON.stringify(eventData);
    if (useBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(VISIT_ENDPOINT, new Blob([body], { type: "application/json" }));
      return;
    }
    fetch(VISIT_ENDPOINT, { method: "POST", headers: { "Content-Type": "application/json" }, body, keepalive: true, credentials: "same-origin" }).catch(() => {});
  }

  const utmSource = new URLSearchParams(window.location.search).get("utm_source") || "";
  sendVisitEvent({ t: "ver", p: pageKey, r: document.referrer, u: utmSource });

  // "Ainda estou aqui" enquanto a aba estiver visível (alimenta o "online agora" do painel)
  setInterval(() => {
    if (document.visibilityState === "visible") {
      sendVisitEvent({ t: "ping", p: pageKey });
    }
  }, PING_INTERVAL_MILLISECONDS);
  window.addEventListener("pagehide", () => sendVisitEvent({ t: "sai" }, true));

  // Chamado pelo calculators.js no primeiro cálculo de cada página
  let toolUseRegistered = false;
  window.iCalculei = {
    // Usado pelo conversor de moedas para trocar as cotações de exemplo pelas do dia
    getExchangeRates: () => getExchangeRates(),
    registerToolUse(toolId) {
      if (toolUseRegistered || !toolId) {
        return;
      }
      toolUseRegistered = true;
      sendVisitEvent({ t: "uso", tool: toolId });
    },
  };

  /* ---------- Cotações de moedas (APIs gratuitas, chamadas pelo navegador) ---------- */
  // Principal: AwesomeAPI (brasileira, atualiza durante o dia).
  // Reserva: Frankfurter (Banco Central Europeu, atualiza uma vez por dia útil).
  // O resultado fica guardado por 10 minutos neste navegador para não chamar a API a cada página.
  const EXCHANGE_CACHE_KEY = "icalculei-exchange-rates";
  const EXCHANGE_CACHE_MINUTES = 10;
  const PRIMARY_EXCHANGE_URL = "https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL,GBP-BRL,ARS-BRL";
  const BACKUP_EXCHANGE_URL = "https://api.frankfurter.dev/v1/latest?from=USD&to=BRL,EUR,GBP";

  async function fetchJson(url) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 6000);
    try {
      const response = await fetch(url, { signal: controller.signal });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return await response.json();
    } finally {
      clearTimeout(timeout);
    }
  }

  // Resultado no mesmo formato para as duas APIs: { rates: { USD: {...} }, source, updatedAt }
  async function loadFromPrimaryApi() {
    const data = await fetchJson(PRIMARY_EXCHANGE_URL);
    const rates = {};
    for (const code of ["USD", "EUR", "GBP", "ARS"]) {
      const quote = data[`${code}BRL`];
      if (quote && Number(quote.bid) > 0) {
        rates[code] = {
          value: Number(quote.bid),
          changePercent: Number(quote.pctChange),
          high: Number(quote.high),
          low: Number(quote.low),
        };
      }
    }
    if (!rates.USD || !rates.EUR) {
      throw new Error("resposta incompleta");
    }
    const newestTimestamp = Math.max(Number(data.USDBRL.timestamp), Number(data.EURBRL.timestamp)) * 1000;
    return { rates, source: "AwesomeAPI", updatedAt: newestTimestamp };
  }

  async function loadFromBackupApi() {
    const data = await fetchJson(BACKUP_EXCHANGE_URL);
    const realPerDollar = Number(data.rates.BRL);
    if (!(realPerDollar > 0)) {
      throw new Error("resposta incompleta");
    }
    // A Frankfurter dá tudo em dólar: 1 euro = (reais por dólar) ÷ (euros por dólar)
    const rates = { USD: { value: realPerDollar } };
    for (const code of ["EUR", "GBP"]) {
      if (Number(data.rates[code]) > 0) {
        rates[code] = { value: realPerDollar / Number(data.rates[code]) };
      }
    }
    return { rates, source: "Banco Central Europeu (Frankfurter)", updatedAt: new Date(`${data.date}T12:00:00`).getTime(), dailyOnly: true };
  }

  let exchangeRatesPromise = null;
  function getExchangeRates() {
    if (exchangeRatesPromise) {
      return exchangeRatesPromise;
    }
    exchangeRatesPromise = (async () => {
      try {
        const cached = JSON.parse(sessionStorage.getItem(EXCHANGE_CACHE_KEY));
        if (cached && Date.now() - cached.savedAt < EXCHANGE_CACHE_MINUTES * 60000) {
          return cached;
        }
      } catch {
        // Sem armazenamento: busca de novo
      }
      let result = null;
      try {
        result = await loadFromPrimaryApi();
      } catch {
        try {
          result = await loadFromBackupApi();
        } catch {
          return null; // as duas APIs fora do ar
        }
      }
      result.savedAt = Date.now();
      try {
        sessionStorage.setItem(EXCHANGE_CACHE_KEY, JSON.stringify(result));
      } catch {
        // Sem armazenamento: tudo bem
      }
      return result;
    })();
    return exchangeRatesPromise;
  }

  const moneyFormat = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", minimumFractionDigits: 2, maximumFractionDigits: 4 });
  const exchangeBox = document.getElementById("exchange-rates");
  if (exchangeBox) {
    getExchangeRates().then((exchange) => {
      const updatedLabel = document.getElementById("exchange-updated");
      if (!exchange) {
        exchangeBox.querySelectorAll(".exchange-value").forEach((valueElement) => { valueElement.textContent = "indisponível"; });
        updatedLabel.textContent = "não foi possível buscar a cotação agora";
        return;
      }
      exchangeBox.querySelectorAll(".exchange-row").forEach((row) => {
        const quote = exchange.rates[row.dataset.currency];
        if (!quote) {
          return;
        }
        row.querySelector(".exchange-value").textContent = moneyFormat.format(quote.value);
        const changeElement = row.querySelector(".exchange-change");
        if (Number.isFinite(quote.changePercent)) {
          const direction = quote.changePercent > 0.005 ? "is-up" : quote.changePercent < -0.005 ? "is-down" : "is-flat";
          const arrow = direction === "is-up" ? "▲" : direction === "is-down" ? "▼" : "●";
          changeElement.className = `exchange-change change ${direction}`;
          changeElement.textContent = `${arrow} ${quote.changePercent > 0 ? "+" : ""}${quote.changePercent.toFixed(2).replace(".", ",")}%`;
        }
        if (quote.high > 0 && quote.low > 0) {
          row.querySelector(".exchange-range").textContent = `mín. ${moneyFormat.format(quote.low)} · máx. ${moneyFormat.format(quote.high)}`;
        }
      });
      const updatedAt = new Date(exchange.updatedAt);
      updatedLabel.textContent = exchange.dailyOnly
        ? `cotação de ${updatedAt.toLocaleDateString("pt-BR")}`
        : `atualizado às ${updatedAt.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
    });
  }

  /* ---------- Índices econômicos da página inicial (IPCA, IGP-M, Selic) ---------- */
  // Os dados vêm do Banco Central pelo servidor (/api/indices), que guarda o resultado em cache
  const indicatorsBox = document.getElementById("indicators");
  if (indicatorsBox) {
    const percentFormat = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const asPercent = (value) => `${percentFormat.format(value)}%`;
    const fillRow = (key, currentHtml, yearHtml) => {
      const row = indicatorsBox.querySelector(`[data-indicator="${key}"]`);
      row.querySelector(".indicator-current").innerHTML = currentHtml;
      row.querySelector(".indicator-year").innerHTML = yearHtml;
    };
    fetch("/api/indices", { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then((response) => (response.ok ? response.json() : Promise.reject()))
      .then((indicators) => {
        for (const key of ["ipca", "igpm"]) {
          const index = indicators[key];
          fillRow(key, index ? `${asPercent(index.monthly)}<small>${index.month}</small>` : "—", index ? asPercent(index.twelveMonths) : "—");
        }
        const selic = indicators.selic;
        fillRow("selic", selic ? `${asPercent(selic.target)}<small>meta ao ano</small>` : "—", selic ? `${asPercent(selic.twelveMonths)}<small>rendeu</small>` : "—");
      })
      .catch(() => {
        indicatorsBox.querySelectorAll(".indicator-current, .indicator-year").forEach((cell) => { cell.textContent = "—"; });
      });
  }

  /* ---------- Faixa "Indicadores" da página inicial ---------- */
  // Dados do servidor (/api/indicadores), que guarda cada fonte em cache. Item sem dados não aparece.
  const ticker = document.getElementById("market-ticker");
  if (ticker) {
    const money = (value, decimals = 2) => new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
    const number = (value, decimals = 2) => new Intl.NumberFormat("pt-BR", { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
    // Seta colorida da variação (verde sobe, vermelho desce)
    const change = (percent) => {
      if (percent === null || percent === undefined || Number.isNaN(percent)) {
        return "";
      }
      const direction = percent > 0.005 ? "is-up" : percent < -0.005 ? "is-down" : "is-flat";
      const arrow = direction === "is-up" ? "▲" : direction === "is-down" ? "▼" : "●";
      return ` <span class="change ${direction}">${arrow} ${number(Math.abs(percent))}%</span>`;
    };
    const item = (href, label, valueHtml, title) => `<a href="${href}" title="${title}"><span class="ticker-label">${label}</span> <b>${valueHtml}</b></a>`;

    fetch("/api/indicadores", { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then((response) => (response.ok ? response.json() : Promise.reject()))
      .then((data) => {
        const items = [];
        if (data.bitcoin) {
          items.push(item("/calculadoras/moedas", "₿ Bitcoin", money(data.bitcoin.value, 0) + change(data.bitcoin.changePercent), "Preço do Bitcoin em reais (AwesomeAPI), variação nas últimas 24 horas"));
        }
        if (data.cdi) {
          items.push(item("/calculadoras/correcao-monetaria", "CDI", `${number(data.cdi.yearly)}% a.a.`, `CDI ao ano (Banco Central), ${data.cdi.date}`));
        }
        const fuelNames = { gasoline: "⛽ Gasolina", ethanol: "Etanol", diesel: "Diesel S10" };
        for (const [key, label] of Object.entries(fuelNames)) {
          const fuel = data.fuel?.prices?.[key];
          if (fuel) {
            items.push(item("/calculadoras/alcool-gasolina", label, money(fuel.value) + change(fuel.changePercent), `Preço médio no Brasil (ANP), semana até ${data.fuel.week}; variação sobre a semana anterior`));
          }
        }
        if (data.savings) {
          items.push(item("/calculadoras/correcao-monetaria", "Poupança", `${number(data.savings.monthly, 2)}% ao mês`, "Rendimento da poupança no mês (Banco Central)"));
        }
        if (data.focus) {
          items.push(item("/calculadoras/correcao-monetaria", `IPCA esperado ${data.focus.year}`, `${number(data.focus.ipca)}%`, `Mediana do boletim Focus (Banco Central), ${data.focus.date}`));
          items.push(item("/calculadoras/juros-compostos", `Selic no fim de ${data.focus.year}`, `${number(data.focus.selic)}%`, `Expectativa do mercado, boletim Focus (Banco Central), ${data.focus.date}`));
        }
        if (items.length === 0) {
          ticker.closest(".ticker").hidden = true;
          return;
        }
        // Conteúdo duplicado: a faixa rola sem emenda (anda até a metade e recomeça)
        const content = items.join("");
        ticker.innerHTML = `<span class="ticker-group">${content}</span><span class="ticker-group" aria-hidden="true">${content}</span>`;
        ticker.classList.add("is-running");
      })
      .catch(() => {
        ticker.closest(".ticker").hidden = true;
      });
  }

  /* ---------- Calendário de feriados: escolher o estado abre a página dele ---------- */
  const holidayState = document.getElementById("holiday-state");
  if (holidayState) {
    holidayState.addEventListener("change", () => {
      const year = holidayState.dataset.year;
      window.location.href = holidayState.value ? `/feriados/${year}/${holidayState.value}` : `/feriados/${year}`;
    });
  }

  /* ---------- PWA: instala o service worker do site (public/sw.js) ---------- */
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(() => {
        // Sem service worker o site funciona normalmente, só não abre offline
      });
    });
  }

  /* ---------- Formulário de contato ---------- */
  const contactSubject = document.getElementById("contact-subject");
  if (contactSubject) {
    const toolField = document.getElementById("contact-tool-field");
    contactSubject.addEventListener("change", () => {
      toolField.hidden = contactSubject.value !== "erro";
    });
    const contactMessage = document.getElementById("contact-message");
    contactMessage.addEventListener("input", () => {
      document.getElementById("contact-counter").textContent = `${contactMessage.value.length} / 2000`;
    });
  }

  /* ---------- Evita envio duplicado de formulários ---------- */
  document.querySelectorAll("form[method='post']").forEach((form) => {
    form.addEventListener("submit", () => {
      const submitButton = form.querySelector("button[type='submit']");
      if (submitButton) {
        submitButton.disabled = true;
      }
    });
  });
  window.addEventListener("pageshow", () => {
    document.querySelectorAll("button[type='submit']").forEach((button) => { button.disabled = false; });
  });
})();
