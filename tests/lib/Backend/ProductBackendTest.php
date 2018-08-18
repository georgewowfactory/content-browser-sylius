<?php

declare(strict_types=1);

namespace Netgen\ContentBrowser\Sylius\Tests\Backend;

use ArrayIterator;
use Netgen\ContentBrowser\Sylius\Backend\ProductBackend;
use Netgen\ContentBrowser\Sylius\Item\Product\Item;
use Netgen\ContentBrowser\Sylius\Item\Product\Location;
use Netgen\ContentBrowser\Sylius\Repository\ProductRepositoryInterface;
use Netgen\ContentBrowser\Sylius\Tests\Stubs\Location as StubLocation;
use Netgen\ContentBrowser\Sylius\Tests\Stubs\Product;
use Netgen\ContentBrowser\Sylius\Tests\Stubs\Taxon;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final class ProductBackendTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $taxonRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $productRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $localeContextMock;

    /**
     * @var \Netgen\ContentBrowser\Sylius\Backend\ProductBackend
     */
    private $backend;

    public function setUp(): void
    {
        $this->taxonRepositoryMock = $this->createMock(TaxonRepositoryInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->localeContextMock = $this->createMock(LocaleContextInterface::class);

        $this->localeContextMock
            ->expects(self::any())
            ->method('getLocaleCode')
            ->will(self::returnValue('en'));

        $this->backend = new ProductBackend(
            $this->taxonRepositoryMock,
            $this->productRepositoryMock,
            $this->localeContextMock
        );
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::__construct
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildLocations
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSections
     */
    public function testGetSections(): void
    {
        $this->taxonRepositoryMock
            ->expects(self::once())
            ->method('findRootNodes')
            ->will(self::returnValue([$this->getTaxon(1), $this->getTaxon(2)]));

        $locations = $this->backend->getSections();

        self::assertCount(2, $locations);
        self::assertContainsOnlyInstancesOf(Location::class, $locations);
        self::assertSame(1, $locations[0]->getLocationId());
        self::assertSame(2, $locations[1]->getLocationId());
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildLocation
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::loadLocation
     */
    public function testLoadLocation(): void
    {
        $this->taxonRepositoryMock
            ->expects(self::once())
            ->method('find')
            ->with(self::identicalTo(1))
            ->will(self::returnValue($this->getTaxon(1)));

        $location = $this->backend->loadLocation(1);

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->getLocationId());
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::loadLocation
     * @expectedException \Netgen\ContentBrowser\Exceptions\NotFoundException
     * @expectedExceptionMessage Location with ID "1" not found.
     */
    public function testLoadLocationThrowsNotFoundException(): void
    {
        $this->taxonRepositoryMock
            ->expects(self::once())
            ->method('find')
            ->with(self::identicalTo(1))
            ->will(self::returnValue(null));

        $this->backend->loadLocation(1);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItem
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::loadItem
     */
    public function testLoadItem(): void
    {
        $this->productRepositoryMock
            ->expects(self::once())
            ->method('find')
            ->with(self::identicalTo(1))
            ->will(self::returnValue($this->getProduct(1)));

        $item = $this->backend->loadItem(1);

        self::assertInstanceOf(Item::class, $item);
        self::assertSame(1, $item->getValue());
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::loadItem
     * @expectedException \Netgen\ContentBrowser\Exceptions\NotFoundException
     * @expectedExceptionMessage Item with value "1" not found.
     */
    public function testLoadItemThrowsNotFoundException(): void
    {
        $this->productRepositoryMock
            ->expects(self::once())
            ->method('find')
            ->with(self::identicalTo(1))
            ->will(self::returnValue(null));

        $this->backend->loadItem(1);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildLocation
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildLocations
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubLocations
     */
    public function testGetSubLocations(): void
    {
        $taxon = $this->getTaxon(1);

        $this->taxonRepositoryMock
            ->expects(self::once())
            ->method('findBy')
            ->with(self::identicalTo(['parent' => $taxon]))
            ->will(self::returnValue([$this->getTaxon(2, 1), $this->getTaxon(3, 1)]));

        $locations = $this->backend->getSubLocations(
            new Location($taxon)
        );

        self::assertCount(2, $locations);
        self::assertContainsOnlyInstancesOf(Location::class, $locations);

        foreach ($locations as $location) {
            self::assertSame(1, $location->getParentId());
        }
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubLocations
     */
    public function testGetSubLocationsWithInvalidItem(): void
    {
        $this->taxonRepositoryMock
            ->expects(self::never())
            ->method('findBy');

        $locations = $this->backend->getSubLocations(new StubLocation(0));

        self::assertSame([], $locations);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubLocationsCount
     */
    public function testGetSubLocationsCount(): void
    {
        $taxon = $this->getTaxon(1);

        $this->taxonRepositoryMock
            ->expects(self::once())
            ->method('findBy')
            ->with(self::identicalTo(['parent' => $taxon]))
            ->will(self::returnValue([$this->getTaxon(2), $this->getTaxon(3)]));

        $count = $this->backend->getSubLocationsCount(
            new Location($taxon)
        );

        self::assertSame(2, $count);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItem
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItems
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubItems
     */
    public function testGetSubItems(): void
    {
        $taxon = $this->getTaxon(1);

        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);
        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getSlice')
            ->with(self::identicalTo(0), self::identicalTo(25))
            ->will(self::returnValue(new ArrayIterator([$this->getProduct(), $this->getProduct()])));

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createByTaxonPaginator')
            ->with(self::identicalTo($taxon), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $items = $this->backend->getSubItems(
            new Location($taxon)
        );

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(Item::class, $items);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubItems
     */
    public function testGetSubItemsWithInvalidItem(): void
    {
        $this->productRepositoryMock
            ->expects(self::never())
            ->method('createByTaxonPaginator');

        $items = $this->backend->getSubItems(new StubLocation(0));

        self::assertSame([], $items);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItem
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItems
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubItems
     */
    public function testGetSubItemsWithOffsetAndLimit(): void
    {
        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);

        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getNbResults')
            ->will(self::returnValue(15));

        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getSlice')
            ->with(self::identicalTo(8), self::identicalTo(2))
            ->will(self::returnValue(new ArrayIterator([$this->getProduct(), $this->getProduct()])));

        $taxon = $this->getTaxon(1);

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createByTaxonPaginator')
            ->with(self::identicalTo($taxon), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $items = $this->backend->getSubItems(
            new Location($taxon),
            8,
            2
        );

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(Item::class, $items);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubItemsCount
     */
    public function testGetSubItemsCount(): void
    {
        $taxon = $this->getTaxon(1);

        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);
        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getNbResults')
            ->will(self::returnValue(2));

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createByTaxonPaginator')
            ->with(self::identicalTo($taxon), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $count = $this->backend->getSubItemsCount(
            new Location($taxon)
        );

        self::assertSame(2, $count);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::getSubItemsCount
     */
    public function testGetSubItemsCountWithInvalidItem(): void
    {
        $this->productRepositoryMock
            ->expects(self::never())
            ->method('createByTaxonPaginator');

        $count = $this->backend->getSubItemsCount(new StubLocation(0));

        self::assertSame(0, $count);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItem
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItems
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::search
     */
    public function testSearch(): void
    {
        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);
        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getSlice')
            ->with(self::identicalTo(0), self::identicalTo(25))
            ->will(self::returnValue(new ArrayIterator([$this->getProduct(), $this->getProduct()])));

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createSearchPaginator')
            ->with(self::identicalTo('test'), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $items = $this->backend->search('test');

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(Item::class, $items);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItem
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::buildItems
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::search
     */
    public function testSearchWithOffsetAndLimit(): void
    {
        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);

        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getNbResults')
            ->will(self::returnValue(15));

        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getSlice')
            ->with(self::identicalTo(8), self::identicalTo(2))
            ->will(self::returnValue(new ArrayIterator([$this->getProduct(), $this->getProduct()])));

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createSearchPaginator')
            ->with(self::identicalTo('test'), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $items = $this->backend->search('test', 8, 2);

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(Item::class, $items);
    }

    /**
     * @covers \Netgen\ContentBrowser\Sylius\Backend\ProductBackend::searchCount
     */
    public function testSearchCount(): void
    {
        $pagerfantaAdapterMock = $this->createMock(AdapterInterface::class);
        $pagerfantaAdapterMock
            ->expects(self::any())
            ->method('getNbResults')
            ->will(self::returnValue(2));

        $this->productRepositoryMock
            ->expects(self::once())
            ->method('createSearchPaginator')
            ->with(self::identicalTo('test'), self::identicalTo('en'))
            ->will(self::returnValue(new Pagerfanta($pagerfantaAdapterMock)));

        $count = $this->backend->searchCount('test');

        self::assertSame(2, $count);
    }

    /**
     * Returns the taxon object used in tests.
     *
     * @param int|string $id
     * @param int|string $parentId
     *
     * @return \Netgen\ContentBrowser\Sylius\Tests\Stubs\Taxon
     */
    private function getTaxon($id = null, $parentId = null): Taxon
    {
        $taxon = new Taxon();
        $taxon->setId($id);

        if ($parentId !== null) {
            $taxon->setParent(
                $this->getTaxon($parentId)
            );
        }

        return $taxon;
    }

    /**
     * Returns the product object used in tests.
     *
     * @param int|string $id
     *
     * @return \Netgen\ContentBrowser\Sylius\Tests\Stubs\Product
     */
    private function getProduct($id = null): Product
    {
        $product = new Product();
        $product->setId($id);

        return $product;
    }
}