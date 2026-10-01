// iCalculei - service worker do PAINEL (PWA "Painel iCalculei", escopo "/painel").
//
// O painel mostra dados de visitas e mensagens: nada disso é guardado no aparelho.
// Tudo vem sempre da internet; sem conexão, aparece só o aviso de offline.
// Também recebe as notificações push (aviso a cada 1.000 visitantes; ver app/Services/PushNotifier.php).
//
// Mudou a lógica deste arquivo? Aumente o CACHE_VERSION.

const CACHE_VERSION = "v3";
const PANEL_CACHE = `icalculei-painel-${CACHE_VERSION}`;
const OFFLINE_PAGE = "/offline-painel.html";

self.addEventListener("install", (installEvent) => {
  installEvent.waitUntil(
    caches.open(PANEL_CACHE).then((cache) => cache.addAll([OFFLINE_PAGE, "/icons/painel-192.png"])).then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (activateEvent) => {
  activateEvent.waitUntil(
    caches.keys()
      .then((cacheNames) => Promise.all(cacheNames
        .filter((cacheName) => cacheName.startsWith("icalculei-painel-") && cacheName !== PANEL_CACHE)
        .map((cacheName) => caches.delete(cacheName))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (fetchEvent) => {
  // Só as páginas do painel sem internet caem no aviso; o resto segue normal
  if (fetchEvent.request.mode === "navigate") {
    fetchEvent.respondWith(fetch(fetchEvent.request).catch(() => caches.match(OFFLINE_PAGE)));
  }
});

// Notificação push: o servidor manda { title, body, url } criptografado; o navegador já entrega aberto aqui
self.addEventListener("push", (pushEvent) => {
  let message = {};
  try {
    message = pushEvent.data ? pushEvent.data.json() : {};
  } catch {
    message = { body: pushEvent.data ? pushEvent.data.text() : "" };
  }
  pushEvent.waitUntil(self.registration.showNotification(message.title || "iCalculei", {
    body: message.body || "",
    icon: "/icons/painel-192.png",
    data: { url: message.url || "/painel/visitas" },
  }));
});

// Tocou na notificação: volta para o painel já aberto ou abre a página do aviso
self.addEventListener("notificationclick", (clickEvent) => {
  clickEvent.notification.close();
  const url = clickEvent.notification.data?.url || "/painel/visitas";
  clickEvent.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((windows) => {
      const panelWindow = windows.find((client) => new URL(client.url).pathname.startsWith("/painel"));
      if (panelWindow) {
        return panelWindow.navigate(url).then((client) => (client || panelWindow).focus());
      }
      return self.clients.openWindow(url);
    }),
  );
});
