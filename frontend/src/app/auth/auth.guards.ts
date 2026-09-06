import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import {
  clearSafeKidsSession,
  getStoredUser,
  hasActiveSession,
} from './auth-session';

export const authGuard: CanActivateFn = () => {
  const router = inject(Router);

  if (hasActiveSession()) {
    return true;
  }

  clearSafeKidsSession();
  return router.createUrlTree(['/login']);
};

export const webRoleGuard: CanActivateFn = () => {
  const router = inject(Router);
  const role = getStoredUser()?.rol;

  if (role === 'ADMIN' || role === 'EMPLEADO') {
    return true;
  }

  clearSafeKidsSession();
  return router.createUrlTree(['/login']);
};

export const adminGuard: CanActivateFn = () => {
  const router = inject(Router);

  if (getStoredUser()?.rol === 'ADMIN') {
    return true;
  }

  return router.createUrlTree(['/dashboard']);
};

export const passwordUpdatedGuard: CanActivateFn = () => {
  const router = inject(Router);

  if (!getStoredUser()?.requiere_cambio_password) {
    return true;
  }

  return router.createUrlTree(['/cambiar-password']);
};
