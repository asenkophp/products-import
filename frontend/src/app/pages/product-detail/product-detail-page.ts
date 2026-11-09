import { AsyncPipe, CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap, tap } from 'rxjs';
import { extractErrorMessage } from '../../core/http-error';
import { Product, ProductImage } from '../../models';
import { ProductService } from '../../services/product.service';

type DetailState =
  | { kind: 'loading' }
  | { kind: 'loaded'; product: Product }
  | { kind: 'error'; message: string };

@Component({
  selector: 'app-product-detail-page',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DatePipe,
    DecimalPipe,
    RouterLink,
    MatButtonModule,
    MatCardModule,
    MatIconModule,
    MatProgressBarModule,
    MatTableModule,
    MatTooltipModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './product-detail-page.html',
  styleUrl: './product-detail-page.scss',
})
export class ProductDetailPage {
  private readonly route = inject(ActivatedRoute);
  private readonly productService = inject(ProductService);

  readonly attributeColumns = ['key', 'value'];
  readonly selectedImage = signal(0);

  readonly state$: Observable<DetailState> = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    tap(() => this.selectedImage.set(0)),
    switchMap((id) =>
      this.productService.getProduct(id).pipe(
        map((product): DetailState => ({ kind: 'loaded', product })),
        catchError((error: unknown) =>
          of<DetailState>({
            kind: 'error',
            message:
              error instanceof HttpErrorResponse && error.status === 404
                ? 'Товар не найден'
                : extractErrorMessage(error),
          }),
        ),
        startWith<DetailState>({ kind: 'loading' }),
      ),
    ),
  );

  /** Скачанные картинки — для галереи; нескачанные показываем ссылками. */
  downloaded(images: ProductImage[]): ProductImage[] {
    return images.filter((image) => image.path !== null);
  }

  missing(images: ProductImage[]): ProductImage[] {
    return images.filter((image) => image.path === null);
  }
}
