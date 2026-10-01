// Vibe2000 - service worker do SITE (PWA "Vibe2000", escopo "/").
//
// Páginas: busca na internet primeiro e guarda uma cópia; sem internet, abre a cópia
// (as calculadoras funcionam offline, porque a conta é feita no navegador).
// CSS, JS e imagens: usa a cópia guardada (o endereço muda a cada versão: ?v=...).
// Nunca guarda: painel, APIs, links curtos (/l/), formulários (POST) e outros sites.
//
// Mudou a lógica deste arquivo? Aumente o CACHE_VERSION para apagar os caches antigos.

const CACHE_VERSION = "v2";
const PAGES_CACHE = `vibe2000-pages-${CACHE_VERSION}`;
const ASSETS_CACHE = `vibe2000-assets-${CACHE_VERSION}`;
const OFFLINE_PAGE = "/offline.html";
const MAX_SAVED_PAGES = 40;
const NEVER_CACHE = /^\/(painel|api\/|l\/|sitemap\.xml|robots\.txt|sw\.js|sw-painel\.js)/;
const STATIC_FILES = /^\/(assets\/|icons\/|favicon\.svg|manifest\.webmanifest)/;

self.addEventListener("install", (installEvent) => {
  installEvent.waitUntil(
    caches.open(PAGES_CACHE).then((cache) => cache.addAll([OFFLINE_PAGE, "/"])).then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (activateEvent) => {
  activateEvent.waitUntil(
    caches.keys()
      .then((cacheNames) => Promise.all(cacheNames
        .filter((cacheName) => cacheName.startsWith("vibe2000-") && ![PAGES_CACHE, ASSETS_CACHE].includes(cacheName))
        .map((cacheName) => caches.delete(cacheName))))
      .then(() => self.clients.claim()),
  );
});

// Guarda no máximo MAX_SAVED_PAGES páginas (apaga as mais antigas)
async function trimPagesCache() {
  const cache = await caches.open(PAGES_CACHE);
  const savedRequests = await cache.keys();
  const removable = savedRequests.filter((request) => !request.url.endsWith(OFFLINE_PAGE));
  for (const request of removable.slice(0, Math.max(0, removable.length - MAX_SAVED_PAGES))) {
    await cache.delete(request);
  }
}

async function pageNetworkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(PAGES_CACHE);
      // Guarda sem o "?utm_source=app", para achar a mesma página depois
      const cleanUrl = new URL(request.url);
      cleanUrl.searchParams.delete("utm_source");
      await cache.put(cleanUrl.toString(), response.clone());
      trimPagesCache();
    }
    return response;
  } catch {
    const cleanUrl = new URL(request.url);
    cleanUrl.searchParams.delete("utm_source");
    return (await caches.match(cleanUrl.toString())) || (await caches.match(OFFLINE_PAGE));
  }
}

async function assetCacheFirst(request) {
  const saved = await caches.match(request);
  if (saved) {
    return saved;
  }
  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(ASSETS_CACHE);
    cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener("fetch", (fetchEvent) => {
  const request = fetchEvent.request;
  const url = new URL(request.url);
  if (request.method !== "GET" || url.origin !== self.location.origin || NEVER_CACHE.test(url.pathname)) {
    return; // o navegador faz o pedido normalmente
  }
  if (request.mode === "navigate") {
    fetchEvent.respondWith(pageNetworkFirst(request));
  } else if (STATIC_FILES.test(url.pathname)) {
    fetchEvent.respondWith(assetCacheFirst(request));
  }
});
