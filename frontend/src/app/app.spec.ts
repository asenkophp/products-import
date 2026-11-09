import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';

function render(): HTMLElement {
  TestBed.configureTestingModule({
    imports: [App],
    providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
  });
  const fixture = TestBed.createComponent(App);
  fixture.detectChanges();
  return fixture.nativeElement as HTMLElement;
}

describe('App', () => {
  afterEach(() => localStorage.clear());

  it('без входа показывает только название', () => {
    const element = render();

    expect(element.querySelector('.toolbar__brand')?.textContent).toContain('Каталог товаров');
    expect(element.querySelectorAll('.toolbar__nav a').length).toBe(0);
  });

  it('после входа показывает навигацию и пользователя', () => {
    localStorage.setItem(
      'products-import.session',
      JSON.stringify({ token: 't', user: { id: 1, username: 'admin' }, expiresAt: Date.now() + 60_000 }),
    );
    const element = render();

    expect(element.querySelectorAll('.toolbar__nav a').length).toBe(2);
    expect(element.querySelector('.toolbar__username')?.textContent).toContain('admin');
  });
});
