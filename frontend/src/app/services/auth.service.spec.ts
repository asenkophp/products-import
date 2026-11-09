import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { AuthService } from './auth.service';

const STORAGE_KEY = 'products-import.session';

describe('AuthService', () => {
  let http: HttpTestingController;

  function create(): AuthService {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    return TestBed.inject(AuthService);
  }

  afterEach(() => {
    localStorage.clear();
    http.verify();
  });

  it('вход сохраняет токен и пользователя', () => {
    const auth = create();
    auth.login('admin', 'secret').subscribe();

    const request = http.expectOne('/api/auth/login');
    expect(request.request.body).toEqual({ username: 'admin', password: 'secret' });
    request.flush({
      token: 'jwt-token',
      expiresAt: new Date(Date.now() + 3_600_000).toISOString(),
      user: { id: 1, username: 'admin' },
    });

    expect(auth.isAuthenticated()).toBe(true);
    expect(auth.token()).toBe('jwt-token');
    expect(auth.user()?.username).toBe('admin');
    expect(localStorage.getItem(STORAGE_KEY)).toContain('jwt-token');
  });

  it('выход очищает сессию', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: 't', user: { id: 1, username: 'admin' }, expiresAt: Date.now() + 60_000 }),
    );
    const auth = create();
    expect(auth.isAuthenticated()).toBe(true);

    auth.logout();

    expect(auth.isAuthenticated()).toBe(false);
    expect(auth.token()).toBeNull();
    expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
  });

  it('истёкшая сессия из localStorage не восстанавливается', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: 't', user: { id: 1, username: 'admin' }, expiresAt: Date.now() - 1 }),
    );

    expect(create().isAuthenticated()).toBe(false);
  });
});
