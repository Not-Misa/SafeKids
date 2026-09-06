import { Routes } from '@angular/router';
import { DashboardComponent } from './dashboard.component';
import { Login } from './login/login';
import { EmpleadosComponent } from './pages/empleados.component';
import { EmpleadoPerfilComponent } from './pages/empleado-perfil.component';
import { EmpleadoRegistroComponent } from './pages/empleado-registro.component';
import { NinosComponent } from './pages/ninos.component';
import { NinoPerfilComponent } from './pages/nino-perfil.component';
import { NinoRegistroComponent } from './pages/nino-registro.component';
import { NotificacionesComponent } from './pages/notificaciones.component';
import {
  adminGuard,
  authGuard,
  passwordUpdatedGuard,
  webRoleGuard,
} from './auth/auth.guards';
import { CambiarPasswordComponent } from './pages/cambiar-password.component';

export const routes: Routes = [
  { path: 'login', component: Login },
  {
    path: 'cambiar-password',
    component: CambiarPasswordComponent,
    canActivate: [authGuard, webRoleGuard],
  },
  {
    path: '',
    canActivate: [authGuard, webRoleGuard, passwordUpdatedGuard],
    children: [
      { path: 'dashboard', component: DashboardComponent },
      {
        path: 'avisos',
        loadComponent: () =>
          import('./pages/avisos.component').then((modulo) => modulo.AvisosComponent),
      },
      {
        path: 'notificaciones',
        component: NotificacionesComponent,
        data: { alcance: 'GLOBAL' },
      },
      { path: 'administrar-clientes/nuevo', component: NinoRegistroComponent },
      { path: 'administrar-clientes', component: NinosComponent },
      {
        path: 'ninos/:id/notificaciones/nueva',
        component: NotificacionesComponent,
        data: { alcance: 'PERSONAL' },
      },
      {
        path: 'ninos/:id/notificaciones',
        loadComponent: () =>
          import('./pages/notificaciones-historial.component').then(
            (modulo) => modulo.NotificacionesHistorialComponent,
          ),
      },
      {
        path: 'ninos/:id/editar',
        component: NinoRegistroComponent,
        data: { modo: 'editar' },
      },
      {
        path: 'ninos/:id/reportes/nuevo',
        loadComponent: () =>
          import('./pages/reporte-registro.component').then(
            (modulo) => modulo.ReporteRegistroComponent,
          ),
      },
      {
        path: 'ninos/:id/reportes',
        loadComponent: () =>
          import('./pages/reportes-historial.component').then(
            (modulo) => modulo.ReportesHistorialComponent,
          ),
      },
      { path: 'ninos/:id', component: NinoPerfilComponent },
      {
        path: 'administrar-empleados/nuevo',
        component: EmpleadoRegistroComponent,
        canActivate: [adminGuard],
      },
      {
        path: 'administrar-empleados',
        component: EmpleadosComponent,
        canActivate: [adminGuard],
      },
      {
        path: 'empleados/:id/editar',
        component: EmpleadoRegistroComponent,
        canActivate: [adminGuard],
        data: { modo: 'editar' },
      },
      { path: 'empleados/:id', component: EmpleadoPerfilComponent },
    ],
  },
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: '**', redirectTo: 'login' },
];
