<?php
// tests/SaleItemTest.php

namespace App\Tests;

use App\Entity\SaleItem;
use PHPUnit\Framework\TestCase;

class SaleItemTest extends TestCase
{
    public function testCalculateSubtotal(): void
    {
        $item = new SaleItem();
        $item->setUnitPrice(250.50);
        $item->setQuantity(3);
        $item->calculateSubtotal();
        $this->assertEquals(751.5, $item->getSubtotal());
    }
}
