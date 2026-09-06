import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, HostListener, Input } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { finalize } from 'rxjs';
import { clearSafeKidsSession } from '../auth/auth-session';

interface NavItem {
  id: string;
  label: string;
  route: string;
  soloAdmin?: boolean;
}

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './shell.component.html',
  styleUrl: './shell.component.css',
})
export class ShellComponent {
  @Input() active = 'inicio';

  usuario: any = null;
  empleado: any = null;
  defaultUserImage = 'assets/images/default-user.jpg';
  menuAjustesAbierto = false;
  mostrarAcercaDe = false;

  navItems: NavItem[] = [
    { id: 'inicio', label: 'Inicio', route: '/dashboard' },
    { id: 'avisos', label: 'Avisos', route: '/avisos' },
    { id: 'notificaciones', label: 'Notificaciones', route: '/notificaciones' },
    { id: 'ninos', label: 'Niños', route: '/administrar-clientes' },
    {
      id: 'empleados',
      label: 'Empleados',
      route: '/administrar-empleados',
      soloAdmin: true,
    },
  ];
  navItemsVisibles: NavItem[] = [];

  constructor(
    private router: Router,
    private http: HttpClient,
  ) {
    const usuarioGuardado = localStorage.getItem('safekids_usuario');
    if (usuarioGuardado) {
      this.usuario = JSON.parse(usuarioGuardado);
      this.empleado = this.usuario?.empleado;
    }
    this.navItemsVisibles = this.navItems.filter(
      (item) => !item.soloAdmin || this.usuario?.rol === 'ADMIN',
    );
  }

  toggleAjustes(evento: MouseEvent): void {
    evento.stopPropagation();
    this.menuAjustesAbierto = !this.menuAjustesAbierto;
  }

  abrirAcercaDe(): void {
    this.menuAjustesAbierto = false;
    this.mostrarAcercaDe = true;
  }

  abrirCambioPassword(): void {
    this.menuAjustesAbierto = false;
    void this.router.navigate(['/cambiar-password']);
  }

  cerrarAcercaDe(): void {
    this.mostrarAcercaDe = false;
  }

  @HostListener('document:click')
  cerrarMenuAjustes(): void {
    this.menuAjustesAbierto = false;
  }

  @HostListener('document:keydown.escape')
  manejarEscape(): void {
    if (this.mostrarAcercaDe) {
      this.cerrarAcercaDe();
      return;
    }

    this.menuAjustesAbierto = false;
  }

  logout() {
    this.menuAjustesAbierto = false;
    this.http
      .post('http://localhost/SafeKids-api/api/logout.php', {})
      .pipe(
        finalize(() => {
          clearSafeKidsSession();
          void this.router.navigate(['/login']);
        }),
      )
      .subscribe({ error: () => undefined });
  }
}
