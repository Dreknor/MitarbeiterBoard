/*
 * Klassenraum-Tools – seltene Besuche in den Lautstärke-Szenen (Tiere, Himmelserscheinungen).
 *
 * Ein Besuch kommt nur, wenn es (weitgehend) ruhig ist – der erste nach 12–25 s, danach etwa
 * alle 30–60 s. Bei leichter Unruhe läuft die Wartezeit halb so schnell. Wird es laut, ziehen
 * sich Besucher zurück (das regelt jeder Besuch selbst über zustand.unruhe).
 *
 * Eine Art ist eine Fabrik () => Besuch; ein Besuch ist ein Objekt mit
 *   ebene: string                     in welcher Zeichenebene der Szene er erscheint
 *   schritt(dt, zustand): boolean     false = Besuch ist vorbei
 *   zeichne(ctx, zustand)
 */

const zwischen = (a, b) => a + Math.random() * (b - a);

export class Besuchsplaner {
    /**
     * @param {Object<string, function(): object>} arten
     * @param {{erstes?: number[], abstand?: number[], ruheGrenze?: number, manuell?: string[]}} optionen
     *        Zeiten in Sekunden; „manuell“ = Arten, die nie zufällig kommen, nur über starte()
     */
    constructor(arten, {erstes = [12, 25], abstand = [30, 60], ruheGrenze = 0.2, manuell = []} = {}) {
        this.arten = arten;
        this.zufaellige = Object.keys(arten).filter((name) => !manuell.includes(name));
        this.abstand = abstand;
        this.ruheGrenze = ruheGrenze;
        this.warten = zwischen(...erstes);
        this.aktive = [];
        this.zuletzt = null;
    }

    starte(name) {
        const fabrik = this.arten[name];
        if (!fabrik) return false;
        this.aktive.push(fabrik());
        this.zuletzt = name;
        return true;
    }

    schritt(dt, zustand) {
        this.aktive = this.aktive.filter((besuch) => besuch.schritt(dt, zustand) !== false);

        if (zustand.unruhe < this.ruheGrenze) this.warten -= dt;
        else if (zustand.unruhe < 0.5) this.warten -= dt / 2;
        if (this.warten <= 0 && this.aktive.length === 0) {
            const namen = this.zufaellige.filter((n) => n !== this.zuletzt || this.zufaellige.length === 1);
            this.starte(namen[Math.floor(Math.random() * namen.length)]);
            this.warten = zwischen(...this.abstand);
        }
    }

    zeichne(ctx, ebene, zustand) {
        this.aktive.forEach((besuch) => {
            if (besuch.ebene === ebene) {
                ctx.save();
                besuch.zeichne(ctx, zustand);
                ctx.restore();
            }
        });
    }
}

/** Sanftes Ein-/Ausblenden: Wert läuft gegen ziel (0…1). */
export function blende(wert, ziel, dt, tempo = 0.6) {
    return wert + Math.max(-dt * tempo, Math.min(dt * tempo, ziel - wert));
}
