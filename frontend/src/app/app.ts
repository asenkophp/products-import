import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatTooltipModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <mat-toolbar class="toolbar">
      <a routerLink="/products" class="toolbar__brand">
        <mat-icon>inventory_2</mat-icon>
        <span>Каталог товаров</span>
      </a>
      <span class="toolbar__spacer"></span>

      @if (auth.user(); as user) {
        <nav class="toolbar__nav">
          <a mat-button routerLink="/products" routerLinkActive="active">
            <mat-icon>list</mat-icon>
            Товары
          </a>
          <a mat-button routerLink="/import" routerLinkActive="active">
            <mat-icon>upload_file</mat-icon>
            Импорт
          </a>
        </nav>
        <span class="toolbar__user">
          <mat-icon>account_circle</mat-icon>
          <span class="toolbar__username">{{ user.username }}</span>
        </span>
        <button mat-icon-button type="button" matTooltip="Выйти" aria-label="Выйти" (click)="logout()">
          <mat-icon>logout</mat-icon>
        </button>
      }
    </mat-toolbar>

    <main>
      <router-outlet />
    </main>
  `,
  styleUrl: './app.scss',
})
export class App {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}
