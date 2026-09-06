import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ShellComponent } from '../layout/shell.component';
import { NinoProfileNavComponent } from '../layout/nino-profile-nav.component';

interface TipoEvento {
  id_tipo_evento: number;
  codigo: string;
  nombre: string;
  descripcion: string | null;
  requiere_detalle: boolean;
  permite_imagen: boolean;
}

interface NinoHistorial {
  id_nino: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
  estado: string;
}

interface ImagenEvento {
  id_imagen: number;
  url: string;
  public_id: string;
  descripcion: string | null;
  orden: number;
}

interface ReporteDiario {
  id_evento: number;
  fecha_hora_evento: string;
  titulo: string | null;
  descripcion: string | null;
  detalle_json: Record<string, string> | null;
  nivel: string;
  tipo: {
    codigo: string;
    nombre: string;
  };
  empleado: {
    id_empleado: number;
    nombres: string;
    apellidos: string;
  };
  imagenes: ImagenEvento[];
}

interface RespuestaHistorial {
  status: string;
  nino: NinoHistorial;
  reportes: ReporteDiario[];
  mensaje?: string;
}

@Component({
  selector: 'app-reportes-historial',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    ShellComponent,
    NinoProfileNavComponent,
  ],
  templateUrl: './reportes-historial.component.html',
  styleUrl: './reportes-historial.component.css',
})
export class ReportesHistorialComponent implements OnInit {
  nino: NinoHistorial | null = null;
  tipos: TipoEvento[] = [];
  reportes: ReporteDiario[] = [];
  idNino = 0;
  idGuarderia = 0;
  idEmpleado = 0;
  fechaDesde = this.fechaInput(new Date(Date.now() - 6 * 86400000));
  fechaHasta = this.fechaInput();
  tipoFiltro = 'TODOS';
  cargando = true;
  mensajeError = '';
  imagenSeleccionada: ImagenEvento | null = null;
  readonly defaultUserImage = 'assets/images/default-user.jpg';
  readonly fechaMaxima = this.fechaInput();

  private readonly apiTipos = 'http://localhost/SafeKids-api/api/tipos_evento.php';
  private readonly apiHistorial =
    'http://localhost/SafeKids-api/api/listar_reportes.php';

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

  aplicarFiltros(): void {
    if (!this.fechaDesde || !this.fechaHasta || this.fechaDesde > this.fechaHasta) {
      this.mensajeError = 'Selecciona un rango de fechas válido.';
      return;
    }
    this.cargarReportes();
  }

  limpiarFiltros(): void {
    this.fechaDesde = this.fechaInput(new Date(Date.now() - 6 * 86400000));
    this.fechaHasta = this.fechaInput();
    this.tipoFiltro = 'TODOS';
    this.cargarReportes();
  }

  volverPerfil(): void {
    this.router.navigate(['/ninos', this.idNino]);
  }

  nuevoReporte(): void {
    this.router.navigate(['/ninos', this.idNino, 'reportes', 'nuevo']);
  }

  abrirImagen(imagen: ImagenEvento): void {
    this.imagenSeleccionada = imagen;
  }

  cerrarImagen(): void {
    this.imagenSeleccionada = null;
  }

  mostrarFecha(indice: number): boolean {
    if (indice === 0) {
      return true;
    }
    return (
      this.reportes[indice - 1].fecha_hora_evento.slice(0, 10) !==
      this.reportes[indice].fecha_hora_evento.slice(0, 10)
    );
  }

  fechaTitulo(fechaHora: string): string {
    const [anio, mes, dia] = fechaHora.slice(0, 10).split('-').map(Number);
    return new Intl.DateTimeFormat('es-MX', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    }).format(new Date(anio, mes - 1, dia));
  }

  hora(fechaHora: string): string {
    const hora = fechaHora.slice(11, 16);
    const [horas, minutos] = hora.split(':').map(Number);
    return new Intl.DateTimeFormat('es-MX', {
      hour: 'numeric',
      minute: '2-digit',
    }).format(new Date(2020, 0, 1, horas, minutos));
  }

  tituloReporte(reporte: ReporteDiario): string {
    return reporte.titulo?.trim() || reporte.tipo.nombre;
  }

  detalleLegible(detalle: Record<string, string> | null): Array<{
    etiqueta: string;
    valor: string;
  }> {
    if (!detalle) {
      return [];
    }
    const etiquetas: Record<string, string> = {
      alimento: 'Alimento',
      cantidad_consumida: 'Cantidad consumida',
      tipo_cambio: 'Tipo de cambio',
      resultado: 'Resultado',
      medicamento: 'Medicamento',
      dosis: 'Dosis',
      autorizado_por: 'Autorizado por',
      estado_animo: 'Estado de ánimo',
      actividad: 'Actividad',
      participacion: 'Participación',
      zona_cuerpo: 'Zona del cuerpo',
      accion_realizada: 'Acción realizada',
    };
    return Object.entries(detalle).map(([clave, valor]) => ({
      etiqueta: etiquetas[clave] || clave,
      valor,
    }));
  }

  inicialTipo(nombre: string): string {
    return nombre.trim().charAt(0).toUpperCase();
  }

  private cargarContexto(): void {
    this.cargando = true;
    forkJoin({
      tipos: this.http.get<{ status: string; tipos: TipoEvento[] }>(this.apiTipos, {
        params: {
          id_guarderia: String(this.idGuarderia),
          id_empleado: String(this.idEmpleado),
        },
      }),
      historial: this.solicitudHistorial(),
    }).subscribe({
      next: (respuesta) => {
        this.tipos = respuesta.tipos.tipos || [];
        this.aplicarRespuesta(respuesta.historial);
      },
      error: (error) => {
        this.mensajeError =
          error.error?.mensaje || 'No fue posible cargar el historial.';
        this.cargando = false;
        this.cdr.detectChanges();
      },
    });
  }

  private cargarReportes(): void {
    this.cargando = true;
    this.mensajeError = '';
    this.solicitudHistorial().subscribe({
      next: (respuesta) => this.aplicarRespuesta(respuesta),
      error: (error) => {
        this.mensajeError =
          error.error?.mensaje || 'No fue posible consultar los reportes.';
        this.cargando = false;
        this.cdr.detectChanges();
      },
    });
  }

  private solicitudHistorial() {
    return this.http.get<RespuestaHistorial>(this.apiHistorial, {
      params: {
        id_guarderia: String(this.idGuarderia),
        id_empleado: String(this.idEmpleado),
        id_nino: String(this.idNino),
        desde: this.fechaDesde,
        hasta: this.fechaHasta,
        tipo: this.tipoFiltro,
      },
    });
  }

  private aplicarRespuesta(respuesta: RespuestaHistorial): void {
    this.nino = respuesta.nino;
    this.reportes = respuesta.reportes || [];
    this.cargando = false;
    this.cdr.detectChanges();
  }

  private fechaInput(fecha = new Date()): string {
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
  }
}
