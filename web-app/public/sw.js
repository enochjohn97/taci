// public/sw.js - Service Worker for PWA

const CACHE_VERSION = "app-v1";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;

// Files to cache on install
const STATIC_ASSETS = [
  "/",
  "/dashboard",
  "/offline.html",
  "/css/bootstrap.min.css",
  "/css/styles.css",
  "/js/bootstrap.bundle.min.js",
  "/images/icon-192x192.png",
];

// Install event - cache static assets
self.addEventListener("install", (event) => {
  console.log("[Service Worker] Installing...");
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then((cache) => {
        console.log("[Service Worker] Caching static assets");
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting()),
  );
});

// Activate event - cleanup old caches
self.addEventListener("activate", (event) => {
  console.log("[Service Worker] Activating...");
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (
              cacheName !== STATIC_CACHE &&
              cacheName !== DYNAMIC_CACHE &&
              cacheName.startsWith(CACHE_VERSION)
            ) {
              console.log("[Service Worker] Deleting old cache:", cacheName);
              return caches.delete(cacheName);
            }
          }),
        );
      })
      .then(() => self.clients.claim()),
  );
});

// Fetch event - Network first, fallback to cache strategy
self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Skip API calls to external services and certain paths
  if (
    url.pathname.includes("/api/external") ||
    url.pathname.includes("/webhook")
  ) {
    return;
  }

  // Network-first strategy for API calls
  if (url.pathname.includes("/api/")) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Clone the response
          const clonedResponse = response.clone();
          // Cache successful responses
          if (response.ok) {
            caches
              .open(DYNAMIC_CACHE)
              .then((cache) => cache.put(request, clonedResponse));
          }
          return response;
        })
        .catch(() => {
          // Fallback to cache if network fails
          return caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Return offline page if available
            if (request.mode === "navigate") {
              return caches.match("/offline.html");
            }
            return new Response("Offline - Resource not available", {
              status: 503,
              statusText: "Service Unavailable",
            });
          });
        }),
    );
    return;
  }

  // Cache-first strategy for static assets
  event.respondWith(
    caches.match(request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(request)
        .then((response) => {
          // Don't cache non-successful responses
          if (
            !response ||
            response.status !== 200 ||
            response.type !== "basic"
          ) {
            return response;
          }
          const clonedResponse = response.clone();
          caches
            .open(DYNAMIC_CACHE)
            .then((cache) => cache.put(request, clonedResponse));
          return response;
        })
        .catch(() => {
          // Return offline page for navigation requests
          if (request.mode === "navigate") {
            return caches.match("/offline.html");
          }
          return new Response("Offline - Resource not available", {
            status: 503,
            statusText: "Service Unavailable",
          });
        });
    }),
  );
});

// Handle messages from clients
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

// Background sync for offline actions
self.addEventListener("sync", (event) => {
  console.log("[Service Worker] Background sync:", event.tag);
  if (event.tag === "sync-payments") {
    event.waitUntil(syncPendingPayments());
  }
  if (event.tag === "sync-inventory") {
    event.waitUntil(syncInventoryChanges());
  }
});

async function syncPendingPayments() {
  try {
    const cache = await caches.open(DYNAMIC_CACHE);
    const requests = await cache.keys();
    for (const request of requests) {
      if (request.url.includes("/api/payments") && request.method === "POST") {
        const response = await fetch(request);
        if (response.ok) {
          await cache.delete(request);
        }
      }
    }
  } catch (error) {
    console.error("[Service Worker] Sync error:", error);
  }
}

async function syncInventoryChanges() {
  try {
    const cache = await caches.open(DYNAMIC_CACHE);
    const requests = await cache.keys();
    for (const request of requests) {
      if (request.url.includes("/api/inventory") && request.method === "POST") {
        const response = await fetch(request);
        if (response.ok) {
          await cache.delete(request);
        }
      }
    }
  } catch (error) {
    console.error("[Service Worker] Sync error:", error);
  }
}

// Push notifications
self.addEventListener("push", (event) => {
  console.log("[Service Worker] Push notification received");
  const data = event.data ? event.data.json() : {};
  const options = {
    body: data.body || "New notification",
    icon: "/images/icon-192x192.png",
    badge: "/images/icon-96x96.png",
    tag: data.tag || "notification",
    requireInteraction: data.requireInteraction || false,
    data: {
      url: data.url || "/",
    },
  };

  event.waitUntil(
    self.registration.showNotification(data.title || "APP_NAME", options),
  );
});

// Handle notification clicks
self.addEventListener("notificationclick", (event) => {
  console.log("[Service Worker] Notification clicked");
  event.notification.close();

  event.waitUntil(
    clients.matchAll({ type: "window" }).then((clientList) => {
      // Check if window is already open
      for (let i = 0; i < clientList.length; i++) {
        if (
          clientList[i].url === event.notification.data.url &&
          "focus" in clientList[i]
        ) {
          return clientList[i].focus();
        }
      }
      // Open new window if not found
      if (clients.openWindow) {
        return clients.openWindow(event.notification.data.url);
      }
    }),
  );
});
