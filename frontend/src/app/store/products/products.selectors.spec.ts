import { initialProductsState, ProductsState } from './products.reducer';
import {
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsQuery,
  selectTotalPages,
} from './products.selectors';

describe('products selectors', () => {
  const state: ProductsState = {
    ...initialProductsState,
    items: [{ id: 1, externalCode: 'A-1', name: 'Бермуды', price: 100, discount: null, image: null }],
    totalPages: 4,
    loading: true,
    error: 'ошибка',
  };
  const root = { products: state };

  it('выбирают части состояния', () => {
    expect(selectAllProducts(root)).toBe(state.items);
    expect(selectProductsLoading(root)).toBe(true);
    expect(selectTotalPages(root)).toBe(4);
    expect(selectProductsError(root)).toBe('ошибка');
    expect(selectProductsQuery(root)).toBe(state.query);
  });
});
