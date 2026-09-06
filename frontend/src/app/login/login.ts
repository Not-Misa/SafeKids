import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';
import {
  SAFE_KIDS_TOKEN_EXPIRY_KEY,
  SAFE_KIDS_TOKEN_KEY,
  SAFE_KIDS_USER_KEY,
  clearSafeKidsSession,
} from '../auth/auth-session';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
  userInput = '';
  passwordInput = '';
  isLoading = false;
  readonly mensajeInformativo: string;

  constructor(
    private http: HttpClient,
    private router: Router,
    route: ActivatedRoute,
  ) {
    this.mensajeInformativo =
      route.snapshot.queryParamMap.get('password_actualizada') === '1'
        ? 'Contraseña actualizada. Inicia sesión nuevamente.'
        : '';
  }

  sessionStart() {
    if (!this.userInput.trim() || !this.passwordInput) {
      alert('Ingresa tu correo y contraseña.');
      return;
    }

    const packageData = {
      usuario_login: this.userInput.trim(),
      password_login: this.passwordInput,
      cliente: 'WEB',
    };

    this.isLoading = true;

    this.http
      .post('http://localhost/SafeKids-api/api/login.php', packageData)
      .pipe(finalize(() => (this.isLoading = false)))
      .subscribe({
      next: (respuesta: any) => {
        if (respuesta.status === 'success') {
          const usuario = respuesta.usuario;
          const empleado = usuario?.empleado;

          clearSafeKidsSession();
          localStorage.setItem(SAFE_KIDS_TOKEN_KEY, respuesta.token);
          localStorage.setItem(
            SAFE_KIDS_TOKEN_EXPIRY_KEY,
            respuesta.token_expira_en,
          );
          localStorage.setItem(SAFE_KIDS_USER_KEY, JSON.stringify(usuario));
          localStorage.setItem('id_usuario', String(usuario.id_usuario));
          localStorage.setItem('id_guarderia', String(usuario.id_guarderia));
          localStorage.setItem('rol', usuario.rol);

          if (empleado?.id_empleado) {
            localStorage.setItem('id_empleado', String(empleado.id_empleado));
          }

          this.router.navigate([
            usuario.requiere_cambio_password ? '/cambiar-password' : '/dashboard',
          ]);
          return;
        }

        alert(respuesta.mensaje || 'No fue posible iniciar sesion.');
      },
      error: (error) => {
        console.error('Error al iniciar sesion:', error);
        alert(error.error?.mensaje || 'No fue posible conectar con el backend.');
      },
    });
  }
}
