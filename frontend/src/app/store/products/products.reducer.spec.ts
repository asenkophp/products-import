import { DEFAULT_PRODUCT_QUERY, ProductPage } from '../../models';
import { changeProductsFilter, loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { initialProductsState, productsReducer } from './products.reducer';

const page: ProductPage = {
  items: [{ id: 1, externalCode: 'A-1', name: 'Бермуды', price: 1320, discount: 33.33, image: null }],
  meta: { page: 2, limit: 20, total: 41, pages: 3 },
};

describe('productsReducer', () => {
  it('loadProducts запоминает запрос и включает загрузку', () => {
    const query = { ...DEFAULT_PRODUCT_QUERY, page: 2 };
    const state = productsReducer({ ...initialProductsState, error: 'старая ошибка' }, loadProducts({ query }));

    expect(state.loading).toBe(true);
    expect(state.query).toEqual(query);
    expect(state.error).toBeNull();
  });

  it('loadProductsSuccess сохраняет товары и пагинацию', () => {
    const state = productsReducer({ ...initialProductsState, loading: true }, loadProductsSuccess({ page }));

    expect(state.items).toEqual(page.items);
    expect(state.total).toBe(41);
    expect(state.totalPages).toBe(3);
    expect(state.loading).toBe(false);
  });

  it('loadProductsFailure сохраняет ошибку и очищает список', () => {
    const loaded = productsReducer(initialProductsState, loadProductsSuccess({ page }));
    const state = productsReducer(loaded, loadProductsFailure({ error: 'Сервер недоступен' }));

    expect(state.error).toBe('Сервер недоступен');
    expect(state.items).toEqual([]);
    expect(state.loading).toBe(false);
  });

  it('changeProductsFilter сразу обновляет запрос, не запуская загрузку', () => {
    const query = { ...DEFAULT_PRODUCT_QUERY, name: 'бермуды' };
    const state = productsReducer(initialProductsState, changeProductsFilter({ query }));

    expect(state.query.name).toBe('бермуды');
    expect(state.loading).toBe(false);
  });
});
