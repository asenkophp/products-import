<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use App\Http\ValidationException;
use App\Presenter\ProductPresenter;
use App\Service\Product\ProductCatalog;
use App\Service\Product\ProductListQuery;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProductController
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly ProductPresenter $presenter,
    ) {}

    /** GET /api/products?page=1&limit=20&name=&price_min=&price_max= */
    public function list(Request $request, Response $response): Response
    {
        try {
            /** @var array<string, mixed> $params */
            $params = $request->getQueryParams();
            $query  = ProductListQuery::fromArray($params);
        } catch (ValidationException $e) {
            return Json::error($response, $e->getMessage(), 422, $e->getErrors());
        }

        return Json::write($response, $this->presenter->page($this->catalog->list($query)));
    }

    /**
     * GET /api/products/{id}
     *
     * @param array{id: string} $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $product = $this->catalog->get((int) $args['id']);
        if ($product === null) {
            return Json::error($response, 'Товар не найден', 404);
        }

        return Json::write($response, $this->presenter->detail($product));
    }
}
