// Vibe2000 - painel → Visitas: gráficos (Chart.js) e o "online agora", que atualiza sozinho a cada 10 s.
// Mesmo modelo do painel do Pote Político.

(() => {
  "use strict";

  // PWA do painel: service worker próprio (public/sw-painel.js), separado do site
  // (try: se o navegador recusar, o resto do painel, como o menu, continua funcionando)
  try {
    navigator.serviceWorker?.register("/sw-painel.js", { scope: "/painel" }).catch(() => {});
  } catch {
    // sem app instalável neste navegador
  }

  // Menu sanduíche do celular: abre e fecha o menu; fecha ao escolher um item, tocar fora ou apertar Esc
  const menuButton = document.querySelector(".admin-menu-button");
  const topbar = document.querySelector(".admin-topbar");
  if (menuButton && topbar) {
    const setMenuOpen = (isOpen) => {
      topbar.classList.toggle("is-menu-open", isOpen);
      menuButton.setAttribute("aria-expanded", String(isOpen));
      menuButton.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
    };
    menuButton.addEventListener("click", () => setMenuOpen(!topbar.classList.contains("is-menu-open")));
    document.getElementById("admin-menu").addEventListener("click", (clickEvent) => {
      if (clickEvent.target.closest("a")) {
        setMenuOpen(false);
      }
    });
    document.addEventListener("click", (clickEvent) => {
      if (!topbar.contains(clickEvent.target)) {
        setMenuOpen(false);
      }
    });
    document.addEventListener("keydown", (keyEvent) => {
      if (keyEvent.key === "Escape") {
        setMenuOpen(false);
      }
    });
  }

  // Formulários com data-confirm (ex.: Atualizar site) pedem confirmação antes de enviar
  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (submitEvent) => {
      if (!window.confirm(form.dataset.confirm)) {
        submitEvent.preventDefault();
        return;
      }
      const submitButton = form.querySelector("button[type='submit']");
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "atualizando… não feche a página";
      }
    });
  });

  const dataElement = document.getElementById("visits-data");
  if (!dataElement) {
    return;
  }
  const visitsData = JSON.parse(dataElement.textContent);

  const COLORS = { primary: "#0e6b4f", secondary: "#3a6fd8", tertiary: "#d98a00", text: "#5b6b63", grid: "#d7ded9", ink: "#17221d" };
  const LIVE_REFRESH_MILLISECONDS = 10000;
  const LIVE_HISTORY_POINTS = 90; // 15 minutos
  const numberFormat = new Intl.NumberFormat("pt-BR");
  const weekdayNames = ["dom", "seg", "ter", "qua", "qui", "sex", "sáb"];
  const charts = {};

  // Média móvel de 7 dias: a tendência sem o sobe e desce do dia a dia
  function movingAverage(values) {
    return values.map((_, index) => {
      if (index < 6) {
        return null;
      }
      const windowValues = values.slice(index - 6, index + 1);
      return Math.round((windowValues.reduce((sum, value) => sum + value, 0) / 7) * 10) / 10;
    });
  }

  function timeNow() {
    return new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  }

  function dayLabel(isoDate) {
    const [, month, day] = isoDate.split("-");
    return `${day}/${month}`;
  }

  function drawCharts() {
    const Chart = window.Chart;
    Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";
    Chart.defaults.color = COLORS.text;
    Chart.defaults.borderColor = COLORS.grid;

    const baseOptions = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 400 },
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (context) => ` ${context.dataset.label}: ${context.parsed.y === null ? "—" : numberFormat.format(context.parsed.y)}` } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14 } },
        y: { beginAtZero: true, grid: { color: COLORS.grid + "88" }, border: { display: false }, ticks: { precision: 0, callback: (value) => numberFormat.format(value) } },
      },
    };
    const line = (color, extra = {}) => ({ borderColor: color, backgroundColor: color, borderWidth: 2, tension: 0.3, pointRadius: 0, pointHoverRadius: 5, ...extra });

    // Visitas por dia
    const days = visitsData.days;
    const visitors = days.map((day) => day.visitors);
    charts.days = new Chart(document.getElementById("chart-days"), {
      type: "line",
      data: {
        labels: days.map((day) => dayLabel(day.date)),
        datasets: [
          line(COLORS.primary, { label: "Visitantes", data: visitors, fill: true, backgroundColor: "rgba(14, 107, 79, .12)", pointRadius: days.length <= 31 ? 3 : 0 }),
          line(COLORS.ink, { label: "Média de 7 dias", data: movingAverage(visitors), borderDash: [6, 5] }),
          line(COLORS.secondary, { label: "Páginas vistas", data: days.map((day) => day.page_views) }),
        ],
      },
      options: {
        ...baseOptions,
        plugins: {
          ...baseOptions.plugins,
          tooltip: {
            callbacks: {
              ...baseOptions.plugins.tooltip.callbacks,
              title: (items) => {
                const isoDate = days[items[0].dataIndex].date;
                return `${weekdayNames[new Date(`${isoDate}T12:00`).getDay()]}, ${dayLabel(isoDate)}`;
              },
              afterBody: (items) => {
                const newVisitors = days[items[0].dataIndex].new_visitors;
                return newVisitors ? `  ${numberFormat.format(newVisitors)} novos` : "";
              },
            },
          },
        },
      },
    });

    // Últimos 12 meses (o mês atual ganha a projeção em barra tracejada)
    const months = visitsData.months;
    const lastMonthIndex = months.length - 1;
    charts.months = new Chart(document.getElementById("chart-months"), {
      type: "bar",
      data: {
        labels: months.map((month) => month.label),
        datasets: [
          { label: "Visitantes", data: months.map((month) => month.visitors), backgroundColor: COLORS.primary, borderRadius: 4, stack: "total", maxBarThickness: 34 },
          {
            label: "Projeção (a mais até o fim do mês)",
            data: months.map((month, index) => (index === lastMonthIndex && visitsData.projection ? Math.max(0, visitsData.projection - month.visitors) : null)),
            backgroundColor: "rgba(14, 107, 79, .18)", borderColor: COLORS.primary, borderWidth: 1.5, borderDash: [4, 3], borderRadius: 4, stack: "total", maxBarThickness: 34,
          },
        ],
      },
      options: { ...baseOptions, scales: { ...baseOptions.scales, x: { ...baseOptions.scales.x, stacked: true }, y: { ...baseOptions.scales.y, stacked: true } } },
    });

    // Por hora: hoje (até a hora atual) × ontem
    charts.hours = new Chart(document.getElementById("chart-hours"), {
      type: "line",
      data: {
        labels: Array.from({ length: 24 }, (_, hour) => `${hour}h`),
        datasets: [
          line(COLORS.primary, { label: "Hoje", data: visitsData.hours.today.map((value, hour) => (hour <= visitsData.hourNow ? value : null)), pointRadius: 2 }),
          line(COLORS.tertiary, { label: "Ontem", data: visitsData.hours.yesterday, borderDash: [5, 4] }),
        ],
      },
      options: baseOptions,
    });

    // Ao vivo: vai somando pontos enquanto a página está aberta
    charts.live = new Chart(document.getElementById("chart-live"), {
      type: "line",
      data: { labels: [timeNow()], datasets: [line(COLORS.primary, { label: "Online", data: [visitsData.online], fill: true, backgroundColor: "rgba(14, 107, 79, .12)", stepped: true, tension: 0 })] },
      options: { ...baseOptions, animation: false, scales: { x: { display: false }, y: { display: false, beginAtZero: true, suggestedMax: 3 } } },
    });
  }

  /* ---------- Online agora ---------- */
  const liveCard = document.getElementById("live-card");
  let previousOnlineCount = visitsData.online;

  function escapeHtml(text) {
    return String(text).replace(/[&<>"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[character]));
  }

  async function refreshOnline() {
    try {
      const response = await fetch("/painel/online", { cache: "no-store", credentials: "same-origin" });
      if (response.status === 401) {
        window.location.href = "/painel";
        return;
      }
      if (!response.ok) {
        return;
      }
      const online = await response.json();
      document.getElementById("live-now").textContent = numberFormat.format(online.now);
      document.getElementById("live-half-hour").textContent = numberFormat.format(online.half_hour);
      document.getElementById("live-time").textContent = online.time;
      const pages = Object.entries(online.pages);
      document.getElementById("live-pages").innerHTML = pages.length
        ? pages.map(([page, count]) => `<li><span>${escapeHtml(page)}</span><b>${count}</b></li>`).join("")
        : '<li class="muted">Ninguém no site agora.</li>';

      if (online.now !== previousOnlineCount) {
        liveCard.classList.remove("changed");
        void liveCard.offsetWidth; // reinicia a animação
        liveCard.classList.add("changed");
        previousOnlineCount = online.now;
      }

      const liveChart = charts.live;
      if (liveChart) {
        liveChart.data.labels.push(timeNow());
        liveChart.data.datasets[0].data.push(online.now);
        if (liveChart.data.labels.length > LIVE_HISTORY_POINTS) {
          liveChart.data.labels.shift();
          liveChart.data.datasets[0].data.shift();
        }
        liveChart.update();
      }
    } catch {
      // Sem conexão: tenta de novo no próximo ciclo
    }
  }

  // Só atualiza com a aba visível
  let liveTimer = null;
  function restartLiveTimer() {
    clearInterval(liveTimer);
    if (document.visibilityState === "visible") {
      liveTimer = setInterval(refreshOnline, LIVE_REFRESH_MILLISECONDS);
    }
  }
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      refreshOnline();
    }
    restartLiveTimer();
  });
  restartLiveTimer();

  // O Chart.js vem do CDN: se não carregar em ~10 s, o painel fica só com os números
  let chartAttempts = 0;
  function startCharts() {
    if (window.Chart) {
      drawCharts();
    } else if (++chartAttempts < 200) {
      setTimeout(startCharts, 50);
    }
  }
  startCharts();
})();
