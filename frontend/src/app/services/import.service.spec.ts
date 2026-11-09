import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ImportJob, ImportStatus } from '../models';
import { ImportService } from './import.service';

function job(status: ImportStatus, progress: number): ImportJob {
  return {
    id: 7,
    status,
    progress,
    filename: 'file.xlsx',
    totalRows: 10,
    processedRows: progress / 10,
    createdCount: 0,
    updatedCount: 0,
    failedCount: 0,
    errors: [],
    warnings: [],
    createdAt: '',
    startedAt: null,
    finishedAt: null,
  };
}

describe('ImportService', () => {
  let service: ImportService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ImportService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    vi.useRealTimers();
    http.verify();
  });

  it('отправляет файл как multipart-поле "file"', () => {
    const file = new File(['data'], 'products.xlsx');
    service.upload(file).subscribe();

    const request = http.expectOne('/api/import');
    expect(request.request.method).toBe('POST');
    expect((request.request.body as FormData).get('file')).toBeInstanceOf(File);
    request.flush(job('pending', 0));
  });

  it('опрашивает статус, пока задача не завершится', () => {
    vi.useFakeTimers();
    const received: ImportStatus[] = [];
    let completed = false;

    service.pollStatus(7, 1000).subscribe({
      next: (j) => received.push(j.status),
      complete: () => (completed = true),
    });

    vi.advanceTimersByTime(0);
    http.expectOne('/api/import/7').flush(job('processing', 50));
    vi.advanceTimersByTime(1000);
    http.expectOne('/api/import/7').flush(job('completed', 100));
    vi.advanceTimersByTime(5000);

    expect(received).toEqual(['processing', 'completed']);
    expect(completed).toBe(true);
    http.expectNone('/api/import/7');
  });
});
