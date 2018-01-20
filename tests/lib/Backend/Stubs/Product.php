<?php

namespace Netgen\ContentBrowser\Tests\Backend\Stubs;

use Sylius\Component\Product\Model\Product as BaseProduct;

final class Product extends BaseProduct
{
    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}
