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
import { forkJoin } from 'rxjs';
import { ShellComponent } from '../layout/shell.component';
import { NinoProfileNavComponent } from '../layout/nino-profile-nav.component';

interface NinoReporte {
  id_nino: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
  estado: string;
}

interface TipoEvento {
  id_tipo_evento: number;
  codigo: string;
  nombre: string;
  descripcion: string | null;
  requiere_detalle: boolean;
  permite_imagen: boolean;
}

interface DetalleFormulario {
  alimento: string;
  cantidad_consumida: string;
  tipo_cambio: string;
  resultado: string;
  medicamento: string;
  dosis: string;
  autorizado_por: string;
  estado_animo: string;
  actividad: string;
  participacion: string;
  zona_cuerpo: string;
  accion_realizada: string;
}

interface RespuestaReporte {
  status: string;
  mensaje: string;
  id_evento: number;
}

interface RespuestaFoto {
  status: string;
  mensaje: string;
  foto_url: string;
  foto_public_id: string;
  advertencia: string | null;
}

@Component({
  selector: 'app-reporte-registro',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ShellComponent,
    NinoProfileNavComponent,
  ],
  templateUrl: './reporte-registro.component.html',
  styleUrl: './reporte-registro.component.css',
})
export class ReporteRegistroComponent implements OnInit, OnDestroy {
  @ViewChild('fotoInput') fotoInput?: ElementRef<HTMLInputElement>;

  nino: NinoReporte | null = null;
  tipos: TipoEvento[] = [];
  idNino = 0;
  idGuarderia = 0;
  idEmpleado = 0;
  idTipoEvento: number | null = null;
  fechaHoraEvento = this.fechaHoraLocal();
  titulo = '';
  descripcion = '';
  nivel: 'INFORMATIVO' | 'IMPORTANTE' | 'URGENTE' = 'INFORMATIVO';
  detalle = this.nuevoDetalle();
  cargando = true;
  enviando = false;
  subiendoFoto = false;
  mensajeError = '';
  mensajeAdvertencia = '';
  idEventoCreado: number | null = null;
  fotoSeleccionada: File | null = null;
  fotoPreview: string | null = null;
  readonly fechaMinima = this.fechaHoraLocal(new Date(Date.now() - 90 * 86400000));
  readonly fechaMaxima = this.fechaHoraLocal(new Date(Date.now() + 5 * 60000));
  readonly defaultUserImage = 'assets/images/default-user.jpg';

  private fotoPreviewTemporal: string | null = null;
  private readonly apiNino = 'http://localhost/SafeKids-api/api/nino_detalle.php';
  private readonly apiTipos = 'http://localhost/SafeKids-api/api/tipos_evento.php';
  private readonly apiCrear = 'http://localhost/SafeKids-api/api/crear_reporte.php';
  private readonly apiFoto =
    'http://localhost/SafeKids-api/api/subir_foto_evento.php';

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

    this.cargarContexto();
  }

  ngOnDestroy(): void {
    this.liberarFotoTemporal();
  }

  get tipoSeleccionado(): TipoEvento | null {
    return (
      this.tipos.find((tipo) => tipo.id_tipo_evento === Number(this.idTipoEvento)) ||
      null
    );
  }

  alCambiarTipo(): void {
    this.detalle = this.nuevoDetalle();
    if (!this.tipoSeleccionado?.permite_imagen) {
      this.descartarFoto();
    }
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
      this.mensajeError = 'La evidencia debe ser JPG, PNG o WEBP.';
      return;
    }
    if (archivo.size > 5 * 1024 * 1024) {
      input.value = '';
      this.mensajeError = 'La evidencia debe pesar como máximo 5 MB.';
      return;
    }

    this.liberarFotoTemporal();
    this.fotoSeleccionada = archivo;
    this.fotoPreviewTemporal = URL.createObjectURL(archivo);
    this.fotoPreview = this.fotoPreviewTemporal;
  }

  descartarFoto(): void {
    this.liberarFotoTemporal();
    this.fotoSeleccionada = null;
    this.fotoPreview = null;
    if (this.fotoInput) {
      this.fotoInput.nativeElement.value = '';
    }
  }

  guardar(formulario: NgForm): void {
    this.mensajeError = '';
    this.mensajeAdvertencia = '';

    if (formulario.invalid || !this.tipoSeleccionado) {
      formulario.control.markAllAsTouched();
      this.mensajeError = 'Revisa los campos obligatorios antes de continuar.';
      return;
    }

    this.enviando = true;
    this.http
      .post<RespuestaReporte>(this.apiCrear, {
        id_guarderia: this.idGuarderia,
        id_empleado: this.idEmpleado,
        id_nino: this.idNino,
        id_tipo_evento: this.idTipoEvento,
        fecha_hora_evento: this.fechaHoraEvento,
        titulo: this.titulo,
        descripcion: this.descripcion,
        detalle_json: this.construirDetalle(),
        nivel: this.nivel,
      })
      .subscribe({
        next: (respuesta) => {
          if (this.fotoSeleccionada && this.tipoSeleccionado?.permite_imagen) {
            this.subirFoto(respuesta);
            return;
          }
          this.finalizarGuardado(respuesta.id_evento);
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible registrar el reporte.';
          this.enviando = false;
          this.cdr.detectChanges();
        },
      });
  }

  volverPerfil(): void {
    this.router.navigate(['/ninos', this.idNino]);
  }

  verHistorial(): void {
    this.router.navigate(['/ninos', this.idNino, 'reportes']);
  }

  registrarOtro(): void {
    this.idTipoEvento = null;
    this.fechaHoraEvento = this.fechaHoraLocal();
    this.titulo = '';
    this.descripcion = '';
    this.nivel = 'INFORMATIVO';
    this.detalle = this.nuevoDetalle();
    this.descartarFoto();
    this.idEventoCreado = null;
    this.mensajeError = '';
    this.mensajeAdvertencia = '';
  }

  private cargarContexto(): void {
    forkJoin({
      nino: this.http.get<{ status: string; nino: NinoReporte }>(this.apiNino, {
        params: {
          id_nino: String(this.idNino),
          id_guarderia: String(this.idGuarderia),
        },
      }),
      tipos: this.http.get<{ status: string; tipos: TipoEvento[] }>(this.apiTipos, {
        params: {
          id_guarderia: String(this.idGuarderia),
          id_empleado: String(this.idEmpleado),
        },
      }),
    }).subscribe({
      next: (respuesta) => {
        this.nino = respuesta.nino.nino;
        this.tipos = respuesta.tipos.tipos || [];
        if (this.nino.estado !== 'ACTIVO') {
          this.mensajeError =
            'Este expediente está inactivo y no admite nuevos reportes.';
        }
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.mensajeError =
          error.error?.mensaje || 'No fue posible cargar el formulario.';
        this.cargando = false;
        this.cdr.detectChanges();
      },
    });
  }

  private subirFoto(reporte: RespuestaReporte): void {
    if (!this.fotoSeleccionada) {
      this.finalizarGuardado(reporte.id_evento);
      return;
    }

    const datos = new FormData();
    datos.append('foto', this.fotoSeleccionada);
    datos.append('id_evento', String(reporte.id_evento));
    datos.append('id_guarderia', String(this.idGuarderia));
    datos.append('id_empleado', String(this.idEmpleado));
    this.subiendoFoto = true;

    this.http.post<RespuestaFoto>(this.apiFoto, datos).subscribe({
      next: (respuesta) => {
        if (respuesta.advertencia) {
          this.mensajeAdvertencia = respuesta.advertencia;
        }
        this.finalizarGuardado(reporte.id_evento);
      },
      error: (error) => {
        const detalle =
          error.error?.mensaje || 'No fue posible subir la evidencia.';
        this.mensajeAdvertencia =
          'El reporte se guardó correctamente, pero la evidencia no: ' + detalle;
        this.finalizarGuardado(reporte.id_evento);
      },
    });
  }

  private finalizarGuardado(idEvento: number): void {
    this.idEventoCreado = idEvento;
    this.enviando = false;
    this.subiendoFoto = false;
    this.cdr.detectChanges();
  }

  private construirDetalle(): Record<string, string> {
    const codigo = this.tipoSeleccionado?.codigo;
    const mapas: Record<string, Array<keyof DetalleFormulario>> = {
      ALIMENTACION: ['alimento', 'cantidad_consumida'],
      CAMBIO_PANAL: ['tipo_cambio'],
      BANO: ['resultado'],
      MEDICAMENTO: ['medicamento', 'dosis', 'autorizado_por'],
      ESTADO_ANIMO: ['estado_animo'],
      ACTIVIDAD: ['actividad', 'participacion'],
      INCIDENTE: ['zona_cuerpo', 'accion_realizada'],
    };
    const resultado: Record<string, string> = {};
    for (const clave of mapas[codigo || ''] || []) {
      const valor = this.detalle[clave].trim();
      if (valor) {
        resultado[clave] = valor;
      }
    }
    return resultado;
  }

  private nuevoDetalle(): DetalleFormulario {
    return {
      alimento: '',
      cantidad_consumida: '',
      tipo_cambio: '',
      resultado: '',
      medicamento: '',
      dosis: '',
      autorizado_por: '',
      estado_animo: '',
      actividad: '',
      participacion: '',
      zona_cuerpo: '',
      accion_realizada: '',
    };
  }

  private fechaHoraLocal(fecha = new Date()): string {
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
  }

  private liberarFotoTemporal(): void {
    if (this.fotoPreviewTemporal) {
      URL.revokeObjectURL(this.fotoPreviewTemporal);
      this.fotoPreviewTemporal = null;
    }
  }
}
