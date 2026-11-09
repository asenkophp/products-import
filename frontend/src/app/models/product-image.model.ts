/** Изображение товара: исходная ссылка и локальный путь (null — не удалось скачать). */
export interface ProductImage {
  id: number;
  url: string;
  path: string | null;
  position: number;
}
