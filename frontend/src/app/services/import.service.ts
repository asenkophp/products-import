import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, switchMap, takeWhile, timer } from 'rxjs';
import { API_URL } from '../core/api-url';
import { ImportJob, isImportFinished } from '../models';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = inject(API_URL);

  /** Загрузить файл: сервер сразу возвращает задачу, импорт идёт в очереди. */
  upload(file: File): Observable<ImportJob> {
    const form = new FormData();
    form.append('file', file, file.name);

    return this.http.post<ImportJob>(`${this.apiUrl}/import`, form);
  }

  getStatus(id: number): Observable<ImportJob> {
    return this.http.get<ImportJob>(`${this.apiUrl}/import/${id}`);
  }

  /** Polling статуса задачи, пока она не завершится (последнее значение тоже отдаётся). */
  pollStatus(id: number, intervalMs = 1000): Observable<ImportJob> {
    return timer(0, intervalMs).pipe(
      switchMap(() => this.getStatus(id)),
      takeWhile((job) => !isImportFinished(job), true),
    );
  }
}
