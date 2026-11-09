import { InjectionToken } from '@angular/core';

/** Базовый адрес API. В dev запросы проксируются через proxy.conf.json, в Docker — через nginx. */
export const API_URL = new InjectionToken<string>('API_URL', {
  providedIn: 'root',
  factory: () => '/api',
});
