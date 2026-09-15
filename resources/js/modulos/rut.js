// Validación de RUT chileno (formato y dígito verificador), idéntica a la del prototipo.
// La validación definitiva se hace también en el servidor (Bloque D).
export function rutValido(valor) {
    const limpio = String(valor).replace(/[.\-\s]/g, '').toUpperCase();
    if (limpio.length < 8) return false;
    const cuerpo = limpio.slice(0, -1);
    const dv = limpio.slice(-1);
    if (!/^\d+$/.test(cuerpo)) return false;
    let suma = 0;
    let multiplicador = 2;
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += parseInt(cuerpo[i], 10) * multiplicador;
        multiplicador = multiplicador === 7 ? 2 : multiplicador + 1;
    }
    const resto = 11 - (suma % 11);
    const esperado = resto === 11 ? '0' : resto === 10 ? 'K' : String(resto);
    return dv === esperado;
}
