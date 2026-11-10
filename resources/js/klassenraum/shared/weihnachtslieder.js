/*
 * Klassenraum-Tools – Weihnachtslieder für die Hintergrundmusik in der Adventszeit (shared/musik.js).
 *
 * Nur gemeinfreie Melodien (Volkslieder bzw. 19. Jahrhundert). Notiert in C-Dur:
 *   melodie: [[MIDI-Ton | null (Pause), Dauer in Einheiten], …]   (C5 = 72)
 *   akkorde: [[Akkordname, Dauer in Einheiten], …]               (siehe AKKORDE)
 *   einheit: Sekunden je Einheit (ruhiges Tempo, passend zu den Szenen)
 *   wiederholen: wie oft das Lied am Stück gespielt wird (Standard 1)
 * Melodie und Akkorde sind gleich lang.
 */

// Akkorde: Begleitung (eng um C4) und Basston
export const AKKORDE = {
    C: {toene: [60, 64, 67], bass: 48},
    F: {toene: [57, 60, 65], bass: 53},
    G: {toene: [55, 59, 62], bass: 43},
    G7: {toene: [55, 59, 62, 65], bass: 43},
    D: {toene: [57, 62, 66], bass: 50},
    D7: {toene: [57, 60, 62, 66], bass: 50},
    E: {toene: [56, 59, 64], bass: 52},
    Am: {toene: [57, 60, 64], bass: 45},
};

// Tonnamen (C5 = 72)
const G4 = 67, A4 = 69, B4 = 71, C5 = 72, D5 = 74, E5 = 76, F5 = 77, G5 = 79, A5 = 81, B5 = 83, C6 = 84, D6 = 86, E6 = 88, F6 = 89;

export const WEIHNACHTSLIEDER = [
    {
        name: 'Stille Nacht, heilige Nacht',
        einheit: 0.32, // 6/8, Einheit = Achtel
        melodie: [
            [G5, 3], [A5, 1], [G5, 2], [E5, 6],
            [G5, 3], [A5, 1], [G5, 2], [E5, 6],
            [D6, 4], [D6, 2], [B5, 6],
            [C6, 4], [C6, 2], [G5, 6],
            [A5, 4], [A5, 2], [C6, 3], [B5, 1], [A5, 2],
            [G5, 3], [A5, 1], [G5, 2], [E5, 6],
            [A5, 4], [A5, 2], [C6, 3], [B5, 1], [A5, 2],
            [G5, 3], [A5, 1], [G5, 2], [E5, 6],
            [D6, 4], [D6, 2], [F6, 3], [D6, 1], [B5, 2],
            [C6, 6], [E6, 6],
            [C6, 3], [G5, 1], [E5, 2], [G5, 3], [F5, 1], [D5, 2],
            [C5, 12],
        ],
        akkorde: [
            ['C', 24], ['G7', 12], ['C', 12], ['F', 12], ['C', 12], ['F', 12], ['C', 12],
            ['G7', 12], ['C', 12], ['C', 6], ['G7', 6], ['C', 12],
        ],
    },
    {
        name: 'Morgen kommt der Weihnachtsmann',
        einheit: 0.62, // 4/4, Einheit = Viertel
        melodie: [
            [C5, 1], [C5, 1], [G5, 1], [G5, 1], [A5, 1], [A5, 1], [G5, 2],
            [F5, 1], [F5, 1], [E5, 1], [E5, 1], [D5, 1], [D5, 1], [C5, 2],
            [G5, 1], [G5, 1], [F5, 1], [F5, 1], [E5, 1], [E5, 1], [D5, 2],
            [G5, 1], [G5, 1], [F5, 1], [F5, 1], [E5, 1], [E5, 1], [D5, 2],
            [C5, 1], [C5, 1], [G5, 1], [G5, 1], [A5, 1], [A5, 1], [G5, 2],
            [F5, 1], [F5, 1], [E5, 1], [E5, 1], [D5, 1], [D5, 1], [C5, 2],
        ],
        akkorde: [
            ['C', 4], ['F', 2], ['C', 2], ['F', 2], ['C', 2], ['G', 2], ['C', 2],
            ['C', 2], ['F', 2], ['C', 2], ['G', 2], ['C', 2], ['F', 2], ['C', 2], ['G', 2],
            ['C', 4], ['F', 2], ['C', 2], ['F', 2], ['C', 2], ['G', 2], ['C', 2],
        ],
    },
    {
        name: 'Jingle Bells',
        einheit: 0.42, // 4/4, Einheit = Viertel (ruhig statt flott)
        melodie: [
            [E5, 1], [E5, 1], [E5, 2], [E5, 1], [E5, 1], [E5, 2],
            [E5, 1], [G5, 1], [C5, 1.5], [D5, 0.5], [E5, 4],
            [F5, 1], [F5, 1], [F5, 1.5], [F5, 0.5], [F5, 1], [E5, 1], [E5, 1], [E5, 0.5], [E5, 0.5],
            [E5, 1], [D5, 1], [D5, 1], [E5, 1], [D5, 2], [G5, 2],
            [E5, 1], [E5, 1], [E5, 2], [E5, 1], [E5, 1], [E5, 2],
            [E5, 1], [G5, 1], [C5, 1.5], [D5, 0.5], [E5, 4],
            [F5, 1], [F5, 1], [F5, 1.5], [F5, 0.5], [F5, 1], [E5, 1], [E5, 1], [E5, 0.5], [E5, 0.5],
            [G5, 1], [G5, 1], [F5, 1], [D5, 1], [C5, 4],
        ],
        akkorde: [
            ['C', 16], ['F', 4], ['C', 4], ['D7', 4], ['G', 4],
            ['C', 16], ['F', 4], ['C', 4], ['G7', 4], ['C', 4],
        ],
    },
    {
        name: 'We Wish You a Merry Christmas',
        einheit: 0.5, // 3/4, Einheit = Viertel
        wiederholen: 2,
        melodie: [
            [G4, 1],
            [C5, 1], [C5, 0.5], [D5, 0.5], [C5, 0.5], [B4, 0.5],
            [A4, 1], [A4, 1], [A4, 1],
            [D5, 1], [D5, 0.5], [E5, 0.5], [D5, 0.5], [C5, 0.5],
            [B4, 1], [G4, 1], [G4, 1],
            [E5, 1], [E5, 0.5], [F5, 0.5], [E5, 0.5], [D5, 0.5],
            [C5, 1], [A4, 1], [G4, 0.5], [G4, 0.5],
            [A4, 1], [D5, 1], [B4, 1],
            [C5, 2], [null, 1],
        ].map(([ton, dauer]) => [ton === null ? null : ton + 12, dauer]),
        akkorde: [
            ['G', 1], ['C', 3], ['F', 3], ['D', 3], ['G', 3], ['E', 3], ['Am', 3], ['F', 1], ['G', 2], ['C', 3],
        ],
    },
];
