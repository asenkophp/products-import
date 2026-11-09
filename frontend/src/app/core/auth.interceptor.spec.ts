import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let backend: HttpTestingController;

  beforeEach(() => {
    localStorage.setItem(
      'products-import.session',
      JSON.stringify({ token: 'jwt-token', user: { id: 1, username: 'admin' }, expiresAt: Date.now() + 60_000 }),
    );
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    localStorage.clear();
    backend.verify();
  });

  it('добавляет Bearer-токен к запросам API', () => {
    http.get('/api/products').subscribe();

    expect(backend.expectOne('/api/products').request.headers.get('Authorization')).toBe('Bearer jwt-token');
  });

  it('не отправляет токен на чужие адреса', () => {
    http.get('https://example.com/data').subscribe();

    expect(backend.expectOne('https://example.com/data').request.headers.has('Authorization')).toBe(false);
  });

  it('на 401 завершает сессию и ведёт на страницу входа', () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    http.get('/api/products').subscribe({ error: () => undefined });

    backend.expectOne('/api/products').flush({ error: { message: 'Срок действия токена истёк' } }, { status: 401, statusText: 'Unauthorized' });

    expect(TestBed.inject(AuthService).isAuthenticated()).toBe(false);
    expect(navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/' } });
  });
});
