import { HttpErrorResponse } from '@angular/common/http';
import { ApiError } from '../models';

function isApiError(body: unknown): body is ApiError {
  return (
    typeof body === 'object' &&
    body !== null &&
    'error' in body &&
    typeof (body as ApiError).error?.message === 'string'
  );
}

/** Человекочитаемое сообщение из ошибки HTTP-запроса. */
export function extractErrorMessage(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (isApiError(error.error)) {
      return error.error.error.message;
    }
    if (error.status === 0) {
      return 'Сервер недоступен. Проверьте, что бэкенд запущен.';
    }
    return `Ошибка ${error.status}: ${error.statusText || 'неизвестная ошибка'}`;
  }

  return error instanceof Error ? error.message : 'Неизвестная ошибка';
}
