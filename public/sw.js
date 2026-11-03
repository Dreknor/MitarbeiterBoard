/*
 * Service Worker – Web-Push des MitarbeiterBoards
 */
self.addEventListener('push', function (e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    if (e.data) {
        const msg = e.data.json();
        e.waitUntil(self.registration.showNotification(msg.title, {
            body: msg.body,
            icon: msg.icon,
            tag: msg.tag,
            actions: msg.actions,
            data: msg.data || {},
        }));
    }
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const data = event.notification.data || {};
    // Nur Ziele auf der eigenen Domain öffnen
    let ziel = self.registration.scope;
    try {
        if (data.url) {
            const url = new URL(data.url, self.registration.scope);
            if (url.origin === self.location.origin) {
                ziel = url.href;
            }
        }
    } catch (err) {
        // ungültige URL → Startseite
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenster) {
            for (const client of fenster) {
                if (client.url === ziel && 'focus' in client) {
                    return client.focus();
                }
            }
            for (const client of fenster) {
                if ('navigate' in client && new URL(client.url).origin === self.location.origin) {
                    return client.focus().then(() => client.navigate(ziel));
                }
            }
            return clients.openWindow(ziel);
        })
    );
});
