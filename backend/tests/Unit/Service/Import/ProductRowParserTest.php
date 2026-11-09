<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\InvalidImportFileException;
use App\Service\Import\ProductRowParser;
use App\Service\Import\RowValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductRowParserTest extends TestCase
{
    private const HEADERS = [
        'Внешний код',
        'Наименование',
        'Описание',
        'Цена: Цена продажи',
        'Закупочная цена',
        'Доп. поле: Цвет',
        'Доп. поле: Кол-во в упаковке',
        'Доп. поле: Ссылка на упаковку',
        'Доп. поле: Ссылки на фото',
        'Штрихкод EAN13',
    ];

    private ProductRowParser $parser;

    protected function setUp(): void
    {
        $this->parser = ProductRowParser::fromHeaders(self::HEADERS);
    }

    /** @param array<string, mixed> $values */
    private function row(array $values): array
    {
        $defaults = [
            'Внешний код'                   => 'ABC-1',
            'Наименование'                  => 'Бермуды мужские',
            'Описание'                      => 'Описание',
            'Цена: Цена продажи'            => '1320,00',
            'Закупочная цена'               => '880,00',
            'Доп. поле: Цвет'               => 'Grigio',
            'Доп. поле: Кол-во в упаковке'  => 20.0,
            'Доп. поле: Ссылка на упаковку' => 'http://example.com/pack.jpg',
            'Доп. поле: Ссылки на фото'     => 'http://example.com/1.jpg, http://example.com/2.jpg',
            'Штрихкод EAN13'                => '8000000784582',
        ];
        $values += $defaults;

        return array_map(static fn(string $header): mixed => $values[$header], self::HEADERS);
    }

    public function testParsesValidRow(): void
    {
        $data = $this->parser->parse($this->row([]));

        self::assertSame('ABC-1', $data->externalCode);
        self::assertSame('Бермуды мужские', $data->name);
        self::assertSame('1320.00', $data->price);
        self::assertSame('33.33', $data->discount);
        // «Доп. поле:» → атрибуты, колонки с картинками в атрибуты не попадают, 20.0 → "20"
        self::assertSame(['Цвет' => 'Grigio', 'Кол-во в упаковке' => '20'], $data->attributes);
        // сначала фото товара, потом упаковка
        self::assertSame(
            ['http://example.com/1.jpg', 'http://example.com/2.jpg', 'http://example.com/pack.jpg'],
            $data->imageUrls,
        );
        self::assertSame([], $data->warnings);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function prices(): iterable
    {
        yield 'запятая' => ['1320,50', '1320.50'];
        yield 'точка' => ['1320.5', '1320.50'];
        yield 'пробелы-разделители' => ['1 320,00', '1320.00'];
        yield 'число из ячейки' => [990, '990.00'];
        yield 'float из ячейки' => [99.999, '100.00'];
    }

    #[DataProvider('prices')]
    public function testNormalizesPrice(mixed $raw, string $expected): void
    {
        self::assertSame($expected, $this->parser->parse($this->row(['Цена: Цена продажи' => $raw]))->price);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRows(): iterable
    {
        yield 'нет внешнего кода' => [['Внешний код' => null], 'Не заполнен внешний код'];
        yield 'нет названия' => [['Наименование' => '  '], 'Не заполнено наименование'];
        yield 'нет цены' => [['Цена: Цена продажи' => ''], 'Не заполнена цена продажи'];
        yield 'цена текстом' => [['Цена: Цена продажи' => 'abc'], 'Некорректная цена продажи'];
        yield 'нулевая цена' => [['Цена: Цена продажи' => '0'], 'больше нуля'];
        yield 'отрицательная закупка' => [['Закупочная цена' => '-5'], 'не может быть отрицательной'];
        yield 'длинный код' => [['Внешний код' => str_repeat('x', 101)], 'длиннее 100'];
    }

    /** @param array<string, mixed> $values */
    #[DataProvider('invalidRows')]
    public function testRejectsInvalidRow(array $values, string $expectedMessage): void
    {
        try {
            $this->parser->parse($this->row($values));
            self::fail('Ожидалось исключение');
        } catch (RowValidationException $e) {
            self::assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    public function testCollectsAllErrorsOfRow(): void
    {
        $this->expectException(RowValidationException::class);
        $this->expectExceptionMessage('Не заполнено наименование; Некорректная цена продажи: «abc»');

        $this->parser->parse($this->row(['Наименование' => null, 'Цена: Цена продажи' => 'abc']));
    }

    public function testWarnsAboutNegativeDiscountAndBadUrls(): void
    {
        $data = $this->parser->parse($this->row([
            'Закупочная цена'           => '2000',
            'Доп. поле: Ссылки на фото' => 'not-a-url, ftp://example.com/x.jpg, http://example.com/ok.jpg',
        ]));

        self::assertSame('-51.52', $data->discount);
        self::assertSame(['http://example.com/ok.jpg', 'http://example.com/pack.jpg'], $data->imageUrls);
        self::assertCount(3, $data->warnings);
    }

    public function testDiscountIsNullWithoutPurchasePrice(): void
    {
        self::assertNull($this->parser->parse($this->row(['Закупочная цена' => null]))->discount);
    }

    public function testRequiresMandatoryColumns(): void
    {
        $this->expectException(InvalidImportFileException::class);
        $this->expectExceptionMessage('Внешний код');

        ProductRowParser::fromHeaders(['Наименование', 'Цена: Цена продажи']);
    }
}
