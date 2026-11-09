import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { MockStore, provideMockStore } from '@ngrx/store/testing';
import { DEFAULT_PRODUCT_QUERY } from '../../models';
import { initialProductsState, loadProducts } from '../../store/products';
import { ProductListPage } from './product-list-page';

describe('ProductListPage', () => {
  let store: MockStore;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProductListPage],
      providers: [
        provideRouter([]),
        provideMockStore({
          initialState: {
            products: {
              ...initialProductsState,
              items: [
                { id: 1, externalCode: 'A-1', name: 'Бермуды мужские', price: 1320, discount: 33.33, image: null },
                { id: 2, externalCode: 'A-2', name: 'Леггинсы', price: 900, discount: null, image: '/uploads/x.jpg' },
              ],
              total: 2,
              totalPages: 1,
            },
          },
        }),
      ],
    }).compileComponents();

    store = TestBed.inject(MockStore);
  });

  it('при открытии запрашивает товары через store', () => {
    const dispatch = vi.spyOn(store, 'dispatch');
    TestBed.createComponent(ProductListPage).detectChanges();

    expect(dispatch).toHaveBeenCalledWith(loadProducts({ query: DEFAULT_PRODUCT_QUERY }));
  });

  it('показывает товары из store ссылками на карточки', async () => {
    const fixture = TestBed.createComponent(ProductListPage);
    fixture.detectChanges();
    await fixture.whenStable();

    const links = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<HTMLAnchorElement>('a.products__link'));
    expect(links.map((a) => a.textContent?.trim())).toEqual(['Бермуды мужские', 'Леггинсы']);
    expect(links[0].getAttribute('href')).toBe('/products/1');
  });
});
