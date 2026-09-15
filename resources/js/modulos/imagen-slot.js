// Encuadre "cover" idéntico al <image-slot> del prototipo: imagen centrada con
// translate(-50%, -50%) y ancho/alto en porcentaje del marco. Con object-fit el resultado es el mismo
// geométricamente, pero Chrome rasteriza distinto; esto deja las fotos 1:1.
// Sin JavaScript sigue funcionando el object-fit del CSS.

function encuadrar(img) {
    const marco = img.parentElement;
    if (!img.naturalWidth || !marco.clientWidth || !marco.clientHeight) return;
    const aspectoMarco = marco.clientWidth / marco.clientHeight;
    const aspectoImagen = img.naturalWidth / img.naturalHeight;
    if (aspectoMarco > aspectoImagen) {
        img.style.width = '100%';
        img.style.height = (aspectoMarco / aspectoImagen) * 100 + '%';
    } else {
        img.style.height = '100%';
        img.style.width = (aspectoImagen / aspectoMarco) * 100 + '%';
    }
    img.classList.add('es-encuadrada');
}

const observador = new ResizeObserver((entradas) => {
    for (const entrada of entradas) {
        const img = entrada.target.querySelector('img');
        if (img) encuadrar(img);
    }
});

function preparar(raiz) {
    for (const img of raiz.querySelectorAll('.imagen-slot__marco img:not([data-encuadre])')) {
        img.dataset.encuadre = '';
        if (img.complete) encuadrar(img);
        img.addEventListener('load', () => encuadrar(img));
        observador.observe(img.parentElement);
    }
}

export function iniciarImagenesSlot() {
    preparar(document);
    new MutationObserver(() => preparar(document)).observe(document.body, { childList: true, subtree: true });
}
