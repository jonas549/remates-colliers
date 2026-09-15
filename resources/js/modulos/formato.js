// Formatos compartidos por las pantallas (idénticos a los del prototipo).

export const clp = (n) => '$' + Math.round(n).toLocaleString('es-CL');

export const dos = (n) => String(n).padStart(2, '0');

export function partes(ms) {
    const s = Math.max(0, Math.floor(ms / 1000));
    return { d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 };
}

// "2d 05:00:00" o "00:42:00"
export function cuentaRegresiva(ms) {
    const p = partes(ms);
    return (p.d > 0 ? p.d + 'd ' : '') + dos(p.h) + ':' + dos(p.m) + ':' + dos(p.s);
}
