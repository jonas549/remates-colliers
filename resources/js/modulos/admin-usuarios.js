// Admin · Usuarios internos (QA del 20/09, punto 2). El alta y la edición se envían como formularios normales;
// restablecer clave, desbloquear y cerrar sesiones son endpoints JSON que ya existían (Bloque D).
export default ({ usuarios }) => ({
    usuarios,
    form: false,
    editando: null,
    enviando: false,

    async pedir(url, metodo = 'POST') {
        this.enviando = true;
        try {
            const r = await fetch(url, {
                method: metodo,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            const esJson = (r.headers.get('content-type') || '').includes('application/json');
            const datos = esJson ? await r.json().catch(() => ({})) : {};
            if (!r.ok || !esJson) {
                alert(datos.mensaje || datos.message || 'No se pudo completar la acción: recarga la página e inténtalo de nuevo.');
                return null;
            }
            return datos;
        } catch (e) {
            alert('Sin conexión. Revisa e inténtalo de nuevo.');
            return null;
        } finally {
            this.enviando = false;
        }
    },

    async restablecer(u) {
        if (!confirm('¿Generar una clave temporal para ' + u.nombre + '? Se cerrarán sus sesiones.')) return;
        const datos = await this.pedir(u.urls.clave);
        if (!datos) return;
        // La clave se muestra una sola vez: queda en pantalla hasta que la copien.
        window.prompt('Clave temporal de ' + u.email + ' (se muestra una sola vez). Cópiala y entrégasela por un canal seguro:', datos.clave_temporal);
    },

    async desbloquear(u) {
        const datos = await this.pedir(u.urls.desbloquear);
        if (datos) window.location.reload();
    },

    async cerrarSesiones(u) {
        if (!confirm('¿Cerrar todas las sesiones de ' + u.nombre + '?')) return;
        const datos = await this.pedir(u.urls.sesiones, 'DELETE');
        if (datos) alert('Sesiones cerradas: ' + datos.sesiones_cerradas);
    },
});
