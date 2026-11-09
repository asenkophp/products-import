import { HttpErrorResponse } from '@angular/common/http';
import { Observable, of, throwError } from 'rxjs';
import { TestScheduler } from 'rxjs/testing';
import { DEFAULT_PRODUCT_QUERY, ProductPage, ProductQuery } from '../../models';
import { ProductService } from '../../services/product.service';
import { changeProductsFilter, loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { filterChanged$, loadProducts$ } from './products.effects';

const page: ProductPage = { items: [], meta: { page: 1, limit: 20, total: 0, pages: 0 } };

function serviceReturning(result: Observable<ProductPage>): { service: ProductService; calls: ProductQuery[] } {
  const calls: ProductQuery[] = [];
  const service = {
    getProducts: (query: ProductQuery) => {
      calls.push(query);
      return result;
    },
  } as unknown as ProductService;
  return { service, calls };
}

describe('products effects', () => {
  let scheduler: TestScheduler;

  beforeEach(() => {
    scheduler = new TestScheduler((actual, expected) => expect(actual).toEqual(expected));
  });

  it('loadProducts$ запрашивает страницу через ProductService', () => {
    const { service, calls } = serviceReturning(of(page));

    scheduler.run(({ cold, expectObservable }) => {
      const actions$ = cold('a', { a: loadProducts({ query: DEFAULT_PRODUCT_QUERY }) });
      expectObservable(loadProducts$(actions$, service)).toBe('s', { s: loadProductsSuccess({ page }) });
    });

    expect(calls).toEqual([DEFAULT_PRODUCT_QUERY]);
  });

  it('loadProducts$ превращает ошибку HTTP в loadProductsFailure', () => {
    const error = new HttpErrorResponse({ status: 0 });
    const { service } = serviceReturning(throwError(() => error));

    scheduler.run(({ cold, expectObservable }) => {
      const actions$ = cold('a', { a: loadProducts({ query: DEFAULT_PRODUCT_QUERY }) });
      expectObservable(loadProducts$(actions$, service)).toBe('f', {
        f: loadProductsFailure({ error: 'Сервер недоступен. Проверьте, что бэкенд запущен.' }),
      });
    });
  });

  it('filterChanged$ ждёт паузу в вводе и загружает только последний запрос', () => {
    const q1 = { ...DEFAULT_PRODUCT_QUERY, name: 'б' };
    const q2 = { ...DEFAULT_PRODUCT_QUERY, name: 'бермуды' };

    scheduler.run(({ cold, expectObservable }) => {
      const actions$ = cold('a 100ms b', {
        a: changeProductsFilter({ query: q1 }),
        b: changeProductsFilter({ query: q2 }),
      });
      // b приходит на 101-м кадре, загрузка — через 300 мс тишины после него
      expectObservable(filterChanged$(actions$)).toBe('401ms l', { l: loadProducts({ query: q2 }) });
    });
  });
});
