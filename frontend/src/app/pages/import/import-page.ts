import { AsyncPipe, DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Observable, Subject, catchError, map, of, startWith, switchMap } from 'rxjs';
import { extractErrorMessage } from '../../core/http-error';
import { ImportJob, ImportStatus } from '../../models';
import { ImportService } from '../../services/import.service';

const ALLOWED_EXTENSIONS = ['.xlsx', '.ods'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

type ImportViewState =
  | { kind: 'idle' }
  | { kind: 'uploading'; fileName: string }
  | { kind: 'progress'; job: ImportJob }
  | { kind: 'error'; message: string };

@Component({
  selector: 'app-import-page',
  imports: [
    AsyncPipe,
    DatePipe,
    RouterLink,
    MatButtonModule,
    MatCardModule,
    MatExpansionModule,
    MatIconModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './import-page.html',
  styleUrl: './import-page.scss',
})
export class ImportPage {
  private readonly importService = inject(ImportService);
  private readonly files$ = new Subject<File>();

  readonly issueColumns = ['row', 'external_code', 'message'];
  readonly accept = ALLOWED_EXTENSIONS.join(',');

  readonly statusLabels: Record<ImportStatus, string> = {
    pending: 'В очереди',
    processing: 'Обрабатывается',
    completed: 'Завершён',
    failed: 'Ошибка',
  };

  /**
   * Состояние страницы целиком описано потоком: выбор файла → загрузка → polling статуса.
   * Подписка — через async pipe, при уходе со страницы polling останавливается сам.
   */
  readonly state$: Observable<ImportViewState> = this.files$.pipe(
    switchMap((file) => {
      const validationError = this.validate(file);
      if (validationError) {
        return of<ImportViewState>({ kind: 'error', message: validationError });
      }

      return this.importService.upload(file).pipe(
        switchMap((job) => this.importService.pollStatus(job.id)),
        map((job): ImportViewState => ({ kind: 'progress', job })),
        startWith<ImportViewState>({ kind: 'uploading', fileName: file.name }),
        catchError((error: unknown) => of<ImportViewState>({ kind: 'error', message: extractErrorMessage(error) })),
      );
    }),
    startWith<ImportViewState>({ kind: 'idle' }),
  );

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.item(0);
    if (file) {
      this.files$.next(file);
    }
    // сбрасываем, чтобы повторный выбор того же файла снова запускал импорт
    input.value = '';
  }

  private validate(file: File): string | null {
    const name = file.name.toLowerCase();
    if (!ALLOWED_EXTENSIONS.some((extension) => name.endsWith(extension))) {
      return `Допустимы только файлы ${ALLOWED_EXTENSIONS.join(', ')}`;
    }
    if (file.size === 0) {
      return 'Файл пустой';
    }
    if (file.size > MAX_FILE_SIZE) {
      return 'Файл больше 10 МБ';
    }
    return null;
  }
}
