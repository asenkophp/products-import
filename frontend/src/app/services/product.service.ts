import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../core/api-url';
import { Product, ProductPage, ProductQuery } from '../models';

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = inject(API_URL);

  /** Страница товаров: серверная пагинация и фильтры. */
  getProducts(query: ProductQuery): Observable<ProductPage> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);

    if (query.name) {
      params = params.set('name', query.name);
    }
    if (query.priceMin !== null) {
      params = params.set('price_min', query.priceMin);
    }
    if (query.priceMax !== null) {
      params = params.set('price_max', query.priceMax);
    }

    return this.http.get<ProductPage>(`${this.apiUrl}/products`, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.apiUrl}/products/${id}`);
  }
}
