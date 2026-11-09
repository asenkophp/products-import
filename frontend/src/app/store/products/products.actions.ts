import { createAction, props } from '@ngrx/store';
import { ProductPage, ProductQuery } from '../../models';

/** Загрузить страницу товаров с указанными параметрами. */
export const loadProducts = createAction('[Products] Load Products', props<{ query: ProductQuery }>());

export const loadProductsSuccess = createAction(
  '[Products API] Load Products Success',
  props<{ page: ProductPage }>(),
);

export const loadProductsFailure = createAction(
  '[Products API] Load Products Failure',
  props<{ error: string }>(),
);

/** Пользователь меняет фильтры; загрузка запускается с задержкой (debounce) в эффекте. */
export const changeProductsFilter = createAction(
  '[Products Page] Change Filter',
  props<{ query: ProductQuery }>(),
);
