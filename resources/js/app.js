import Alpine from 'alpinejs';
import acceso from './modulos/acceso';
import registro from './modulos/registro';
import listado from './modulos/listado';
import { iniciarImagenesSlot } from './modulos/imagen-slot';

Alpine.data('acceso', acceso);
Alpine.data('registro', registro);
Alpine.data('listado', listado);

window.Alpine = Alpine;
Alpine.start();
iniciarImagenesSlot();
