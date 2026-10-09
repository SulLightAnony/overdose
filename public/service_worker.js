/**
 * ====================================================================================
 * SERVICE WORKER: Background Push Notification Listener
 * FILE LOCATION: public/service_worker.js
 * ====================================================================================
 * 
 * TUJUAN (PURPOSE):
 * File Service Worker ini berjalan di latar belakang (background process) browser/Sistem
 * Operasi pengguna. Berfungsi untuk mendengarkan (listen) event push notification dari
 * Web Push Protocol/VAPID server dan menampilkan pop-up notifikasi OS/HP bahkan ketika
 * aplikasi web 'Overdose' sedang ditutup atau diminimalkan (closed browser support).
 * 
 * EVENT LISTENERS:
 * 1. 'push':
 *    - Menangkap payload JSON yang dikirimkan oleh Push Service.
 *    - Parsing data payload (title, message/body, icon, badge, targetUrl).
 *    - Memanggil self.registration.showNotification(title, options) untuk memicu pop-up OS.
 * 
 * 2. 'notificationclick':
 *    - Menangkap aksi saat pengguna mengklik pop-up notifikasi yang muncul di perangkat.
 *    - Menutup pop-up notifikasi (event.notification.close()).
 *    - Memeriksa apakah tab aplikasi web sudah terbuka:
 *      * Jika sudah terbuka: Lakukan focus pada tab tersebut dan arahkan ke targetUrl.
 *      * Jika belum terbuka: Buka window/tab baru menuju targetUrl (Detail Tugas/Materi).
 * 
 * CARA KERJA & RELASI DENGAN FILE LAIN:
 * - Didaftarkan oleh modul JS client-side (public/js/modules/configurations.js / notifications.js)
 *   menggunakan navigator.serviceWorker.register('/public/service_worker.js').
 * - Bekerja secara independen dari siklus hidup DOM/halaman web standar.
 */

self.addEventListener('push', function(event) {
    let payload = {};
    const scopeUrl = self.registration.scope;
    const defaultAppUrl = new URL('../', scopeUrl).href;
    const defaultIconUrl = new URL('assets/img/logo.png', scopeUrl).href;

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            payload = {
                title: 'Notifikasi Sistem',
                body: event.data.text(),
                url: defaultAppUrl
            };
        }
    }

    const title = payload.title || 'Overdose Academic';
    const options = {
        body: payload.body || payload.message || 'Anda memiliki pemberitahuan baru.',
        icon: defaultIconUrl,
        badge: defaultIconUrl,
        vibrate: [200, 100, 200],
        data: {
            url: payload.url || defaultAppUrl
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const targetUrl = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : new URL('../', self.registration.scope).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(windowClients) {
                for (let i = 0; i < windowClients.length; i++) {
                    const client = windowClients[i];
                    if ('focus' in client && 'navigate' in client) {
                        return client.navigate(targetUrl).then(function(navigatedClient) {
                            return navigatedClient.focus();
                        });
                    }
                }
                
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});