import { CommonModule } from '@angular/common';
import {
  ChangeDetectorRef,
  Component,
  HostListener,
  OnInit,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';
import { NinoProfileNavComponent } from '../layout/nino-profile-nav.component';

interface TipoNotificacion {
  codigo: string;
  nombre: string;
}

interface NinoNotificaciones {
  id_nino: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
  estado: string;
}

interface EmpleadoNotificacion {
  id_empleado: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
}

interface EntregaNotificacion {
  destinatarios: number;
  push_enviados: number;
  vistos: number;
  confirmados: number;
}

interface DestinatarioNotificacion {
  id_destinatario: number;
  id_usuario: number;
  id_tutor: number | null;
  nombres: string | null;
  apellidos: string | null;
  foto_url: string | null;
  enviada_push: boolean;
  enviada_push_en: string | null;
  vista: boolean;
  vista_en: string | null;
  confirmada: boolean;
  confirmada_en: string | null;
  respuesta: string | null;
}

interface NotificacionPersonal {
  id_notificacion: number;
  titulo: string;
  mensaje: string;
  prioridad: 'NORMAL' | 'IMPORTANTE' | 'URGENTE';
  imagen_url: string | null;
  imagen_public_id: string | null;
  requiere_confirmacion: boolean;
  publicada_en: string;
  tipo: TipoNotificacion;
  empleado: EmpleadoNotificacion;
  entrega: EntregaNotificacion;
  destinatarios: DestinatarioNotificacion[];
}

interface GrupoNotificaciones {
  fecha: string;
  etiqueta: string;
  notificaciones: NotificacionPersonal[];
}

interface RespuestaHistorial {
  status: string;
  nino: NinoNotificaciones;
  notificaciones: NotificacionPersonal[];
  tipos: TipoNotificacion[];
  mensaje?: string;
}

@Component({
  selector: 'app-notificaciones-historial',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ShellComponent,
    NinoProfileNavComponent,
  ],
  templateUrl: './notificaciones-historial.component.html',
  styleUrl: './notificaciones-historial.component.css',
})
export class NotificacionesHistorialComponent implements OnInit {
  nino: NinoNotificaciones | null = null;
  notificaciones: NotificacionPersonal[] = [];
  tipos: TipoNotificacion[] = [];
  idNino = 0;
  idGuarderia = 0;
  idEmpleado = 0;
  buscar = '';
  tipo = 'TODOS';
  prioridad = 'TODAS';
  fechaDesde = this.fechaInput(new Date(Date.now() - 29 * 86400000));
  fechaHasta = this.fechaInput();
  cargando = true;
  mensajeError = '';
  notificacionSeleccionada: NotificacionPersonal | null = null;
  readonly fechaMaxima = this.fechaInput();
  readonly defaultUserImage = 'assets/images/default-user.jpg';

  private readonly apiHistorial =
    'http://localhost/SafeKids-api/api/listar_notificaciones_nino.php';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    const usuarioGuardado = localStorage.getItem('safekids_usuario');
    this.idNino = Number(this.route.snapshot.paramMap.get('id'));
    if (!usuarioGuardado) {
      this.router.navigate(['/login']);
      return;
    }

    const usuario = JSON.parse(usuarioGuardado);
    this.idGuarderia = Number(usuario.id_guarderia);
    this.idEmpleado = Number(usuario.empleado?.id_empleado);
    if (
      !['ADMIN', 'EMPLEADO'].includes(usuario.rol) ||
      !this.idNino ||
      !this.idGuarderia ||
      !this.idEmpleado
    ) {
      this.router.navigate(['/dashboard']);
      return;
    }

    this.cargarNotificaciones();
  }

  get gruposNotificaciones(): GrupoNotificaciones[] {
    const grupos = new Map<string, NotificacionPersonal[]>();
    for (const notificacion of this.notificaciones) {
      const fecha = notificacion.publicada_en.slice(0, 10);
      const grupo = grupos.get(fecha) ?? [];
      grupo.push(notificacion);
      grupos.set(fecha, grupo);
    }

    return Array.from(grupos.entries()).map(([fecha, notificaciones]) => ({
      fecha,
      etiqueta: this.formatearFecha(fecha),
      notificaciones,
    }));
  }

  get totalDestinatarios(): number {
    return this.notificaciones.reduce(
      (total, notificacion) => total + notificacion.entrega.destinatarios,
      0,
    );
  }

  get totalVistos(): number {
    return this.notificaciones.reduce(
      (total, notificacion) => total + notificacion.entrega.vistos,
      0,
    );
  }

  get totalConfirmados(): number {
    return this.notificaciones.reduce(
      (total, notificacion) => total + notificacion.entrega.confirmados,
      0,
    );
  }

  cargarNotificaciones(): void {
    if (!this.fechaDesde || !this.fechaHasta || this.fechaDesde > this.fechaHasta) {
      this.mensajeError = 'Selecciona un rango de fechas válido.';
      this.cargando = false;
      return;
    }

    this.mensajeError = '';
    this.cargando = true;
    this.http
      .get<RespuestaHistorial>(this.apiHistorial, {
        params: {
          id_guarderia: String(this.idGuarderia),
          id_empleado: String(this.idEmpleado),
          id_nino: String(this.idNino),
          buscar: this.buscar.trim(),
          tipo: this.tipo,
          prioridad: this.prioridad,
          desde: this.fechaDesde,
          hasta: this.fechaHasta,
        },
      })
      .subscribe({
        next: (respuesta) => {
          this.nino = respuesta.nino;
          this.notificaciones = respuesta.notificaciones || [];
          this.tipos = respuesta.tipos || [];
          this.cargando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.notificaciones = [];
          this.mensajeError =
            error.error?.mensaje ||
            'No fue posible consultar el historial de notificaciones.';
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }

  limpiarFiltros(): void {
    this.buscar = '';
    this.tipo = 'TODOS';
    this.prioridad = 'TODAS';
    this.fechaDesde = this.fechaInput(new Date(Date.now() - 29 * 86400000));
    this.fechaHasta = this.fechaInput();
    this.cargarNotificaciones();
  }

  volverPerfil(): void {
    this.router.navigate(['/ninos', this.idNino]);
  }

  nuevaNotificacion(): void {
    this.router.navigate(['/ninos', this.idNino, 'notificaciones', 'nueva']);
  }

  abrirNotificacion(notificacion: NotificacionPersonal): void {
    this.notificacionSeleccionada = notificacion;
  }

  cerrarNotificacion(): void {
    this.notificacionSeleccionada = null;
  }

  @HostListener('document:keydown.escape')
  cerrarConEscape(): void {
    if (this.notificacionSeleccionada) {
      this.cerrarNotificacion();
    }
  }

  formatearFecha(fecha: string): string {
    const fechaLocal = new Date(`${fecha}T12:00:00`);
    return new Intl.DateTimeFormat('es-MX', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(fechaLocal);
  }

  formatearHora(fechaHora: string): string {
    const fecha = new Date(fechaHora.replace(' ', 'T'));
    return new Intl.DateTimeFormat('es-MX', {
      hour: 'numeric',
      minute: '2-digit',
    }).format(fecha);
  }

  formatearMomento(fechaHora: string | null): string {
    if (!fechaHora) {
      return '';
    }
    const fecha = new Date(fechaHora.replace(' ', 'T'));
    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'short',
      hour: 'numeric',
      minute: '2-digit',
    }).format(fecha);
  }

  nombreTutor(destinatario: DestinatarioNotificacion): string {
    const nombre = `${destinatario.nombres || ''} ${
      destinatario.apellidos || ''
    }`.trim();
    return nombre || 'Tutor no disponible';
  }

  clasePrioridad(prioridad: string): string {
    return prioridad.toLowerCase();
  }

  etiquetaPrioridad(prioridad: string): string {
    return prioridad.charAt(0) + prioridad.slice(1).toLowerCase();
  }

  private fechaInput(fecha = new Date()): string {
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
  }
}
