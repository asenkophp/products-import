import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { provideEffects } from '@ngrx/effects';
import { provideState } from '@ngrx/store';
import { productsEffects, productsFeatureKey, productsReducer } from './store/products';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  {
    path: 'login',
    title: 'Вход',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login-page').then((m) => m.LoginPage),
  },
  {
    path: 'import',
    title: 'Импорт товаров',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/import/import-page').then((m) => m.ImportPage),
  },
  {
    path: 'products',
    canActivate: [authGuard],
    // NgRx-состояние товаров подключается только при переходе в раздел
    providers: [provideState(productsFeatureKey, productsReducer), provideEffects(productsEffects)],
    children: [
      {
        path: '',
        title: 'Товары',
        loadComponent: () => import('./pages/product-list/product-list-page').then((m) => m.ProductListPage),
      },
      {
        path: ':id',
        title: 'Карточка товара',
        loadComponent: () =>
          import('./pages/product-detail/product-detail-page').then((m) => m.ProductDetailPage),
      },
    ],
  },
  { path: '**', redirectTo: 'products' },
];
