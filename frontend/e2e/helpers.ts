import { Page, expect } from '@playwright/test';

export const E2E_USERNAME = process.env['E2E_USERNAME'] ?? 'admin';
export const E2E_PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin12345';

/** Вход через форму; после него приложение возвращает на returnUrl или в каталог. */
export async function login(page: Page, returnUrl = '/products'): Promise<void> {
  await page.goto(`/login?returnUrl=${encodeURIComponent(returnUrl)}`);
  await page.getByLabel('Логин').fill(E2E_USERNAME);
  await page.getByLabel('Пароль', { exact: true }).fill(E2E_PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(new RegExp(`${returnUrl.replace(/[/]/g, '\\/')}$`));
}
