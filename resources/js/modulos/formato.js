// Formatos compartidos por las pantallas (idénticos a los del prototipo). En inglés, miles con coma (20/10).
import { locale } from './textos';

export const clp = (n) => '$' + Math.round(n).toLocaleString(locale());

// Sobre el precio base (QA 21/10, 4.2): con un decimal («+0,3%»), porque al principio del remate el entero redondea a
// «+0%». Si aun así redondea a cero estando por encima, el monto («+$100.000»).
export function sobreBase(actual, base) {
    if (!base) return '';
    const pct = Math.round((actual / base - 1) * 1000) / 10;
    if (pct === 0 && actual > base) return '+' + clp(actual - base);
    return '+' + pct.toLocaleString(locale(), { maximumFractionDigits: 1 }) + '%';
}

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
