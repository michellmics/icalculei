// Vibe2000 - service worker do PAINEL (PWA "Painel V2K", escopo "/painel").
//
// O painel mostra dados de visitas e mensagens: nada disso é guardado no aparelho.
// Tudo vem sempre da internet; sem conexão, aparece só o aviso de offline.
//
// Mudou a lógica deste arquivo? Aumente o CACHE_VERSION.

const CACHE_VERSION = "v1";
const PANEL_CACHE = `vibe2000-painel-${CACHE_VERSION}`;
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
        .filter((cacheName) => cacheName.startsWith("vibe2000-painel-") && cacheName !== PANEL_CACHE)
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
