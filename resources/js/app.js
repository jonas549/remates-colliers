import Alpine from 'alpinejs';
import acceso from './modulos/acceso';
import registro from './modulos/registro';

Alpine.data('acceso', acceso);
Alpine.data('registro', registro);

window.Alpine = Alpine;
Alpine.start();
