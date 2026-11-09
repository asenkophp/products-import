import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { DEFAULT_PRODUCT_QUERY } from '../models';
import { ProductService } from './product.service';

describe('ProductService', () => {
  let service: ProductService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ProductService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('передаёт пагинацию и только заданные фильтры', () => {
    service.getProducts({ ...DEFAULT_PRODUCT_QUERY, page: 2, name: 'бермуды', priceMin: 100 }).subscribe();

    const request = http.expectOne((r) => r.url === '/api/products');
    expect(request.request.params.get('page')).toBe('2');
    expect(request.request.params.get('limit')).toBe('20');
    expect(request.request.params.get('name')).toBe('бермуды');
    expect(request.request.params.get('price_min')).toBe('100');
    expect(request.request.params.has('price_max')).toBe(false);
    request.flush({ items: [], meta: { page: 2, limit: 20, total: 0, pages: 0 } });
  });

  it('загружает карточку товара', () => {
    let name = '';
    service.getProduct(5).subscribe((product) => (name = product.name));

    http.expectOne('/api/products/5').flush({ id: 5, name: 'Бермуды' });
    expect(name).toBe('Бермуды');
  });
});
