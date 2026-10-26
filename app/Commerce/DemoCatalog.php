<?php

namespace App\Commerce;

/** Synthetic display fixtures; never submitted to a provider or offered for checkout. */
class DemoCatalog
{
    public static function products(string $clinic): array
    {
        $products = $clinic === 'harbor'
            ? [['Demo soft toothbrush', 'A synthetic catalog item for the Harbor clinic preview.', '175.00'], ['Demo floss set', 'A synthetic catalog item. No purchase is available.', '220.00'], ['Demo travel case', 'Preview packaging for the local storefront.', '320.00']]
            : [['Demo bamboo toothbrush', 'A synthetic catalog item for the Cedar clinic preview.', '190.00'], ['Demo reusable travel pouch', 'Preview packaging. No purchase is available.', '260.00'], ['Demo floss picks', 'A synthetic retail product for the local preview.', '145.00']];

        return ['demo' => true, 'notice' => 'Synthetic demonstration catalog. Prices and products are fictional; checkout is disabled.', 'nodes' => array_map(fn ($product, $index) => ['id' => 'demo-'.$clinic.'-'.$index, 'title' => $product[0], 'description' => $product[1], 'featuredImage' => null, 'variants' => ['nodes' => [['id' => 'demo-variant-'.$clinic.'-'.$index, 'title' => 'Demo only', 'availableForSale' => false, 'price' => ['amount' => $product[2], 'currencyCode' => 'PHP']]]]], $products, array_keys($products)), 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]];
    }
}
