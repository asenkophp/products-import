import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { API_URL } from './api-url';

/**
 * Добавляет JWT в запросы к API. На 401 (токен истёк или отозван) завершает сессию
 * и отправляет на страницу входа, запомнив, куда пользователь шёл.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const apiUrl = inject(API_URL);

  const token = auth.token();
  const isApiRequest = req.url.startsWith(apiUrl);
  const request =
    token && isApiRequest ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

  return next(request).pipe(
    catchError((error: unknown) => {
      const isLoginRequest = req.url.endsWith('/auth/login');
      if (error instanceof HttpErrorResponse && error.status === 401 && isApiRequest && !isLoginRequest) {
        auth.logout();
        void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
      }
      return throwError(() => error);
    }),
  );
};
