import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';

interface EmpleadoDetalle {
  id_empleado: number;
  nombres: string;
  apellidos: string;
  telefono: string | null;
  puesto: string | null;
  fecha_ingreso: string | null;
  foto_url: string | null;
  notas: string | null;
  email: string;
  estado: string;
  rol: string;
  requiere_cambio_password: boolean;
  ultimo_acceso: string | null;
  creado_en: string;
  actualizado_en: string;
  es_solicitante: boolean;
}

interface CredencialTemporal {
  email: string;
  password_temporal: string;
  nombre: string;
}

@Component({
  selector: 'app-empleado-perfil',
  standalone: true,
  imports: [CommonModule, RouterModule, ShellComponent],
  templateUrl: './empleado-perfil.component.html',
  styleUrl: './empleado-perfil.component.css',
})
export class EmpleadoPerfilComponent implements OnInit {
  empleado: EmpleadoDetalle | null = null;
  cargando = true;
  mensajeError = '';
  mensajeAccion = '';
  mostrarConfirmacion = false;
  cambiandoEstado = false;
  mostrarRestablecimiento = false;
  restableciendoPassword = false;
  credencialTemporal: CredencialTemporal | null = null;
  mensajeCopia = '';
  idGuarderia = 0;
  idAdministrador = 0;
  rolSolicitante = '';
  readonly defaultUserImage = 'assets/images/default-user.jpg';

  private readonly apiDetalle = 'http://localhost/SafeKids-api/api/empleado_detalle.php';
  private readonly apiEstado = 'http://localhost/SafeKids-api/api/cambiar_estado_empleado.php';
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
    const idObjetivo = Number(this.route.snapshot.paramMap.get('id'));
    if (!usuarioGuardado) {
      this.router.navigate(['/login']);
      return;
    }

    const usuario = JSON.parse(usuarioGuardado);
    this.idGuarderia = Number(usuario.id_guarderia);
    this.idAdministrador = Number(usuario.empleado?.id_empleado);
    this.rolSolicitante = String(usuario.rol || '');
    if (
      !['ADMIN', 'EMPLEADO'].includes(this.rolSolicitante) ||
      !this.idGuarderia ||
      !this.idAdministrador
    ) {
      this.router.navigate(['/dashboard']);
      return;
    }

    if (this.rolSolicitante !== 'ADMIN' && idObjetivo !== this.idAdministrador) {
      this.router.navigate(['/dashboard']);
      return;
    }

    if (!idObjetivo) {
      this.mensajeError = 'No fue posible identificar el perfil solicitado.';
      this.cargando = false;
      return;
    }

    this.cargarDetalle(idObjetivo);
  }

  volver(): void {
    this.router.navigate([
      this.rolSolicitante === 'ADMIN' ? '/administrar-empleados' : '/dashboard',
    ]);
  }

  get esAdministrador(): boolean {
    return this.rolSolicitante === 'ADMIN';
  }

  get textoVolver(): string {
    return this.esAdministrador ? 'Volver al personal' : 'Volver al inicio';
  }

  abrirConfirmacion(): void {
    this.mensajeAccion = '';
    this.mostrarConfirmacion = true;
  }

  cerrarConfirmacion(): void {
    if (!this.cambiandoEstado) {
      this.mostrarConfirmacion = false;
    }
  }

  abrirRestablecimiento(): void {
    this.credencialTemporal = null;
    this.mensajeCopia = '';
    this.mostrarRestablecimiento = true;
  }

  cerrarRestablecimiento(): void {
    if (!this.restableciendoPassword) {
      this.mostrarRestablecimiento = false;
      this.credencialTemporal = null;
      this.mensajeCopia = '';
    }
  }

  restablecerPassword(): void {
    if (!this.empleado || this.empleado.es_solicitante || this.restableciendoPassword) {
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
        id_empleado: this.idAdministrador,
        tipo_objetivo: 'EMPLEADO',
        id_objetivo: this.empleado.id_empleado,
      })
      .subscribe({
        next: (respuesta) => {
          this.credencialTemporal = respuesta.credencial_temporal;
          if (this.empleado) {
            this.empleado.requiere_cambio_password = true;
          }
          this.restableciendoPassword = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeCopia =
            error.error?.mensaje || 'No fue posible restablecer la contraseña.';
          this.restableciendoPassword = false;
          this.cdr.detectChanges();
        },
      });
  }

  async copiarCredencial(): Promise<void> {
    if (!this.credencialTemporal) {
      return;
    }

    const texto = `Correo: ${this.credencialTemporal.email}\nContraseña temporal: ${this.credencialTemporal.password_temporal}`;
    try {
      await navigator.clipboard.writeText(texto);
      this.mensajeCopia = 'Credencial copiada.';
    } catch {
      this.mensajeCopia = 'Selecciona y copia la credencial manualmente.';
    }
  }

  cambiarEstado(): void {
    if (!this.empleado || this.empleado.es_solicitante) {
      return;
    }

    const nuevoEstado = this.empleado.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
    this.cambiandoEstado = true;
    this.mensajeAccion = '';

    this.http
      .patch<{ status: string; mensaje: string; estado: string }>(this.apiEstado, {
        id_objetivo: this.empleado.id_empleado,
        id_guarderia: this.idGuarderia,
        id_empleado: this.idAdministrador,
        estado: nuevoEstado,
      })
      .subscribe({
        next: (respuesta) => {
          if (this.empleado) {
            this.empleado.estado = respuesta.estado;
          }
          this.mensajeAccion = respuesta.mensaje;
          this.cambiandoEstado = false;
          this.mostrarConfirmacion = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeAccion = error.error?.mensaje || 'No fue posible cambiar el estado.';
          this.cambiandoEstado = false;
          this.cdr.detectChanges();
        },
      });
  }

  rolLegible(rol: string): string {
    return rol === 'ADMIN' ? 'Administrador' : 'Empleado';
  }

  fechaLegible(fecha: string | null): string {
    if (!fecha) {
      return 'No registrada';
    }
    const soloFecha = fecha.split(' ')[0];
    const [anio, mes, dia] = soloFecha.split('-').map(Number);
    if (!anio || !mes || !dia) {
      return fecha;
    }
    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    }).format(new Date(anio, mes - 1, dia));
  }

  fechaHora(fecha: string | null): string {
    if (!fecha) {
      return 'Todavía no inicia sesión';
    }
    const valor = new Date(fecha.replace(' ', 'T'));
    if (Number.isNaN(valor.getTime())) {
      return fecha;
    }
    return new Intl.DateTimeFormat('es-MX', {
      dateStyle: 'long',
      timeStyle: 'short',
    }).format(valor);
  }

  textoOValor(valor: string | null, alternativo = 'No registrado'): string {
    return valor?.trim() || alternativo;
  }

  private cargarDetalle(idObjetivo: number): void {
    this.http
      .get<{ status: string; empleado: EmpleadoDetalle; mensaje?: string }>(this.apiDetalle, {
        params: {
          id_objetivo: String(idObjetivo),
          id_guarderia: String(this.idGuarderia),
          id_empleado: String(this.idAdministrador),
        },
      })
      .subscribe({
        next: (respuesta) => {
          this.empleado = respuesta.empleado;
          this.cargando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError = error.error?.mensaje || 'No fue posible conectar con el servidor.';
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }
}
