import Alpine from 'alpinejs';
import acceso from './modulos/acceso';
import registro from './modulos/registro';
import listado from './modulos/listado';
import detalleRemate from './modulos/detalle';
import salaPuja from './modulos/sala';
import adminSubastas from './modulos/admin-subastas';
import adminPostores from './modulos/admin-postores';
import { iniciarImagenesSlot } from './modulos/imagen-slot';

Alpine.data('acceso', acceso);
Alpine.data('registro', registro);
Alpine.data('listado', listado);
Alpine.data('detalleRemate', detalleRemate);
Alpine.data('salaPuja', salaPuja);
Alpine.data('adminSubastas', adminSubastas);
Alpine.data('adminPostores', adminPostores);

window.Alpine = Alpine;
Alpine.start();
iniciarImagenesSlot();
