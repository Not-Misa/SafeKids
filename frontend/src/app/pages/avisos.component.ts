import { ChangeDetectorRef, Component, HostListener, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';

interface TipoAviso {
  codigo: string;
  nombre: string;
}

interface EmpleadoAviso {
  id_empleado: number;
  nombres: string;
  apellidos: string;
  foto_url: string | null;
}

interface EntregaAviso {
  destinatarios: number;
  push_enviados: number;
  vistos: number;
  confirmados: number;
}

interface AvisoGlobal {
  id_notificacion: number;
  titulo: string;
  mensaje: string;
  prioridad: 'NORMAL' | 'IMPORTANTE' | 'URGENTE';
  imagen_url: string | null;
  requiere_confirmacion: boolean;
  publicada_en: string;
  tipo: TipoAviso;
  empleado: EmpleadoAviso;
  entrega: EntregaAviso;
}

interface GrupoAvisos {
  fecha: string;
  etiqueta: string;
  avisos: AvisoGlobal[];
}

interface RespuestaAvisos {
  status: string;
  avisos: AvisoGlobal[];
  tipos: TipoAviso[];
  mensaje?: string;
}

@Component({
  selector: 'app-avisos',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, ShellComponent],
  templateUrl: './avisos.component.html',
  styleUrl: './avisos.component.css',
})
export class AvisosComponent implements OnInit {
  avisos: AvisoGlobal[] = [];
  tipos: TipoAviso[] = [];
  idGuarderia = 0;
  idEmpleado = 0;
  buscar = '';
  tipo = 'TODOS';
  prioridad = 'TODAS';
  fechaDesde = '';
  fechaHasta = '';
  cargando = false;
  mensajeError = '';
  avisoSeleccionado: AvisoGlobal | null = null;
  defaultUserImage = 'assets/images/default-user.jpg';

  private readonly apiAvisos = 'http://localhost/SafeKids-api/api/listar_avisos.php';

  constructor(
    private http: HttpClient,
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

    if (!this.idGuarderia || !this.idEmpleado) {
      this.router.navigate(['/login']);
      return;
    }

    this.cargarAvisos();
  }

  get gruposAvisos(): GrupoAvisos[] {
    const grupos = new Map<string, AvisoGlobal[]>();
    for (const aviso of this.avisos) {
      const fecha = aviso.publicada_en.slice(0, 10);
      const grupo = grupos.get(fecha) ?? [];
      grupo.push(aviso);
      grupos.set(fecha, grupo);
    }

    return Array.from(grupos.entries()).map(([fecha, avisos]) => ({
      fecha,
      etiqueta: this.formatearFecha(fecha),
      avisos,
    }));
  }

  get totalDestinatarios(): number {
    return this.avisos.reduce((total, aviso) => total + aviso.entrega.destinatarios, 0);
  }

  get totalVistos(): number {
    return this.avisos.reduce((total, aviso) => total + aviso.entrega.vistos, 0);
  }

  get avisosUrgentes(): number {
    return this.avisos.filter((aviso) => aviso.prioridad === 'URGENTE').length;
  }

  cargarAvisos(): void {
    this.mensajeError = '';
    this.cargando = true;

    const params: Record<string, string> = {
      id_guarderia: String(this.idGuarderia),
      id_empleado: String(this.idEmpleado),
      buscar: this.buscar.trim(),
      tipo: this.tipo,
      prioridad: this.prioridad,
      desde: this.fechaDesde,
      hasta: this.fechaHasta,
    };

    this.http.get<RespuestaAvisos>(this.apiAvisos, { params }).subscribe({
      next: (respuesta) => {
        this.avisos = respuesta.avisos;
        this.tipos = respuesta.tipos;
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.avisos = [];
        this.mensajeError =
          error.error?.mensaje || 'No fue posible consultar el historial de avisos.';
        this.cargando = false;
        this.cdr.detectChanges();
      },
    });
  }

  limpiarFiltros(): void {
    this.buscar = '';
    this.tipo = 'TODOS';
    this.prioridad = 'TODAS';
    this.fechaDesde = '';
    this.fechaHasta = '';
    this.cargarAvisos();
  }

  abrirAviso(aviso: AvisoGlobal): void {
    this.avisoSeleccionado = aviso;
  }

  cerrarAviso(): void {
    this.avisoSeleccionado = null;
  }

  @HostListener('document:keydown.escape')
  cerrarConEscape(): void {
    if (this.avisoSeleccionado) {
      this.cerrarAviso();
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

  clasePrioridad(prioridad: string): string {
    return prioridad.toLowerCase();
  }

  etiquetaPrioridad(prioridad: string): string {
    return prioridad.charAt(0) + prioridad.slice(1).toLowerCase();
  }
}
