const CACHE_NAME = 'cmih-portal-20260715-notifications';
const CORE_ASSETS = [
  '/manifest.json',
  '/images/logo/favicon.png',
  '/images/logo/icon-192.png',
  '/images/logo/icon-512.png'
];

const DEFAULT_NOTIFICATION_URL = '/portal/notifications';

const toAbsoluteUrl = (url) => {
  try {
    return new URL(url || DEFAULT_NOTIFICATION_URL, self.location.origin).href;
  } catch (error) {
    return new URL(DEFAULT_NOTIFICATION_URL, self.location.origin).href;
  }
};

const normalizeNotificationPayload = (payload = {}) => ({
  title: payload.title || 'CMIH Africa',
  body: payload.body || payload.message || '',
  icon: payload.icon || '/images/logo/icon-192.png',
  badge: payload.badge || '/images/logo/icon-192.png',
  tag: payload.tag || `cmih-${Date.now()}`,
  data: {
    url: toAbsoluteUrl(payload.url),
  },
  renotify: payload.renotify !== false,
});

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(CORE_ASSETS).catch((error) => {
        console.warn('PWA service worker partial asset caching warning:', error);
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
          return null;
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request)
        .then((response) => {
          if (response.status === 200 && (
            event.request.url.includes('tile.openstreetmap.org') ||
            event.request.url.includes('fonts.gstatic.com') ||
            event.request.url.includes('/build/assets/') ||
            event.request.url.includes('/images/')
          )) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          return new Response('Offline resource not available', {
            status: 503,
            statusText: 'Service Unavailable',
          });
        });
    })
  );
});

self.addEventListener('push', (event) => {
  let payload = {};

  if (event.data) {
    try {
      payload = event.data.json();
    } catch (error) {
      payload = { title: 'CMIH Africa', body: event.data.text() };
    }
  }

  const notification = normalizeNotificationPayload(payload);
  event.waitUntil(
    self.registration.showNotification(notification.title, {
      body: notification.body,
      icon: notification.icon,
      badge: notification.badge,
      tag: notification.tag,
      renotify: notification.renotify,
      data: notification.data,
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = toAbsoluteUrl(event.notification.data && event.notification.data.url);

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client && new URL(client.url).origin === self.location.origin) {
          client.focus();
          if ('navigate' in client) {
            return client.navigate(targetUrl);
          }
          return null;
        }
      }

      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }

      return null;
    })
  );
});
