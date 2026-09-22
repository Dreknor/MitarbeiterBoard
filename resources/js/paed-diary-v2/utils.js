/**
 * PaedDiary v2 – Hilfsfunktionen
 *
 * Reine Utility-Funktionen ohne Seiteneffekte.
 * Werden von Store und Components importiert.
 */

/**
 * Gibt den Montag (Wochenbeginn) zum übergebenen Datum zurück.
 * @param {Date} d
 * @returns {Date}
 */
export function startOfWeek(d) {
    const dt = new Date(d);
    const wd = dt.getDay();
    const diff = (wd === 0 ? -6 : 1 - wd);
    dt.setDate(dt.getDate() + diff);
    dt.setHours(0, 0, 0, 0);
    return dt;
}

/**
 * Formatiert ein Date-Objekt als YYYY-MM-DD.
 * @param {Date} d
 * @returns {string}
 */
export function formatDate(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Addiert x Tage zu einem Datum.
 * @param {Date} d
 * @param {number} x
 * @returns {Date}
 */
export function addDays(d, x) {
    const n = new Date(d);
    n.setDate(n.getDate() + x);
    return n;
}

/**
 * Escaped HTML-Sonderzeichen in einem String.
 * @param {string} str
 * @returns {string}
 */
export function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, s => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[s]));
}

/**
 * Kürzt einen String auf maximal len Zeichen.
 * @param {string} str
 * @param {number} len
 * @returns {string}
 */
export function trimText(str, len) {
    if (!str) return '';
    return str.length <= len ? str : str.slice(0, len - 1) + '…';
}

/**
 * Formatiert Zeit: "HH:MM:SS" → "HH:MM" oder ISO-DateTime → "HH:MM".
 * @param {string} timeStr
 * @returns {string}
 */
export function formatTime(timeStr) {
    if (!timeStr) return '';
    if (timeStr.includes('T')) {
        try {
            const date = new Date(timeStr);
            if (!isNaN(date.getTime())) {
                return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            }
        } catch (_) { /* fallthrough */ }
    }
    if (timeStr.includes(':')) {
        const parts = timeStr.split(':');
        if (parts.length >= 2) return `${parts[0]}:${parts[1]}`;
    }
    return timeStr;
}

/**
 * Gibt das CSRF-Token aus dem Meta-Tag zurück.
 * @returns {string}
 */
export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Berechnet die Helligkeit einer Hex-Farbe (0-255).
 * @param {string} hexColor
 * @returns {number}
 */
export function getBrightness(hexColor) {
    const hex = (hexColor || '').replace('#', '');
    if (hex.length < 6) return 255;
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    return ((r * 299) + (g * 587) + (b * 114)) / 1000;
}

