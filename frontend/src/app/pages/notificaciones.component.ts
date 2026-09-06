import {
  ChangeDetectorRef,
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  ViewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';
import { NinoProfileNavComponent } from '../layout/nino-profile-nav.component';

interface TutorNotificacion {
  id_tutor: number;
  nombres: string;
  apellidos: string;
  parentesco: string;
  recibe_notificaciones: boolean;
}

interface NinoNotificacion {
  id_nino: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
  estado: string;
  tutores: TutorNotificacion[];
}

interface TipoNotificacion {
  codigo: string;
  nombre: string;
}

interface ResultadoPush {
  configurado: boolean;
  enviados: number;
  fallidos: number;
  sin_dispositivo: number;
}

@Component({
  selector: 'app-notificaciones',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ShellComponent,
    NinoProfileNavComponent,
  ],
  templateUrl: './notificaciones.component.html',
  styleUrl: './notificaciones.component.css',
})
export class NotificacionesComponent implements OnInit, OnDestroy {
  @ViewChild('imagenInput') imagenInput?: ElementRef<HTMLInputElement>;

  alcance: 'GLOBAL' | 'PERSONAL' = 'GLOBAL';
  nino: NinoNotificacion | null = null;
  idGuarderia = 0;
  idEmpleado = 0;
  razon = '';
  asunto = '';
  descripcion = '';
  prioridad: 'NORMAL' | 'IMPORTANTE' | 'URGENTE' = 'NORMAL';
  requiereConfirmacion = false;
  cargandoContexto = false;
  enviando = false;
  mensajeError = '';
  mensajeExito = '';
  imagenSeleccionada: File | null = null;
  imagenPreview: string | null = null;
  defaultUserImage = 'assets/images/default-user.jpg';
  private imagenPreviewTemporal: string | null = null;

  tiposGlobales: TipoNotificacion[] = [
    { codigo: 'AVISO_GENERAL', nombre: 'Aviso general' },
    { codigo: 'RECORDATORIO', nombre: 'Recordatorio' },
    { codigo: 'ADMINISTRATIVA', nombre: 'Administrativa' },
    { codigo: 'EMERGENCIA', nombre: 'Emergencia' },
  ];

  tiposPersonales: TipoNotificacion[] = [
    { codigo: 'AVISO_PERSONAL', nombre: 'Aviso personal' },
    { codigo: 'INCIDENTE', nombre: 'Incidente' },
    { codigo: 'RECORDATORIO', nombre: 'Recordatorio' },
    { codigo: 'EMERGENCIA', nombre: 'Emergencia' },
  ];

  private readonly apiDetalleNino = 'http://localhost/SafeKids-api/api/nino_detalle.php';
  private readonly apiCrearNotificacion =
    'http://localhost/SafeKids-api/api/crear_notificacion.php';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    const usuarioGuardado = localStorage.getItem('safekids_usuario');
    if (!usuarioGuardado) {
      this.router.navigate(['/login']);
      return;
    }

    const usuario = JSON.parse(usuarioGuardado);
    this.idGuarderia = Number(usuario.id_guarderia);
    this.idEmpleado = Number(usuario.empleado?.id_empleado);
    this.alcance = this.route.snapshot.data['alcance'] === 'PERSONAL' ? 'PERSONAL' : 'GLOBAL';

    if (!this.idGuarderia || !this.idEmpleado) {
      this.router.navigate(['/login']);
      return;
    }

    if (this.esPersonal) {
      this.cargarNino();
    }
  }

  ngOnDestroy(): void {
    this.liberarImagenTemporal();
  }

  get esPersonal(): boolean {
    return this.alcance === 'PERSONAL';
  }

  get tiposDisponibles(): TipoNotificacion[] {
    return this.esPersonal ? this.tiposPersonales : this.tiposGlobales;
  }

  get tutoresDestinatarios(): TutorNotificacion[] {
    return (this.nino?.tutores || []).filter((tutor) => tutor.recibe_notificaciones);
  }

  cargarNino(): void {
    const idNino = Number(this.route.snapshot.paramMap.get('id'));
    if (!idNino) {
      this.mensajeError = 'No fue posible identificar al niño.';
      return;
    }

    this.cargandoContexto = true;
    this.http
      .get<{ status: string; nino: NinoNotificacion; mensaje?: string }>(this.apiDetalleNino, {
        params: {
          id_nino: String(idNino),
          id_guarderia: String(this.idGuarderia),
        },
      })
      .subscribe({
        next: (respuesta) => {
          if (respuesta.status === 'success') {
            this.nino = respuesta.nino;
          } else {
            this.mensajeError = respuesta.mensaje || 'No fue posible cargar al niño.';
          }
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible consultar el perfil del niño.';
          this.cargandoContexto = false;
          this.cdr.detectChanges();
        },
        complete: () => {
          this.cargandoContexto = false;
          this.cdr.detectChanges();
        },
      });
  }

  alCambiarRazon(): void {
    if (this.razon === 'EMERGENCIA') {
      this.prioridad = 'URGENTE';
      this.requiereConfirmacion = true;
      return;
    }

    if (this.razon === 'INCIDENTE') {
      this.prioridad = 'IMPORTANTE';
      this.requiereConfirmacion = false;
      return;
    }

    this.prioridad = 'NORMAL';
    this.requiereConfirmacion = false;
  }

  seleccionarImagen(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    this.mensajeError = '';

    if (!archivo) {
      return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
      this.mensajeError = 'La imagen debe ser JPG, PNG o WEBP.';
      input.value = '';
      return;
    }

    if (archivo.size > 5 * 1024 * 1024) {
      this.mensajeError = 'La imagen debe pesar como máximo 5 MB.';
      input.value = '';
      return;
    }

    this.liberarImagenTemporal();
    this.imagenSeleccionada = archivo;
    this.imagenPreviewTemporal = URL.createObjectURL(archivo);
    this.imagenPreview = this.imagenPreviewTemporal;
  }

  descartarImagen(): void {
    this.liberarImagenTemporal();
    this.imagenSeleccionada = null;
    this.imagenPreview = null;
    if (this.imagenInput) {
      this.imagenInput.nativeElement.value = '';
    }
  }

  enviar(): void {
    this.mensajeError = '';
    this.mensajeExito = '';

    if (
      !this.razon ||
      !this.asunto.trim() ||
      !this.descripcion.trim() ||
      (this.esPersonal && !this.nino)
    ) {
      this.mensajeError = 'Completa todos los campos obligatorios.';
      return;
    }

    this.enviando = true;
    const datos = new FormData();
    datos.append('id_guarderia', String(this.idGuarderia));
    datos.append('id_empleado', String(this.idEmpleado));
    if (this.esPersonal && this.nino) {
      datos.append('id_nino', String(this.nino.id_nino));
    }
    datos.append('alcance', this.alcance);
    datos.append('tipo_codigo', this.razon);
    datos.append('titulo', this.asunto.trim());
    datos.append('mensaje', this.descripcion.trim());
    datos.append('prioridad', this.prioridad);
    datos.append('requiere_confirmacion', this.requiereConfirmacion ? '1' : '0');
    if (this.imagenSeleccionada) {
      datos.append('foto', this.imagenSeleccionada);
    }

    this.http
      .post<{
        status: string;
        mensaje: string;
        id_notificacion?: number;
        destinatarios?: number;
        imagen_url?: string | null;
        push?: ResultadoPush;
      }>(this.apiCrearNotificacion, datos)
      .subscribe({
        next: (respuesta) => {
          const destinatarios = respuesta.destinatarios ?? 0;
          const resultadoPush = respuesta.push;
          const detallePush = resultadoPush?.configurado
            ? ` Push enviados: ${resultadoPush.enviados}; sin dispositivo activo: ${resultadoPush.sin_dispositivo}; fallidos: ${resultadoPush.fallidos}.`
            : ' El servicio de notificaciones push no está configurado.';

          this.mensajeExito = `${respuesta.mensaje}. Tutores destinatarios: ${destinatarios}.${detallePush}`;
          this.descartarImagen();
          this.enviando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible enviar la notificación.';
          this.enviando = false;
          this.cdr.detectChanges();
        },
      });
  }

  private liberarImagenTemporal(): void {
    if (this.imagenPreviewTemporal) {
      URL.revokeObjectURL(this.imagenPreviewTemporal);
      this.imagenPreviewTemporal = null;
    }
  }

  cancelar(): void {
    if (this.esPersonal && this.nino) {
      this.router.navigate(['/ninos', this.nino.id_nino]);
      return;
    }

    this.router.navigate(['/dashboard']);
  }
}
