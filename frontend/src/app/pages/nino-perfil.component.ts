import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';
import { NinoProfileNavComponent } from '../layout/nino-profile-nav.component';

interface ExpedienteMedico {
  tipo_sangre: string | null;
  alergias: string | null;
  padecimientos: string | null;
  medicamentos_habituales: string | null;
  restricciones_alimentarias: string | null;
  medico_nombre: string | null;
  medico_telefono: string | null;
  institucion_medica: string | null;
  numero_seguro: string | null;
  indicaciones_emergencia: string | null;
  observaciones: string | null;
}

interface Tutor {
  id_tutor: number;
  nombres: string;
  apellidos: string;
  telefono: string;
  telefono_alterno: string | null;
  direccion: string | null;
  foto_url: string | null;
  email: string;
  requiere_cambio_password: boolean;
  parentesco: string;
  es_principal: boolean;
  recibe_notificaciones: boolean;
  autorizado_recoger: boolean;
  prioridad_contacto: number | null;
}

interface ContactoAutorizado {
  id_contacto: number;
  nombres: string;
  apellidos: string;
  parentesco: string | null;
  telefono: string;
  email: string | null;
  direccion: string | null;
  foto_url: string | null;
  identificacion_referencia: string | null;
  es_contacto_emergencia: boolean;
  autorizado_recoger: boolean;
  prioridad_emergencia: number | null;
  observaciones: string | null;
}

interface NinoDetalle {
  id_nino: number;
  nombres: string;
  apellidos: string;
  fecha_nacimiento: string;
  edad: number;
  genero: string;
  foto_url: string | null;
  codigo_qr: string | null;
  estado: string;
  fecha_ingreso: string | null;
  expediente_medico: ExpedienteMedico;
  tutores: Tutor[];
  contactos_autorizados: ContactoAutorizado[];
}

interface CredencialTemporal {
  email: string;
  password_temporal: string;
  nombre: string;
}

@Component({
  selector: 'app-nino-perfil',
  standalone: true,
  imports: [CommonModule, RouterModule, ShellComponent, NinoProfileNavComponent],
  templateUrl: './nino-perfil.component.html',
  styleUrl: './nino-perfil.component.css',
})
export class NinoPerfilComponent implements OnInit {
  nino: NinoDetalle | null = null;
  cargando = true;
  mensajeError = '';
  mensajeAccion = '';
  idGuarderia = 0;
  idEmpleado = 0;
  puedeAdministrar = false;
  cambiandoEstado = false;
  mostrarConfirmacionEstado = false;
  mostrarRestablecimientoTutor = false;
  restableciendoPassword = false;
  tutorObjetivo: Tutor | null = null;
  credencialTemporal: CredencialTemporal | null = null;
  mensajePassword = '';
  defaultUserImage = 'assets/images/default-user.jpg';

  private readonly apiDetalleNino = 'http://localhost/SafeKids-api/api/nino_detalle.php';
  private readonly apiCambiarEstado =
    'http://localhost/SafeKids-api/api/cambiar_estado_nino.php';
  private readonly apiRestablecerPassword =
    'http://localhost/SafeKids-api/api/restablecer_password.php';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    const usuarioGuardado = localStorage.getItem('safekids_usuario');
    const idNino = Number(this.route.snapshot.paramMap.get('id'));

    if (!usuarioGuardado) {
      this.router.navigate(['/login']);
      return;
    }

    const usuario = JSON.parse(usuarioGuardado);
    this.idGuarderia = Number(usuario.id_guarderia);
    this.idEmpleado = Number(usuario.empleado?.id_empleado);
    this.puedeAdministrar =
      usuario.rol === 'ADMIN' && this.idGuarderia > 0 && this.idEmpleado > 0;

    if (!idNino || !this.idGuarderia) {
      this.cargando = false;
      this.mensajeError = 'No fue posible identificar el perfil solicitado.';
      return;
    }

    this.http
      .get<{ status: string; nino: NinoDetalle; mensaje?: string }>(this.apiDetalleNino, {
        params: {
          id_nino: String(idNino),
          id_guarderia: String(this.idGuarderia),
        },
      })
      .subscribe({
        next: (respuesta) => {
          if (respuesta.status !== 'success') {
            this.mensajeError = respuesta.mensaje || 'No fue posible consultar el perfil.';
            return;
          }

          this.nino = respuesta.nino;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible conectar con el servidor.';
          this.cargando = false;
          this.cdr.detectChanges();
        },
        complete: () => {
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }

  volver(): void {
    this.router.navigate(['/dashboard']);
  }

  abrirConfirmacionEstado(): void {
    this.mensajeAccion = '';
    this.mostrarConfirmacionEstado = true;
  }

  cerrarConfirmacionEstado(): void {
    if (!this.cambiandoEstado) {
      this.mostrarConfirmacionEstado = false;
    }
  }

  cambiarEstado(): void {
    if (!this.nino || !this.puedeAdministrar) {
      return;
    }

    const nuevoEstado = this.nino.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
    this.cambiandoEstado = true;
    this.mensajeAccion = '';

    this.http
      .patch<{ status: string; mensaje: string; estado: string }>(this.apiCambiarEstado, {
        id_nino: this.nino.id_nino,
        id_guarderia: this.idGuarderia,
        id_empleado: this.idEmpleado,
        estado: nuevoEstado,
      })
      .subscribe({
        next: (respuesta) => {
          if (this.nino) {
            this.nino.estado = respuesta.estado;
          }
          this.mensajeAccion = respuesta.mensaje;
          this.cambiandoEstado = false;
          this.mostrarConfirmacionEstado = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeAccion =
            error.error?.mensaje || 'No fue posible cambiar el estado del niño.';
          this.cambiandoEstado = false;
          this.cdr.detectChanges();
        },
      });
  }

  abrirRestablecimientoTutor(tutor: Tutor): void {
    this.tutorObjetivo = tutor;
    this.credencialTemporal = null;
    this.mensajePassword = '';
    this.mostrarRestablecimientoTutor = true;
  }

  cerrarRestablecimientoTutor(): void {
    if (!this.restableciendoPassword) {
      this.mostrarRestablecimientoTutor = false;
      this.tutorObjetivo = null;
      this.credencialTemporal = null;
      this.mensajePassword = '';
    }
  }

  restablecerPasswordTutor(): void {
    if (!this.tutorObjetivo || !this.puedeAdministrar || this.restableciendoPassword) {
      return;
    }

    this.restableciendoPassword = true;
    this.http
      .post<{
        status: string;
        mensaje: string;
        credencial_temporal: CredencialTemporal;
      }>(this.apiRestablecerPassword, {
        id_guarderia: this.idGuarderia,
        id_empleado: this.idEmpleado,
        tipo_objetivo: 'TUTOR',
        id_objetivo: this.tutorObjetivo.id_tutor,
      })
      .subscribe({
        next: (respuesta) => {
          this.credencialTemporal = respuesta.credencial_temporal;
          if (this.tutorObjetivo) {
            this.tutorObjetivo.requiere_cambio_password = true;
          }
          this.restableciendoPassword = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajePassword =
            error.error?.mensaje || 'No fue posible restablecer la contraseña.';
          this.restableciendoPassword = false;
          this.cdr.detectChanges();
        },
      });
  }

  async copiarCredencialTutor(): Promise<void> {
    if (!this.credencialTemporal) {
      return;
    }

    const texto = `Correo: ${this.credencialTemporal.email}\nContraseña temporal: ${this.credencialTemporal.password_temporal}`;
    try {
      await navigator.clipboard.writeText(texto);
      this.mensajePassword = 'Credencial copiada.';
    } catch {
      this.mensajePassword = 'Selecciona y copia la credencial manualmente.';
    }
  }

  nombreGenero(genero: string): string {
    const nombres: Record<string, string> = {
      FEMENINO: 'Femenino',
      MASCULINO: 'Masculino',
      OTRO: 'Otro',
      NO_ESPECIFICADO: 'No especificado',
    };

    return nombres[genero] || genero;
  }

  fechaLegible(fecha: string | null): string {
    if (!fecha) {
      return 'No registrada';
    }

    const [anio, mes, dia] = fecha.split('-').map(Number);
    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    }).format(new Date(anio, mes - 1, dia));
  }

  textoOValor(valor: string | null | undefined, alternativo = 'No registrado'): string {
    return valor?.trim() || alternativo;
  }
}
