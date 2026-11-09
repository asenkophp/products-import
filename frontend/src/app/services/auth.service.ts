import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { API_URL } from '../core/api-url';
import { AuthUser, LoginResponse } from '../models';

interface Session {
  token: string;
  user: AuthUser;
  /** Окончание действия токена, мс. */
  expiresAt: number;
}

const STORAGE_KEY = 'products-import.session';

/** Вход по JWT. Сессия хранится в localStorage и переживает перезагрузку страницы. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = inject(API_URL);
  private readonly session = signal<Session | null>(AuthService.restore());

  readonly user = computed(() => this.session()?.user ?? null);
  readonly isAuthenticated = computed(() => this.activeSession() !== null);

  login(username: string, password: string): Observable<AuthUser> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/auth/login`, { username, password }).pipe(
      tap((response) =>
        this.save({ token: response.token, user: response.user, expiresAt: Date.parse(response.expiresAt) }),
      ),
      map((response) => response.user),
    );
  }

  logout(): void {
    localStorage.removeItem(STORAGE_KEY);
    this.session.set(null);
  }

  /** Действующий токен или null, если сессии нет или она истекла. */
  token(): string | null {
    return this.activeSession()?.token ?? null;
  }

  private activeSession(): Session | null {
    const session = this.session();
    return session !== null && session.expiresAt > Date.now() ? session : null;
  }

  private save(session: Session): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    this.session.set(session);
  }

  private static restore(): Session | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const session = raw ? (JSON.parse(raw) as Session) : null;
      return session && typeof session.token === 'string' && session.expiresAt > Date.now() ? session : null;
    } catch {
      return null;
    }
  }
}
