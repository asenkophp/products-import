export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

/** Ошибка или предупреждение по конкретной строке файла. */
export interface ImportIssue {
  row: number;
  external_code: string | null;
  message: string;
}

export interface ImportJob {
  id: number;
  status: ImportStatus;
  /** Прогресс 0–100. */
  progress: number;
  filename: string;
  totalRows: number;
  processedRows: number;
  createdCount: number;
  updatedCount: number;
  failedCount: number;
  errors: ImportIssue[];
  warnings: ImportIssue[];
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export function isImportFinished(job: ImportJob): boolean {
  return job.status === 'completed' || job.status === 'failed';
}
