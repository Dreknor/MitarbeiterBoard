/*
 * Web-Push im MitarbeiterBoard
 *
 * Registriert nur den Service Worker. Die Berechtigung wird NICHT automatisch
 * abgefragt, sondern ausschließlich über „Benachrichtigungen → Einstellungen“
 * (Button „Push auf diesem Gerät aktivieren“) – siehe window.MbPush.
 */
(function () {
    'use strict';

    const unterstuetzt = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    function csrf() {
        const meta = document.querySelector('meta[name=csrf-token]');
        return meta ? meta.getAttribute('content') : '';
    }

    function vapidKey() {
        const meta = document.querySelector('meta[name=vapid-public-key]');
        return meta ? meta.getAttribute('content') : '';
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function senden(methode, url, daten) {
        return fetch(window.location.origin + url, {
            method: methode,
            body: daten ? JSON.stringify(daten) : null,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then((res) => res.json().then((json) => {
            if (!res.ok) {
                throw new Error(json.message || 'Anfrage fehlgeschlagen');
            }
            return json;
        }));
    }

    function registrierung() {
        return navigator.serviceWorker.register(window.location.origin + '/sw.js')
            .then(() => navigator.serviceWorker.ready);
    }

    const MbPush = {
        unterstuetzt: unterstuetzt,

        /**
         * 'nicht-unterstuetzt' | 'blockiert' | 'aktiv' | 'inaktiv'
         */
        status() {
            if (!unterstuetzt) {
                return Promise.resolve('nicht-unterstuetzt');
            }
            if (Notification.permission === 'denied') {
                return Promise.resolve('blockiert');
            }
            return registrierung()
                .then((reg) => reg.pushManager.getSubscription())
                .then((sub) => (sub && Notification.permission === 'granted') ? 'aktiv' : 'inaktiv');
        },

        aktivieren() {
            if (!unterstuetzt) {
                return Promise.reject(new Error('Dieser Browser unterstützt keine Push-Benachrichtigungen.'));
            }
            if (!vapidKey()) {
                return Promise.reject(new Error('Push ist auf dem Server nicht eingerichtet.'));
            }
            return Notification.requestPermission()
                .then((ergebnis) => {
                    if (ergebnis !== 'granted') {
                        throw new Error('Die Berechtigung für Benachrichtigungen wurde nicht erteilt.');
                    }
                    return registrierung();
                })
                .then((reg) => reg.pushManager.getSubscription().then((sub) => sub || reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey()),
                })))
                .then((sub) => senden('POST', '/push', sub.toJSON()));
        },

        deaktivieren() {
            if (!unterstuetzt) {
                return Promise.resolve();
            }
            return registrierung()
                .then((reg) => reg.pushManager.getSubscription())
                .then((sub) => {
                    if (!sub) {
                        return null;
                    }
                    const endpoint = sub.endpoint;
                    return sub.unsubscribe().then(() => senden('DELETE', '/push', { endpoint: endpoint }));
                });
        },

        test() {
            return senden('POST', '/push/test');
        },
    };

    window.MbPush = MbPush;

    // Service Worker registrieren, damit bestehende Abos (Klick-Ziele, Aktualisierungen) funktionieren
    if (unterstuetzt) {
        registrierung().catch((err) => console.warn('Service Worker konnte nicht registriert werden', err));
    }
})();
