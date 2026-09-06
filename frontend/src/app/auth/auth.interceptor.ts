import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import {
  SAFE_KIDS_TOKEN_KEY,
  clearSafeKidsSession,
} from './auth-session';
import {
  SAFEKIDS_API_BASE_URL,
  isSafeKidsApiUrl,
  rewriteSafeKidsApiUrl,
} from '../config/api.config';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const router = inject(Router);
  const token = localStorage.getItem(SAFE_KIDS_TOKEN_KEY);
  const isSafeKidsApi = isSafeKidsApiUrl(request.url);
  const rewrittenUrl = rewriteSafeKidsApiUrl(request.url);
  const apiRequest =
    rewrittenUrl === request.url
      ? request
      : request.clone({
          url: rewrittenUrl,
        });
  const authenticatedRequest =
    isSafeKidsApi && token
      ? apiRequest.clone({
          setHeaders: {
            Authorization: `Bearer ${token}`,
          },
        })
      : apiRequest;

  return next(authenticatedRequest).pipe(
    catchError((error: HttpErrorResponse) => {
      const isLoginRequest =
        rewrittenUrl === `${SAFEKIDS_API_BASE_URL}login.php`;

      if (isSafeKidsApi && !isLoginRequest && error.status === 401) {
        clearSafeKidsSession();
        void router.navigate(['/login']);
      }

      return throwError(() => error);
    }),
  );
};
