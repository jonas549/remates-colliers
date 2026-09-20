import Alpine from 'alpinejs';
import acceso from './modulos/acceso';
import registro from './modulos/registro';
import listado from './modulos/listado';
import detalleRemate from './modulos/detalle';
import salaPuja from './modulos/sala';
import salaPujaDemo from './modulos/sala-demo';
import adminSubastas from './modulos/admin-subastas';
import adminPostores from './modulos/admin-postores';
import adminEnVivo from './modulos/admin-en-vivo';
import adminUsuarios from './modulos/admin-usuarios';
import { iniciarImagenesSlot } from './modulos/imagen-slot';

Alpine.data('acceso', acceso);
Alpine.data('registro', registro);
Alpine.data('listado', listado);
Alpine.data('detalleRemate', detalleRemate);
Alpine.data('salaPuja', salaPuja);
Alpine.data('salaPujaDemo', salaPujaDemo);
Alpine.data('adminSubastas', adminSubastas);
Alpine.data('adminPostores', adminPostores);
Alpine.data('adminEnVivo', adminEnVivo);
Alpine.data('adminUsuarios', adminUsuarios);

window.Alpine = Alpine;
Alpine.start();
iniciarImagenesSlot();
