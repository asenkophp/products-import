import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { catchError, throwError } from 'rxjs';
import { extractErrorMessage } from './http-error';

/**
 * Глобальная обработка ошибок HTTP: сервер недоступен (0), перегрузка (429)
 * и ошибки сервера (5xx) показываются уведомлением. Ошибки валидации (4xx)
 * обрабатывают сами страницы.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const snackBar = inject(MatSnackBar);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (
        error instanceof HttpErrorResponse &&
        (error.status === 0 || error.status === 429 || error.status >= 500)
      ) {
        snackBar.open(extractErrorMessage(error), 'Закрыть', { duration: 5000 });
      }
      return throwError(() => error);
    }),
  );
};
