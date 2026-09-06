import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { ShellComponent } from '../layout/shell.component';

interface NinoListado {
  id_nino: number;
  nombres: string;
  apellidos: string;
  fecha_nacimiento: string;
  edad: number;
  genero: string;
  foto_url: string | null;
  estado: string;
  fecha_ingreso: string | null;
  alergias: string | null;
  cantidad_tutores: number;
}

@Component({
  selector: 'app-ninos',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, ShellComponent],
  templateUrl: './ninos.component.html',
  styleUrl: './ninos.component.css',
})
export class NinosComponent implements OnInit {
  ninos: NinoListado[] = [];
  busqueda = '';
  cargando = true;
  mensajeError = '';
  defaultUserImage = 'assets/images/default-user.jpg';

  private idGuarderia = 0;
  private busquedaTimer?: ReturnType<typeof setTimeout>;
  private readonly apiListado = 'http://localhost/SafeKids-api/api/listar_ninos.php';

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

    if (!this.idGuarderia) {
      this.router.navigate(['/login']);
      return;
    }

    this.cargarNinos();
  }

  onBusquedaChange(valor: string): void {
    this.busqueda = valor;

    if (this.busquedaTimer) {
      clearTimeout(this.busquedaTimer);
    }

    this.busquedaTimer = setTimeout(() => this.cargarNinos(), 250);
  }

  cargarNinos(): void {
    this.cargando = true;
    this.mensajeError = '';

    this.http
      .get<{ status: string; ninos: NinoListado[]; mensaje?: string }>(this.apiListado, {
        params: {
          id_guarderia: String(this.idGuarderia),
          q: this.busqueda.trim(),
        },
      })
      .subscribe({
        next: (respuesta) => {
          if (respuesta.status === 'success') {
            this.ninos = respuesta.ninos || [];
          } else {
            this.mensajeError = respuesta.mensaje || 'No fue posible consultar los niños.';
          }
          this.cdr.detectChanges();
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible conectar con el servidor.';
          this.ninos = [];
          this.cargando = false;
          this.cdr.detectChanges();
        },
        complete: () => {
          this.cargando = false;
          this.cdr.detectChanges();
        },
      });
  }

  abrirPerfil(idNino: number): void {
    this.router.navigate(['/ninos', idNino]);
  }

  generoLegible(genero: string): string {
    const generos: Record<string, string> = {
      FEMENINO: 'Femenino',
      MASCULINO: 'Masculino',
      OTRO: 'Otro',
      NO_ESPECIFICADO: 'No especificado',
    };
    return generos[genero] || genero;
  }

  fechaCorta(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}`;
  }
}
