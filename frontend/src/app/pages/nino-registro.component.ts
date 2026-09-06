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

type ModoTutor = 'NUEVO' | 'EXISTENTE';

interface TutorExistente {
  id_tutor: number;
  nombres: string;
  apellidos: string;
  email: string;
  telefono: string;
  telefono_alterno: string | null;
  direccion: string | null;
  cantidad_hijos?: number;
}

interface TutorFormulario {
  id_tutor?: number;
  modo: ModoTutor;
  nombres: string;
  apellidos: string;
  parentesco: string;
  email: string;
  telefono: string;
  telefono_alterno: string;
  direccion: string;
  recibe_notificaciones: boolean;
  autorizado_recoger: boolean;
  busqueda: string;
  resultados: TutorExistente[];
  buscando: boolean;
  mensaje_busqueda: string;
}

interface ContactoFormulario {
  id_contacto?: number;
  nombres: string;
  apellidos: string;
  parentesco: string;
  telefono: string;
  email: string;
  direccion: string;
  identificacion_referencia: string;
  autorizado_recoger: boolean;
  observaciones: string;
}

interface CredencialTemporal {
  nombre: string;
  email: string;
  password_temporal: string;
}

interface RespuestaGuardado {
  status: string;
  mensaje: string;
  id_nino: number;
  credenciales_nuevas: CredencialTemporal[];
  tutores_vinculados?: Array<
    TutorExistente & {
      parentesco: string;
      recibe_notificaciones: boolean;
      autorizado_recoger: boolean;
    }
  >;
}

interface RespuestaFoto {
  status: string;
  mensaje: string;
  foto_url: string;
  foto_public_id: string;
  advertencia: string | null;
}

interface DetalleEdicion {
  id_nino: number;
  nombres: string;
  apellidos: string;
  fecha_nacimiento: string;
  genero: string;
  fecha_ingreso: string | null;
  foto_url: string | null;
  expediente_medico: Record<string, string | null>;
  tutores: Array<{
    id_tutor: number;
    nombres: string;
    apellidos: string;
    parentesco: string;
    email: string;
    telefono: string;
    telefono_alterno: string | null;
    direccion: string | null;
    recibe_notificaciones: boolean;
    autorizado_recoger: boolean;
  }>;
  contactos_autorizados: Array<{
    id_contacto: number;
    nombres: string;
    apellidos: string;
    parentesco: string | null;
    telefono: string;
    email: string | null;
    direccion: string | null;
    identificacion_referencia: string | null;
    autorizado_recoger: boolean;
    observaciones: string | null;
  }>;
}

@Component({
  selector: 'app-nino-registro',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, ShellComponent],
  templateUrl: './nino-registro.component.html',
  styleUrl: './nino-registro.component.css',
})
export class NinoRegistroComponent implements OnInit, OnDestroy {
  @ViewChild('fotoInput') fotoInput?: ElementRef<HTMLInputElement>;

  idGuarderia = 0;
  idEmpleado = 0;
  idNinoEditando: number | null = null;
  fechaMaxima = new Date().toISOString().slice(0, 10);
  readonly defaultUserImage = 'assets/images/default-user.jpg';
  modoEdicion = false;
  cargandoDatos = false;
  enviando = false;
  subiendoFoto = false;
  mensajeError = '';
  mensajeAdvertencia = '';
  idNinoCreado: number | null = null;
  credencialesNuevas: CredencialTemporal[] = [];
  tutoresVinculados: NonNullable<RespuestaGuardado['tutores_vinculados']> = [];
  fotoSeleccionada: File | null = null;
  fotoActualUrl: string | null = null;
  fotoPreview = this.defaultUserImage;

  nino = this.nuevoNino();
  expediente = this.nuevoExpediente();
  tutores: TutorFormulario[] = [this.nuevoTutor()];
  contactos: ContactoFormulario[] = [this.nuevoContacto()];

  private readonly apiRegistro = 'http://localhost/SafeKids-api/api/registrar_nino.php';
  private readonly apiActualizacion =
    'http://localhost/SafeKids-api/api/actualizar_nino.php';
  private readonly apiDetalle = 'http://localhost/SafeKids-api/api/nino_detalle.php';
  private readonly apiFoto = 'http://localhost/SafeKids-api/api/subir_foto_nino.php';
  private readonly apiBuscarTutores =
    'http://localhost/SafeKids-api/api/buscar_tutores.php';
  private fotoPreviewTemporal: string | null = null;

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
    this.idEmpleado = Number(usuario.empleado?.id_empleado);

    if (!this.idGuarderia || !this.idEmpleado || usuario.rol !== 'ADMIN') {
      this.router.navigate(['/login']);
      return;
    }

    this.modoEdicion = this.route.snapshot.data['modo'] === 'editar';
    if (this.modoEdicion) {
      this.idNinoEditando = Number(this.route.snapshot.paramMap.get('id'));
      if (!this.idNinoEditando) {
        this.router.navigate(['/administrar-clientes']);
        return;
      }
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
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
    if (!tiposPermitidos.includes(archivo.type)) {
      input.value = '';
      this.mensajeError = 'La fotografia debe ser JPG, PNG o WEBP.';
      return;
    }

    if (archivo.size > 5 * 1024 * 1024) {
      input.value = '';
      this.mensajeError = 'La fotografia debe pesar como maximo 5 MB.';
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

  agregarTutor(): void {
    if (this.tutores.length < 2) {
      this.tutores.push(this.nuevoTutor());
    }
  }

  eliminarTutor(indice: number): void {
    if (this.tutores.length > 1) {
      this.tutores.splice(indice, 1);
    }
  }

  cambiarModoTutor(indice: number, modo: ModoTutor): void {
    const tutorActual = this.tutores[indice];
    if (!tutorActual || tutorActual.modo === modo) {
      return;
    }

    const tutorNuevo = this.nuevoTutor(modo);
    tutorNuevo.parentesco = tutorActual.parentesco;
    tutorNuevo.recibe_notificaciones = tutorActual.recibe_notificaciones;
    tutorNuevo.autorizado_recoger = tutorActual.autorizado_recoger;
    this.tutores[indice] = tutorNuevo;

    if (modo === 'EXISTENTE') {
      this.buscarTutores(indice);
    }
  }

  buscarTutores(indice: number): void {
    const tutor = this.tutores[indice];
    if (!tutor || tutor.modo !== 'EXISTENTE' || tutor.buscando) {
      return;
    }

    tutor.buscando = true;
    tutor.mensaje_busqueda = '';
    this.http
      .get<{ status: string; tutores: TutorExistente[]; mensaje?: string }>(
        this.apiBuscarTutores,
        {
          params: {
            id_guarderia: String(this.idGuarderia),
            id_empleado: String(this.idEmpleado),
            q: tutor.busqueda.trim(),
          },
        },
      )
      .subscribe({
        next: (respuesta) => {
          const idsSeleccionados = new Set(
            this.tutores
              .filter((_, posicion) => posicion !== indice)
              .map((item) => item.id_tutor)
              .filter((id): id is number => Boolean(id)),
          );
          tutor.resultados = (respuesta.tutores || []).filter(
            (resultado) => !idsSeleccionados.has(resultado.id_tutor),
          );
          tutor.mensaje_busqueda = tutor.resultados.length
            ? ''
            : 'No se encontraron tutores disponibles.';
          tutor.buscando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          tutor.resultados = [];
          tutor.mensaje_busqueda =
            error.error?.mensaje || 'No fue posible consultar los tutores.';
          tutor.buscando = false;
          this.cdr.detectChanges();
        },
      });
  }

  seleccionarTutor(indice: number, seleccionado: TutorExistente): void {
    const tutor = this.tutores[indice];
    if (!tutor) {
      return;
    }

    tutor.id_tutor = seleccionado.id_tutor;
    tutor.nombres = seleccionado.nombres;
    tutor.apellidos = seleccionado.apellidos;
    tutor.email = seleccionado.email;
    tutor.telefono = seleccionado.telefono;
    tutor.telefono_alterno = seleccionado.telefono_alterno || '';
    tutor.direccion = seleccionado.direccion || '';
    tutor.busqueda = '';
    tutor.resultados = [];
    tutor.mensaje_busqueda = '';
  }

  quitarTutorSeleccionado(indice: number): void {
    const tutor = this.tutores[indice];
    if (!tutor) {
      return;
    }

    const reemplazo = this.nuevoTutor('EXISTENTE');
    reemplazo.parentesco = tutor.parentesco;
    reemplazo.recibe_notificaciones = tutor.recibe_notificaciones;
    reemplazo.autorizado_recoger = tutor.autorizado_recoger;
    this.tutores[indice] = reemplazo;
    this.buscarTutores(indice);
  }

  agregarContacto(): void {
    if (this.contactos.length < 2) {
      this.contactos.push(this.nuevoContacto());
    }
  }

  eliminarContacto(indice: number): void {
    this.contactos.splice(indice, 1);
  }

  guardar(formulario: NgForm): void {
    this.mensajeError = '';
    this.mensajeAdvertencia = '';

    if (formulario.invalid) {
      formulario.control.markAllAsTouched();
      this.mensajeError = 'Revisa los campos obligatorios antes de continuar.';
      return;
    }

    const tutorExistenteSinSeleccion = this.tutores.find(
      (tutor) => tutor.modo === 'EXISTENTE' && !tutor.id_tutor,
    );
    if (tutorExistenteSinSeleccion) {
      this.mensajeError = 'Selecciona la cuenta del tutor existente antes de continuar.';
      return;
    }

    const referencias = this.tutores.map((tutor) =>
      tutor.modo === 'EXISTENTE'
        ? `id:${tutor.id_tutor}`
        : `email:${tutor.email.trim().toLowerCase()}`,
    );
    if (new Set(referencias).size !== referencias.length) {
      this.mensajeError = 'Cada tutor debe utilizar un correo diferente.';
      return;
    }

    const tutoresPayload = this.tutores.map((tutor) => {
      const relacion = {
        parentesco: tutor.parentesco,
        recibe_notificaciones: tutor.recibe_notificaciones,
        autorizado_recoger: tutor.autorizado_recoger,
      };

      if (!this.modoEdicion && tutor.modo === 'EXISTENTE') {
        return { id_tutor: tutor.id_tutor, ...relacion };
      }

      return {
        id_tutor: tutor.id_tutor,
        nombres: tutor.nombres,
        apellidos: tutor.apellidos,
        email: tutor.email,
        telefono: tutor.telefono,
        telefono_alterno: tutor.telefono_alterno,
        direccion: tutor.direccion,
        ...relacion,
      };
    });

    const cuerpo = {
      id_nino: this.idNinoEditando,
      id_guarderia: this.idGuarderia,
      id_empleado: this.idEmpleado,
      nino: this.nino,
      expediente_medico: this.expediente,
      tutores: tutoresPayload,
      contactos_autorizados: this.contactos,
    };

    this.enviando = true;
    const solicitud = this.modoEdicion
      ? this.http.put<RespuestaGuardado>(this.apiActualizacion, cuerpo)
      : this.http.post<RespuestaGuardado>(this.apiRegistro, cuerpo);

    solicitud.subscribe({
      next: (respuesta) => {
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
            ? 'No fue posible actualizar el expediente.'
            : 'No fue posible registrar al niño.');
        this.enviando = false;
        this.subiendoFoto = false;
        this.cdr.detectChanges();
      },
    });
  }

  verPerfil(): void {
    const idNino = this.idNinoCreado || this.idNinoEditando;
    if (idNino) {
      this.router.navigate(['/ninos', idNino]);
    }
  }

  registrarOtro(): void {
    this.prepararNuevoRegistro([this.nuevoTutor()]);
  }

  registrarHermano(): void {
    const tutores = this.tutoresVinculados.map((tutor) => ({
      ...this.nuevoTutor('EXISTENTE'),
      id_tutor: tutor.id_tutor,
      nombres: tutor.nombres,
      apellidos: tutor.apellidos,
      email: tutor.email,
      telefono: tutor.telefono,
      telefono_alterno: tutor.telefono_alterno || '',
      direccion: tutor.direccion || '',
      parentesco: tutor.parentesco,
      recibe_notificaciones: tutor.recibe_notificaciones,
      autorizado_recoger: tutor.autorizado_recoger,
    }));
    this.prepararNuevoRegistro(tutores.length ? tutores : [this.nuevoTutor()]);
  }

  private prepararNuevoRegistro(tutores: TutorFormulario[]): void {
    this.fotoActualUrl = null;
    this.descartarFotoSeleccionada();
    this.nino = this.nuevoNino();
    this.expediente = this.nuevoExpediente();
    this.tutores = tutores;
    this.contactos = [this.nuevoContacto()];
    this.idNinoCreado = null;
    this.credencialesNuevas = [];
    this.tutoresVinculados = [];
    this.mensajeError = '';
    this.mensajeAdvertencia = '';
  }

  volverAlListado(): void {
    if (this.modoEdicion && this.idNinoEditando) {
      this.router.navigate(['/ninos', this.idNinoEditando]);
      return;
    }
    this.router.navigate(['/administrar-clientes']);
  }

  private cargarDetalle(): void {
    this.cargandoDatos = true;
    this.http
      .get<{ status: string; nino: DetalleEdicion; mensaje?: string }>(this.apiDetalle, {
        params: {
          id_nino: String(this.idNinoEditando),
          id_guarderia: String(this.idGuarderia),
        },
      })
      .subscribe({
        next: (respuesta) => {
          const detalle = respuesta.nino;
          this.nino = {
            nombres: detalle.nombres,
            apellidos: detalle.apellidos,
            fecha_nacimiento: detalle.fecha_nacimiento,
            genero: detalle.genero,
            fecha_ingreso: detalle.fecha_ingreso || '',
          };
          this.fotoActualUrl = detalle.foto_url;
          this.fotoPreview = detalle.foto_url || this.defaultUserImage;
          this.expediente = {
            tipo_sangre: detalle.expediente_medico['tipo_sangre'] || '',
            alergias: detalle.expediente_medico['alergias'] || '',
            padecimientos: detalle.expediente_medico['padecimientos'] || '',
            medicamentos_habituales:
              detalle.expediente_medico['medicamentos_habituales'] || '',
            restricciones_alimentarias:
              detalle.expediente_medico['restricciones_alimentarias'] || '',
            medico_nombre: detalle.expediente_medico['medico_nombre'] || '',
            medico_telefono: detalle.expediente_medico['medico_telefono'] || '',
            institucion_medica: detalle.expediente_medico['institucion_medica'] || '',
            numero_seguro: detalle.expediente_medico['numero_seguro'] || '',
            indicaciones_emergencia:
              detalle.expediente_medico['indicaciones_emergencia'] || '',
            observaciones: detalle.expediente_medico['observaciones'] || '',
          };
          this.tutores = detalle.tutores.map((tutor) => ({
            id_tutor: tutor.id_tutor,
            modo: 'EXISTENTE',
            nombres: tutor.nombres,
            apellidos: tutor.apellidos,
            parentesco: tutor.parentesco,
            email: tutor.email,
            telefono: tutor.telefono,
            telefono_alterno: tutor.telefono_alterno || '',
            direccion: tutor.direccion || '',
            recibe_notificaciones: tutor.recibe_notificaciones,
            autorizado_recoger: tutor.autorizado_recoger,
            busqueda: '',
            resultados: [],
            buscando: false,
            mensaje_busqueda: '',
          }));
          this.contactos = detalle.contactos_autorizados.map((contacto) => ({
            id_contacto: contacto.id_contacto,
            nombres: contacto.nombres,
            apellidos: contacto.apellidos,
            parentesco: contacto.parentesco || '',
            telefono: contacto.telefono,
            email: contacto.email || '',
            direccion: contacto.direccion || '',
            identificacion_referencia: contacto.identificacion_referencia || '',
            autorizado_recoger: contacto.autorizado_recoger,
            observaciones: contacto.observaciones || '',
          }));
          this.cargandoDatos = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible cargar el expediente.';
          this.cargandoDatos = false;
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
    datosFoto.append('id_nino', String(respuestaGuardado.id_nino));
    datosFoto.append('id_guarderia', String(this.idGuarderia));
    datosFoto.append('id_empleado', String(this.idEmpleado));
    this.subiendoFoto = true;

    this.http.post<RespuestaFoto>(this.apiFoto, datosFoto).subscribe({
      next: (respuestaFoto) => {
        this.fotoActualUrl = respuestaFoto.foto_url;
        this.liberarFotoTemporal();
        this.fotoSeleccionada = null;
        this.fotoPreview = respuestaFoto.foto_url;
        if (respuestaFoto.advertencia) {
          this.mensajeAdvertencia = respuestaFoto.advertencia;
        }
        this.finalizarGuardado(respuestaGuardado);
      },
      error: (error) => {
        const detalle =
          error.error?.mensaje || 'No fue posible subir la fotografia seleccionada.';
        this.mensajeAdvertencia =
          'Los datos se guardaron correctamente, pero la fotografia no: ' + detalle;
        this.finalizarGuardado(respuestaGuardado);
      },
    });
  }

  private finalizarGuardado(respuesta: RespuestaGuardado): void {
    this.idNinoCreado = respuesta.id_nino;
    this.credencialesNuevas = respuesta.credenciales_nuevas || [];
    this.tutoresVinculados = respuesta.tutores_vinculados || [];
    this.enviando = false;
    this.subiendoFoto = false;
    this.cdr.detectChanges();
  }

  private liberarFotoTemporal(): void {
    if (this.fotoPreviewTemporal) {
      URL.revokeObjectURL(this.fotoPreviewTemporal);
      this.fotoPreviewTemporal = null;
    }
  }

  private nuevoNino() {
    return {
      nombres: '',
      apellidos: '',
      fecha_nacimiento: '',
      genero: 'NO_ESPECIFICADO',
      fecha_ingreso: this.fechaMaxima,
    };
  }

  private nuevoExpediente() {
    return {
      tipo_sangre: '',
      alergias: '',
      padecimientos: '',
      medicamentos_habituales: '',
      restricciones_alimentarias: '',
      medico_nombre: '',
      medico_telefono: '',
      institucion_medica: '',
      numero_seguro: '',
      indicaciones_emergencia: '',
      observaciones: '',
    };
  }

  private nuevoTutor(modo: ModoTutor = 'NUEVO'): TutorFormulario {
    return {
      modo,
      nombres: '',
      apellidos: '',
      parentesco: '',
      email: '',
      telefono: '',
      telefono_alterno: '',
      direccion: '',
      recibe_notificaciones: true,
      autorizado_recoger: true,
      busqueda: '',
      resultados: [],
      buscando: false,
      mensaje_busqueda: '',
    };
  }

  private nuevoContacto(): ContactoFormulario {
    return {
      nombres: '',
      apellidos: '',
      parentesco: '',
      telefono: '',
      email: '',
      direccion: '',
      identificacion_referencia: '',
      autorizado_recoger: false,
      observaciones: '',
    };
  }
}
