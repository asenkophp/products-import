/** Формат ошибки API: { "error": { "message": "...", "fields": { ... } } } */
export interface ApiError {
  error: {
    message: string;
    fields?: Record<string, string>;
  };
}
