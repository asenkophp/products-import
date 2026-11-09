import { AsyncPipe, CurrencyPipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit, inject } from '@angular/core';
import {
  AbstractControl,
  FormControl,
  FormGroup,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { ProductQuery } from '../../models';
import {
  changeProductsFilter,
  loadProducts,
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsQuery,
  selectProductsTotal,
  selectTotalPages,
} from '../../store/products';

function priceRangeValidator(group: AbstractControl): ValidationErrors | null {
  const min = group.get('priceMin')?.value as number | null;
  const max = group.get('priceMax')?.value as number | null;

  return min !== null && max !== null && min > max ? { priceRange: true } : null;
}

@Component({
  selector: 'app-product-list-page',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DecimalPipe,
    ReactiveFormsModule,
    RouterLink,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatPaginatorModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './product-list-page.html',
  styleUrl: './product-list-page.scss',
})
export class ProductListPage implements OnInit {
  private readonly store = inject(Store);

  // данные — только через store.select() + async pipe
  readonly products$ = this.store.select(selectAllProducts);
  readonly loading$ = this.store.select(selectProductsLoading);
  readonly totalPages$ = this.store.select(selectTotalPages);
  readonly total$ = this.store.select(selectProductsTotal);
  readonly error$ = this.store.select(selectProductsError);

  /** Текущие параметры запроса (сигнал для чтения в обработчиках и шаблоне без подписок). */
  readonly query = this.store.selectSignal(selectProductsQuery);

  readonly columns = ['image', 'name', 'externalCode', 'price', 'discount'];
  readonly pageSizeOptions = [10, 20, 50, 100];

  readonly filterForm = new FormGroup(
    {
      name: new FormControl('', { nonNullable: true }),
      priceMin: new FormControl<number | null>(null, Validators.min(0)),
      priceMax: new FormControl<number | null>(null, Validators.min(0)),
    },
    { validators: priceRangeValidator },
  );

  ngOnInit(): void {
    const query = this.query();
    this.filterForm.setValue(
      { name: query.name ?? '', priceMin: query.priceMin, priceMax: query.priceMax },
      { emitEvent: false },
    );
    this.store.dispatch(loadProducts({ query }));
  }

  /** Вызывается при вводе в фильтры; сама загрузка — в эффекте с debounce. */
  applyFilters(): void {
    if (this.filterForm.invalid) {
      return;
    }
    const { name, priceMin, priceMax } = this.filterForm.getRawValue();
    const query: ProductQuery = {
      ...this.query(),
      page: 1,
      name: name.trim() || null,
      priceMin: priceMin ?? null,
      priceMax: priceMax ?? null,
    };
    this.store.dispatch(changeProductsFilter({ query }));
  }

  resetFilters(): void {
    this.filterForm.reset({ name: '', priceMin: null, priceMax: null });
    this.store.dispatch(
      loadProducts({ query: { ...this.query(), page: 1, name: null, priceMin: null, priceMax: null } }),
    );
  }

  hasFilters(): boolean {
    const { name, priceMin, priceMax } = this.filterForm.getRawValue();
    return name !== '' || priceMin !== null || priceMax !== null;
  }

  onPage(event: PageEvent): void {
    this.store.dispatch(
      loadProducts({ query: { ...this.query(), page: event.pageIndex + 1, limit: event.pageSize } }),
    );
  }
}
