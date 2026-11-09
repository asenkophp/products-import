import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState, productsFeatureKey } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>(productsFeatureKey);

export const selectAllProducts = createSelector(selectProductsState, (state) => state.items);
export const selectProductsLoading = createSelector(selectProductsState, (state) => state.loading);
export const selectTotalPages = createSelector(selectProductsState, (state) => state.totalPages);
export const selectProductsTotal = createSelector(selectProductsState, (state) => state.total);
export const selectProductsQuery = createSelector(selectProductsState, (state) => state.query);
export const selectProductsError = createSelector(selectProductsState, (state) => state.error);
