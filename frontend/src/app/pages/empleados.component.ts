import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';

interface EmpleadoListado {
  id_empleado: number;
  nombres: string;
  apellidos: string;
  telefono: string | null;
  puesto: string | null;
  fecha_ingreso: string | null;
  foto_url: string | null;
  email: string;
  estado: string;
  rol: string;
  requiere_cambio_password: boolean;
  ultimo_acceso: string | null;
  es_solicitante: boolean;
}

@Component({
  selector: 'app-empleados',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, ShellComponent],
  templateUrl: './empleados.component.html',
  styleUrl: './empleados.component.css',
})
export class EmpleadosComponent implements OnInit, OnDestroy {
  empleados: EmpleadoListado[] = [];
  busqueda = '';
  filtroEstado = 'TODOS';
  cargando = true;
  mensajeError = '';
  readonly defaultUserImage = 'assets/images/default-user.jpg';

  private idGuarderia = 0;
  private idEmpleado = 0;
  private busquedaTimer?: ReturnType<typeof setTimeout>;
  private readonly apiListado =
    'http://localhost/SafeKids-api/api/listar_empleados.php';

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
    if (
      usuario.rol !== 'ADMIN' ||
      !this.idGuarderia ||
      !this.idEmpleado
    ) {
      this.router.navigate(['/dashboard']);
      return;
    }

    this.cargarEmpleados();
  }

  ngOnDestroy(): void {
    if (this.busquedaTimer) {
      clearTimeout(this.busquedaTimer);
    }
  }

  onBusquedaChange(valor: string): void {
    this.busqueda = valor;
    if (this.busquedaTimer) {
      clearTimeout(this.busquedaTimer);
    }
    this.busquedaTimer = setTimeout(() => this.cargarEmpleados(), 250);
  }

  onEstadoChange(): void {
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.cargando = true;
    this.mensajeError = '';

    this.http
      .get<{ status: string; empleados: EmpleadoListado[]; mensaje?: string }>(
        this.apiListado,
        {
          params: {
            id_guarderia: String(this.idGuarderia),
            id_empleado: String(this.idEmpleado),
            q: this.busqueda.trim(),
            estado: this.filtroEstado,
          },
        },
      )
      .subscribe({
        next: (respuesta) => {
          this.empleados = respuesta.empleados || [];
          this.cargando = false;
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible conectar con el servidor.';
          this.empleados = [];
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }

  abrirPerfil(idEmpleado: number): void {
    this.router.navigate(['/empleados', idEmpleado]);
  }

  fechaCorta(fecha: string | null): string {
    if (!fecha) {
      return 'No registrada';
    }
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}`;
  }

  fechaHora(fecha: string | null): string {
    if (!fecha) {
      return 'Sin acceso';
    }
    const valor = new Date(fecha.replace(' ', 'T'));
    if (Number.isNaN(valor.getTime())) {
      return fecha;
    }
    return new Intl.DateTimeFormat('es-MX', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(valor);
  }

  rolLegible(rol: string): string {
    return rol === 'ADMIN' ? 'Administrador' : 'Empleado';
  }
}
