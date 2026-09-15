import Alpine from 'alpinejs';
import acceso from './modulos/acceso';

Alpine.data('acceso', acceso);

window.Alpine = Alpine;
Alpine.start();
