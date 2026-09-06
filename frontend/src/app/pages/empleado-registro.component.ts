import {
  ChangeDetectorRef,
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  ViewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, NgForm } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';

interface EmpleadoFormulario {
  nombres: string;
  apellidos: string;
  email: string;
  telefono: string;
  puesto: string;
  fecha_ingreso: string;
  rol: 'ADMIN' | 'EMPLEADO';
  notas: string;
}

interface EmpleadoDetalle extends EmpleadoFormulario {
  id_empleado: number;
  foto_url: string | null;
  estado: string;
  ultimo_acceso: string | null;
  requiere_cambio_password: boolean;
  es_solicitante: boolean;
}

interface CredencialTemporal {
  email: string;
  password_temporal: string;
}

interface RespuestaGuardado {
  status: string;
  mensaje: string;
  id_empleado: number;
  credencial_temporal?: CredencialTemporal;
}

interface RespuestaFoto {
  status: string;
  mensaje: string;
  foto_url: string;
  foto_public_id: string;
  advertencia: string | null;
}

@Component({
  selector: 'app-empleado-registro',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, ShellComponent],
  templateUrl: './empleado-registro.component.html',
  styleUrl: './empleado-registro.component.css',
})
export class EmpleadoRegistroComponent implements OnInit, OnDestroy {
  @ViewChild('fotoInput') fotoInput?: ElementRef<HTMLInputElement>;

  empleado = this.nuevoEmpleado();
  idGuarderia = 0;
  idAdministrador = 0;
  idEmpleadoEditando: number | null = null;
  modoEdicion = false;
  esCuentaPropia = false;
  cargando = false;
  enviando = false;
  subiendoFoto = false;
  mensajeError = '';
  mensajeAdvertencia = '';
  idEmpleadoGuardado: number | null = null;
  credencialTemporal: CredencialTemporal | null = null;
  fotoSeleccionada: File | null = null;
  fotoActualUrl: string | null = null;
  readonly defaultUserImage = 'assets/images/default-user.jpg';
  fotoPreview = this.defaultUserImage;
  readonly fechaMaxima = new Date().toISOString().slice(0, 10);

  private fotoPreviewTemporal: string | null = null;
  private readonly apiRegistro =
    'http://localhost/SafeKids-api/api/registrar_empleado.php';
  private readonly apiActualizacion =
    'http://localhost/SafeKids-api/api/actualizar_empleado.php';
  private readonly apiDetalle =
    'http://localhost/SafeKids-api/api/empleado_detalle.php';
  private readonly apiFoto =
    'http://localhost/SafeKids-api/api/subir_foto_empleado.php';

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router,
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
    this.idAdministrador = Number(usuario.empleado?.id_empleado);
    if (
      usuario.rol !== 'ADMIN' ||
      !this.idGuarderia ||
      !this.idAdministrador
    ) {
      this.router.navigate(['/dashboard']);
      return;
    }

    this.modoEdicion = this.route.snapshot.data['modo'] === 'editar';
    if (this.modoEdicion) {
      this.idEmpleadoEditando = Number(this.route.snapshot.paramMap.get('id'));
      if (!this.idEmpleadoEditando) {
        this.router.navigate(['/administrar-empleados']);
        return;
      }
      this.esCuentaPropia = this.idEmpleadoEditando === this.idAdministrador;
      this.cargarDetalle();
    }
  }

  ngOnDestroy(): void {
    this.liberarFotoTemporal();
  }

  seleccionarFoto(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) {
      return;
    }

    this.mensajeError = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
      input.value = '';
      this.mensajeError = 'La fotografía debe ser JPG, PNG o WEBP.';
      return;
    }

    if (archivo.size > 5 * 1024 * 1024) {
      input.value = '';
      this.mensajeError = 'La fotografía debe pesar como máximo 5 MB.';
      return;
    }

    this.liberarFotoTemporal();
    this.fotoSeleccionada = archivo;
    this.fotoPreviewTemporal = URL.createObjectURL(archivo);
    this.fotoPreview = this.fotoPreviewTemporal;
  }

  descartarFotoSeleccionada(): void {
    this.liberarFotoTemporal();
    this.fotoSeleccionada = null;
    this.fotoPreview = this.fotoActualUrl || this.defaultUserImage;
    if (this.fotoInput) {
      this.fotoInput.nativeElement.value = '';
    }
  }

  guardar(formulario: NgForm): void {
    this.mensajeError = '';
    this.mensajeAdvertencia = '';

    if (formulario.invalid) {
      formulario.control.markAllAsTouched();
      this.mensajeError = 'Revisa los campos obligatorios antes de continuar.';
      return;
    }

    const cuerpo = {
      id_objetivo: this.idEmpleadoEditando,
      id_guarderia: this.idGuarderia,
      id_empleado: this.idAdministrador,
      empleado: this.empleado,
    };

    this.enviando = true;
    const solicitud = this.modoEdicion
      ? this.http.put<RespuestaGuardado>(this.apiActualizacion, cuerpo)
      : this.http.post<RespuestaGuardado>(this.apiRegistro, cuerpo);

    solicitud.subscribe({
      next: (respuesta) => {
        this.actualizarUsuarioLocal(respuesta.id_empleado);
        if (this.fotoSeleccionada) {
          this.subirFoto(respuesta);
          return;
        }
        this.finalizarGuardado(respuesta);
      },
      error: (error) => {
        this.mensajeError =
          error.error?.mensaje ||
          (this.modoEdicion
            ? 'No fue posible actualizar el perfil.'
            : 'No fue posible registrar al empleado.');
        this.enviando = false;
        this.cdr.detectChanges();
      },
    });
  }

  volver(): void {
    if (this.modoEdicion && this.idEmpleadoEditando) {
      this.router.navigate(['/empleados', this.idEmpleadoEditando]);
      return;
    }
    this.router.navigate(['/administrar-empleados']);
  }

  verPerfil(): void {
    const id = this.idEmpleadoGuardado || this.idEmpleadoEditando;
    if (id) {
      this.router.navigate(['/empleados', id]);
    }
  }

  registrarOtro(): void {
    this.fotoActualUrl = null;
    this.descartarFotoSeleccionada();
    this.empleado = this.nuevoEmpleado();
    this.idEmpleadoGuardado = null;
    this.credencialTemporal = null;
    this.mensajeError = '';
    this.mensajeAdvertencia = '';
  }

  private cargarDetalle(): void {
    this.cargando = true;
    this.http
      .get<{ status: string; empleado: EmpleadoDetalle; mensaje?: string }>(
        this.apiDetalle,
        {
          params: {
            id_objetivo: String(this.idEmpleadoEditando),
            id_guarderia: String(this.idGuarderia),
            id_empleado: String(this.idAdministrador),
          },
        },
      )
      .subscribe({
        next: (respuesta) => {
          const detalle = respuesta.empleado;
          this.empleado = {
            nombres: detalle.nombres,
            apellidos: detalle.apellidos,
            email: detalle.email,
            telefono: detalle.telefono || '',
            puesto: detalle.puesto || '',
            fecha_ingreso: detalle.fecha_ingreso || '',
            rol: detalle.rol,
            notas: detalle.notas || '',
          };
          this.fotoActualUrl = detalle.foto_url;
          this.fotoPreview = detalle.foto_url || this.defaultUserImage;
          this.esCuentaPropia = detalle.es_solicitante;
          this.cargando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible cargar el perfil.';
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }

  private subirFoto(respuestaGuardado: RespuestaGuardado): void {
    if (!this.fotoSeleccionada) {
      this.finalizarGuardado(respuestaGuardado);
      return;
    }

    const datosFoto = new FormData();
    datosFoto.append('foto', this.fotoSeleccionada);
    datosFoto.append('id_objetivo', String(respuestaGuardado.id_empleado));
    datosFoto.append('id_guarderia', String(this.idGuarderia));
    datosFoto.append('id_empleado', String(this.idAdministrador));
    this.subiendoFoto = true;

    this.http.post<RespuestaFoto>(this.apiFoto, datosFoto).subscribe({
      next: (respuestaFoto) => {
        this.fotoActualUrl = respuestaFoto.foto_url;
        this.liberarFotoTemporal();
        this.fotoSeleccionada = null;
        this.fotoPreview = respuestaFoto.foto_url;
        this.actualizarFotoUsuarioLocal(
          respuestaGuardado.id_empleado,
          respuestaFoto.foto_url,
        );
        if (respuestaFoto.advertencia) {
          this.mensajeAdvertencia = respuestaFoto.advertencia;
        }
        this.finalizarGuardado(respuestaGuardado);
      },
      error: (error) => {
        const detalle =
          error.error?.mensaje || 'No fue posible subir la fotografía.';
        this.mensajeAdvertencia =
          'Los datos se guardaron correctamente, pero la fotografía no: ' + detalle;
        this.finalizarGuardado(respuestaGuardado);
      },
    });
  }

  private finalizarGuardado(respuesta: RespuestaGuardado): void {
    this.idEmpleadoGuardado = respuesta.id_empleado;
    this.credencialTemporal = respuesta.credencial_temporal || null;
    this.enviando = false;
    this.subiendoFoto = false;
    this.cdr.detectChanges();
  }

  private actualizarUsuarioLocal(idEmpleado: number): void {
    if (idEmpleado !== this.idAdministrador) {
      return;
    }

    const guardado = localStorage.getItem('safekids_usuario');
    if (!guardado) {
      return;
    }
    const usuario = JSON.parse(guardado);
    usuario.rol = this.empleado.rol;
    usuario.email = this.empleado.email;
    usuario.empleado = {
      ...usuario.empleado,
      nombres: this.empleado.nombres,
      apellidos: this.empleado.apellidos,
    };
    localStorage.setItem('safekids_usuario', JSON.stringify(usuario));
  }

  private actualizarFotoUsuarioLocal(idEmpleado: number, fotoUrl: string): void {
    if (idEmpleado !== this.idAdministrador) {
      return;
    }

    const guardado = localStorage.getItem('safekids_usuario');
    if (!guardado) {
      return;
    }
    const usuario = JSON.parse(guardado);
    usuario.empleado = { ...usuario.empleado, foto_url: fotoUrl };
    localStorage.setItem('safekids_usuario', JSON.stringify(usuario));
  }

  private liberarFotoTemporal(): void {
    if (this.fotoPreviewTemporal) {
      URL.revokeObjectURL(this.fotoPreviewTemporal);
      this.fotoPreviewTemporal = null;
    }
  }

  private nuevoEmpleado(): EmpleadoFormulario {
    return {
      nombres: '',
      apellidos: '',
      email: '',
      telefono: '',
      puesto: '',
      fecha_ingreso: this.fechaMaxima,
      rol: 'EMPLEADO',
      notas: '',
    };
  }
}
