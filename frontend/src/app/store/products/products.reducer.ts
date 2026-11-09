import { createReducer, on } from '@ngrx/store';
import { DEFAULT_PRODUCT_QUERY, ProductListItem, ProductQuery } from '../../models';
import {
  changeProductsFilter,
  loadProducts,
  loadProductsFailure,
  loadProductsSuccess,
} from './products.actions';

export const productsFeatureKey = 'products';

export interface ProductsState {
  items: ProductListItem[];
  query: ProductQuery;
  total: number;
  totalPages: number;
  loading: boolean;
  error: string | null;
}

export const initialProductsState: ProductsState = {
  items: [],
  query: DEFAULT_PRODUCT_QUERY,
  total: 0,
  totalPages: 0,
  loading: false,
  error: null,
};

export const productsReducer = createReducer(
  initialProductsState,
  on(changeProductsFilter, (state, { query }): ProductsState => ({ ...state, query })),
  on(loadProducts, (state, { query }): ProductsState => ({ ...state, query, loading: true, error: null })),
  on(
    loadProductsSuccess,
    (state, { page }): ProductsState => ({
      ...state,
      items: page.items,
      total: page.meta.total,
      totalPages: page.meta.pages,
      loading: false,
    }),
  ),
  on(
    loadProductsFailure,
    (state, { error }): ProductsState => ({ ...state, items: [], total: 0, totalPages: 0, loading: false, error }),
  ),
);
