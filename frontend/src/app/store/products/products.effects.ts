import { inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, debounceTime, map, of, switchMap } from 'rxjs';
import { extractErrorMessage } from '../../core/http-error';
import { ProductService } from '../../services/product.service';
import {
  changeProductsFilter,
  loadProducts,
  loadProductsFailure,
  loadProductsSuccess,
} from './products.actions';

/** Загрузка страницы товаров через ProductService. switchMap отменяет устаревшие запросы. */
export const loadProducts$ = createEffect(
  (actions$ = inject(Actions), productService = inject(ProductService)) =>
    actions$.pipe(
      ofType(loadProducts),
      switchMap(({ query }) =>
        productService.getProducts(query).pipe(
          map((page) => loadProductsSuccess({ page })),
          catchError((error: unknown) => of(loadProductsFailure({ error: extractErrorMessage(error) }))),
        ),
      ),
    ),
  { functional: true },
);

/** Изменение фильтров → загрузка после паузы в 300 мс, чтобы не слать запрос на каждый символ. */
export const filterChanged$ = createEffect(
  (actions$ = inject(Actions)) =>
    actions$.pipe(
      ofType(changeProductsFilter),
      debounceTime(300),
      map(({ query }) => loadProducts({ query })),
    ),
  { functional: true },
);
