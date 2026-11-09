import { ProductAttribute } from './product-attribute.model';
import { ProductImage } from './product-image.model';

/** Полная карточка товара. */
export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  price: number;
  /** Скидка в процентах. */
  discount: number | null;
  attributes: ProductAttribute[];
  images: ProductImage[];
  createdAt: string;
  updatedAt: string;
}

/** Товар в списке (без атрибутов, с главной картинкой). */
export interface ProductListItem {
  id: number;
  externalCode: string;
  name: string;
  price: number;
  discount: number | null;
  image: string | null;
}

export interface PageMeta {
  page: number;
  limit: number;
  total: number;
  pages: number;
}

export interface ProductPage {
  items: ProductListItem[];
  meta: PageMeta;
}

/** Параметры запроса списка: пагинация + фильтры. */
export interface ProductQuery {
  page: number;
  limit: number;
  name: string | null;
  priceMin: number | null;
  priceMax: number | null;
}

export const DEFAULT_PRODUCT_QUERY: ProductQuery = {
  page: 1,
  limit: 20,
  name: null,
  priceMin: null,
  priceMax: null,
};
