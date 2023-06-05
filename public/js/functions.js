function addMinutes(time, minsToAdd) {
    function D(J) {
        return (J < 10 ? '0' : '') + J;
    }

    let piece = time.split(':');
    let mins = piece[0] * 60 + +piece[1] + +minsToAdd;

    return D(mins % (24 * 60) / 60 | 0) + ':' + D(mins % 60);
}
