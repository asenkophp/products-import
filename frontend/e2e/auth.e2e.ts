import { expect, test } from '@playwright/test';
import { E2E_USERNAME, login } from './helpers';

test.describe('Аутентификация', () => {
  test('без входа защищённые страницы недоступны', async ({ page }) => {
    await page.goto('/import');

    await expect(page).toHaveURL(/\/login\?returnUrl=%2Fimport$/);
    await expect(page.getByText('Вход', { exact: true })).toBeVisible();
  });

  test('неверный пароль', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Логин').fill(E2E_USERNAME);
    await page.getByLabel('Пароль', { exact: true }).fill('definitely-wrong');
    await page.getByRole('button', { name: 'Войти' }).click();

    await expect(page.getByRole('alert')).toContainText('Неверный логин или пароль');
    await expect(page).toHaveURL(/\/login/);
  });

  test('вход, возврат на запрошенную страницу и выход', async ({ page }) => {
    await login(page, '/import');
    await expect(page.getByRole('heading', { name: 'Импорт товаров' })).toBeVisible();
    await expect(page.locator('.toolbar__username')).toHaveText(E2E_USERNAME);

    await page.getByRole('button', { name: 'Выйти' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto('/products');
    await expect(page).toHaveURL(/\/login/);
  });
});
