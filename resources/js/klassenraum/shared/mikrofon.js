/*
 * Klassenraum-Tools – Raumlautstärke über das Mikrofon.
 *
 * Das Signal wird ausschließlich live im Browser ausgewertet (Web Audio AnalyserNode):
 * keine Aufnahme, keine Speicherung, keine Übertragung.
 */

export class MikrofonFehler extends Error {
    constructor(grund, ursache) {
        super(grund);
        this.grund = grund; // 'unsicher' | 'nicht-verfuegbar' | 'verweigert' | 'kein-geraet' | 'unbekannt'
        this.ursache = ursache;
    }
}

export const MIKROFON_MELDUNGEN = {
    unsicher: 'Das Mikrofon funktioniert nur über eine sichere Verbindung (https).',
    'nicht-verfuegbar': 'Dieser Browser kann nicht auf das Mikrofon zugreifen.',
    verweigert: 'Kein Mikrofonzugriff. Bitte den Zugriff in der Adressleiste des Browsers erlauben.',
    'kein-geraet': 'Es wurde kein Mikrofon gefunden.',
    unbekannt: 'Das Mikrofon konnte nicht gestartet werden.',
};

export class Mikrofon {
    constructor() {
        this.aktiv = false;
        this.stream = null;
        this.audio = null;
        this.analyser = null;
        this.puffer = null;
        this.beiEnde = null;
    }

    /**
     * @param {{echoUnterdrueckung?: boolean}} optionen  true, wenn die Seite selbst Ton abspielt
     *        (Hintergrundmusik): Der Browser rechnet die eigene Wiedergabe dann aus dem
     *        Mikrofonsignal heraus, damit die Musik nicht als Lärm zählt.
     */
    async starte({echoUnterdrueckung = false} = {}) {
        if (this.aktiv) return;

        if (!window.isSecureContext) throw new MikrofonFehler('unsicher');
        if (!navigator.mediaDevices?.getUserMedia) throw new MikrofonFehler('nicht-verfuegbar');

        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    // Rohsignal: automatische Pegelanpassung würde Lärm "wegregeln"
                    echoCancellation: echoUnterdrueckung,
                    noiseSuppression: false,
                    autoGainControl: false,
                },
                video: false,
            });
        } catch (fehler) {
            const name = fehler?.name;
            if (name === 'NotAllowedError' || name === 'SecurityError') throw new MikrofonFehler('verweigert', fehler);
            if (name === 'NotFoundError' || name === 'OverconstrainedError') throw new MikrofonFehler('kein-geraet', fehler);
            throw new MikrofonFehler('unbekannt', fehler);
        }

        const AudioKontext = window.AudioContext || window.webkitAudioContext;
        this.audio = new AudioKontext();
        if (this.audio.state === 'suspended') {
            try { await this.audio.resume(); } catch (_) { /* wird beim nächsten Klick erneut versucht */ }
        }

        const quelle = this.audio.createMediaStreamSource(stream);
        this.analyser = this.audio.createAnalyser();
        this.analyser.fftSize = 2048;
        this.analyser.smoothingTimeConstant = 0;
        quelle.connect(this.analyser);

        this.puffer = new Float32Array(this.analyser.fftSize);
        this.stream = stream;
        this.aktiv = true;

        stream.getAudioTracks().forEach((spur) => {
            spur.addEventListener('ended', () => {
                this.stoppe();
                this.beiEnde?.();
            });
        });
    }

    stoppe() {
        this.stream?.getTracks().forEach((spur) => spur.stop());
        this.audio?.close().catch(() => {});
        this.stream = null;
        this.audio = null;
        this.analyser = null;
        this.aktiv = false;
    }

    /**
     * Aktueller Pegel auf einer Skala von 0 bis 100.
     * Die Empfindlichkeit (0–100) verschiebt das untere Ende der Skala:
     * höher = leise Geräusche zeigen mehr Ausschlag.
     */
    messe(empfindlichkeit = 50) {
        if (!this.aktiv || !this.analyser) return 0;

        this.analyser.getFloatTimeDomainData(this.puffer);
        let summe = 0;
        for (let i = 0; i < this.puffer.length; i++) {
            summe += this.puffer[i] * this.puffer[i];
        }
        const rms = Math.sqrt(summe / this.puffer.length);
        const dbfs = 20 * Math.log10(Math.max(rms, 1e-8));

        const untergrenze = -70 - (empfindlichkeit - 50) * 0.4; // -90 … -50 dBFS
        const spanne = 55;
        return Math.min(100, Math.max(0, ((dbfs - untergrenze) / spanne) * 100));
    }
}
