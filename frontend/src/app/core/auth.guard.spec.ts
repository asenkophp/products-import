import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { authGuard, guestGuard } from './auth.guard';

const route = {} as ActivatedRouteSnapshot;
const state = { url: '/import' } as RouterStateSnapshot;

describe('auth guards', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideHttpClient()] });
  });

  afterEach(() => localStorage.clear());

  function signIn(): void {
    localStorage.setItem(
      'products-import.session',
      JSON.stringify({ token: 't', user: { id: 1, username: 'admin' }, expiresAt: Date.now() + 60_000 }),
    );
  }

  it('authGuard без входа ведёт на /login с returnUrl', () => {
    const result = TestBed.runInInjectionContext(() => authGuard(route, state));

    expect(result).toBeInstanceOf(UrlTree);
    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/login?returnUrl=%2Fimport');
  });

  it('authGuard пропускает вошедшего пользователя', () => {
    signIn();
    expect(TestBed.runInInjectionContext(() => authGuard(route, state))).toBe(true);
  });

  it('guestGuard уводит вошедшего пользователя со страницы входа', () => {
    signIn();
    const result = TestBed.runInInjectionContext(() => guestGuard(route, state));

    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/products');
  });
});
