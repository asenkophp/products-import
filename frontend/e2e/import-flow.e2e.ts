import { expect, test } from '@playwright/test';
import path from 'node:path';
import { login } from './helpers';

const FIXTURE = path.resolve(__dirname, '../../backend/tests/fixtures/import_example.ods');

test.describe('Импорт товаров: полный сценарий', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('загрузка файла → прогресс → список → карточка', async ({ page }) => {
    await page.goto('/import');
    await expect(page.getByRole('heading', { name: 'Импорт товаров' })).toBeVisible();

    // загрузка файла через скрытый input
    await page.locator('input[type=file]').setInputFiles(FIXTURE);

    // прогресс: polling статуса до завершения задачи в воркере
    await expect(page.getByText('Задача #')).toBeVisible();
    await expect(page.getByText('Завершён')).toBeVisible({ timeout: 150_000 });
    await expect(page.getByText('40 из 40 строк')).toBeVisible();
    await expect(page.getByText('Отчёт об ошибках')).toHaveCount(0);

    // переход к списку
    await page.getByRole('link', { name: 'Перейти к товарам' }).click();
    await expect(page).toHaveURL(/\/products$/);
    await expect(page.locator('tr.products__row').first()).toBeVisible();

    // поиск с серверной фильтрацией
    await page.getByLabel('Поиск по названию').fill('Бермуды мужские');
    await expect(page.locator('tr.products__row')).toHaveCount(2);

    // карточка товара
    await page.locator('tr.products__row').first().click();
    await expect(page).toHaveURL(/\/products\/\d+$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Бермуды мужские');
    await expect(page.getByText('Характеристики (15)')).toBeVisible();
    await expect(page.getByText('Внешний код:')).toBeVisible();
  });

  test('неподходящий файл отклоняется до отправки на сервер', async ({ page }) => {
    await page.goto('/import');

    await page.locator('input[type=file]').setInputFiles({
      name: 'products.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('not a spreadsheet'),
    });

    await expect(page.getByRole('alert')).toContainText('Допустимы только файлы');
  });

  test('несуществующий товар', async ({ page }) => {
    await page.goto('/products/999999');
    await expect(page.getByRole('alert')).toContainText('Товар не найден');
  });
});
