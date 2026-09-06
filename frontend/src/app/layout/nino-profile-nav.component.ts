import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';
import { RouterModule } from '@angular/router';

export type NinoProfileSection =
  | 'perfil'
  | 'notificacion'
  | 'reporte'
  | 'historial'
  | 'notificaciones';

@Component({
  selector: 'app-nino-profile-nav',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './nino-profile-nav.component.html',
  styleUrl: './nino-profile-nav.component.css',
})
export class NinoProfileNavComponent {
  @Input() idNino = 0;
  @Input() active: NinoProfileSection = 'perfil';
  @Input() ninoActivo = true;
}
