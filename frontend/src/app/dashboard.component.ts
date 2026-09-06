import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ShellComponent } from './layout/shell.component';
import { clearSafeKidsSession } from './auth/auth-session';

interface NinoResumen {
  id_nino: number;
  nombres: string;
  apellidos: string;
  fecha_nacimiento?: string;
  edad?: number;
  genero?: string;
  foto_url?: string | null;
  codigo_qr?: string | null;
  estado?: string;
  alergias?: string | null;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, ShellComponent],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css'],
})
export class DashboardComponent implements OnInit {
  usuario: any = null;
  empleado: any = null;
  idEmpleadoActual!: number;
  idGuarderiaActual!: number;
  storageKey!: string;
  ninosRecientes: NinoResumen[] = [];
  ninosEncontrados: NinoResumen[] = [];
  busquedaQuery = '';
  buscandoNinos = false;
  busquedaError = '';
  defaultUserImage = 'assets/images/default-user.jpg';

  private busquedaTimer?: ReturnType<typeof setTimeout>;
  private readonly apiBusquedaNinos = 'http://localhost/SafeKids-api/api/buscar_ninos.php';

  constructor(
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

    this.usuario = JSON.parse(usuarioGuardado);
    this.empleado = this.usuario.empleado;
    this.idGuarderiaActual = Number(this.usuario.id_guarderia);

    if (!this.empleado?.id_empleado || !this.idGuarderiaActual) {
      this.logout();
      return;
    }

    this.idEmpleadoActual = Number(this.empleado.id_empleado);
    this.storageKey = `recientes_safekids_${this.idEmpleadoActual}`;
    this.cargarBusquedasRecientes();
  }

  cargarBusquedasRecientes(): void {
    const locales = localStorage.getItem(this.storageKey);
    this.ninosRecientes = locales ? JSON.parse(locales) : [];
  }

  onBusquedaChange(valor: string): void {
    this.busquedaQuery = valor;
    this.busquedaError = '';

    if (this.busquedaTimer) {
      clearTimeout(this.busquedaTimer);
    }

    const texto = valor.trim();
    if (texto.length === 0) {
      this.buscandoNinos = false;
      this.ninosEncontrados = [];
      return;
    }

    this.busquedaTimer = setTimeout(() => {
      this.buscarNinos(texto);
    }, 250);
  }

  buscarNinos(texto: string): void {
    this.buscandoNinos = true;

    this.http
      .get<{ status: string; ninos: NinoResumen[]; mensaje?: string }>(this.apiBusquedaNinos, {
        params: {
          id_guarderia: String(this.idGuarderiaActual),
          q: texto,
        },
      })
      .subscribe({
        next: (respuesta) => {
          if (respuesta.status !== 'success') {
            this.busquedaError = respuesta.mensaje || 'No fue posible realizar la busqueda.';
            this.ninosEncontrados = [];
            this.cdr.detectChanges();
            return;
          }

          this.ninosEncontrados = [...(respuesta.ninos || [])];
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error al buscar ninos:', error);
          this.busquedaError = error.error?.mensaje || 'No fue posible conectar con el backend.';
          this.ninosEncontrados = [];
          this.cdr.detectChanges();
        },
        complete: () => {
          this.buscandoNinos = false;
          this.cdr.detectChanges();
        },
      });
  }

  seleccionarNino(nino: NinoResumen): void {
    const recientesSinDuplicado = this.ninosRecientes.filter(
      (ninoReciente) => ninoReciente.id_nino !== nino.id_nino,
    );

    this.ninosRecientes = [nino, ...recientesSinDuplicado].slice(0, 5);
    localStorage.setItem(this.storageKey, JSON.stringify(this.ninosRecientes));

    this.busquedaQuery = '';
    this.ninosEncontrados = [];
    this.busquedaError = '';
    this.abrirNino(nino);
  }

  abrirNino(nino: NinoResumen): void {
    this.router.navigate(['/ninos', nino.id_nino]);
  }

  logout(): void {
    clearSafeKidsSession();
    this.router.navigate(['/login']);
  }
}
