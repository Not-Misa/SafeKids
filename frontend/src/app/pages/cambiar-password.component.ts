import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import {
  clearSafeKidsSession,
  getStoredUser,
} from '../auth/auth-session';

@Component({
  selector: 'app-cambiar-password',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cambiar-password.component.html',
  styleUrl: './cambiar-password.component.css',
})
export class CambiarPasswordComponent {
  passwordActual = '';
  passwordNueva = '';
  confirmacionPassword = '';
  enviando = false;
  mensajeError = '';
  readonly cambioObligatorio = Boolean(getStoredUser()?.requiere_cambio_password);

  private readonly api = 'http://localhost/SafeKids-api/api/cambiar_password.php';

  constructor(
    private http: HttpClient,
    private router: Router,
  ) {}

  get criterios(): Array<{ texto: string; cumple: boolean }> {
    return [
      { texto: 'Entre 10 y 72 caracteres', cumple: this.passwordNueva.length >= 10 && this.passwordNueva.length <= 72 },
      { texto: 'Una letra mayúscula', cumple: /[A-Z]/.test(this.passwordNueva) },
      { texto: 'Una letra minúscula', cumple: /[a-z]/.test(this.passwordNueva) },
      { texto: 'Un número', cumple: /[0-9]/.test(this.passwordNueva) },
      { texto: 'Un carácter especial', cumple: /[^A-Za-z0-9]/.test(this.passwordNueva) },
    ];
  }

  get formularioValido(): boolean {
    return (
      this.passwordActual.length > 0 &&
      this.criterios.every((criterio) => criterio.cumple) &&
      this.passwordNueva === this.confirmacionPassword &&
      this.passwordNueva !== this.passwordActual
    );
  }

  cambiarPassword(): void {
    if (!this.formularioValido || this.enviando) {
      this.mensajeError = 'Revisa los campos y los requisitos de la nueva contraseña.';
      return;
    }

    this.enviando = true;
    this.mensajeError = '';
    this.http
      .post<{ status: string; mensaje: string }>(this.api, {
        password_actual: this.passwordActual,
        password_nueva: this.passwordNueva,
        confirmacion_password: this.confirmacionPassword,
      })
      .pipe(finalize(() => (this.enviando = false)))
      .subscribe({
        next: () => {
          clearSafeKidsSession();
          void this.router.navigate(['/login'], {
            queryParams: { password_actualizada: '1' },
          });
        },
        error: (error) => {
          this.mensajeError =
            error.error?.mensaje || 'No fue posible actualizar la contraseña.';
        },
      });
  }

  cancelar(): void {
    if (this.cambioObligatorio) {
      clearSafeKidsSession();
      void this.router.navigate(['/login']);
      return;
    }

    void this.router.navigate(['/dashboard']);
  }
}
