<?php

declare(strict_types=1);

namespace Netgen\ContentBrowser\Backend;

use Netgen\ContentBrowser\Backend\Sylius\ProductRepositoryInterface;
use Netgen\ContentBrowser\Exceptions\NotFoundException;
use Netgen\ContentBrowser\Item\ItemInterface;
use Netgen\ContentBrowser\Item\LocationInterface;
use Netgen\ContentBrowser\Item\Sylius\Product\Item;
use Netgen\ContentBrowser\Item\Sylius\Product\Location;
use Netgen\ContentBrowser\Item\Sylius\Product\TaxonInterface as ContentBrowserTaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final class SyliusProductBackend implements BackendInterface
{
    /**
     * @var \Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface
     */
    private $taxonRepository;

    /**
     * @var \Netgen\ContentBrowser\Backend\Sylius\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Sylius\Component\Locale\Context\LocaleContextInterface
     */
    private $localeContext;

    public function __construct(
        TaxonRepositoryInterface $taxonRepository,
        ProductRepositoryInterface $productRepository,
        LocaleContextInterface $localeContext
    ) {
        $this->taxonRepository = $taxonRepository;
        $this->productRepository = $productRepository;
        $this->localeContext = $localeContext;
    }

    public function getDefaultSections()
    {
        return $this->buildLocations(
            $this->taxonRepository->findRootNodes()
        );
    }

    public function loadLocation($id): LocationInterface
    {
        $taxon = $this->taxonRepository->find($id);

        if (!$taxon instanceof TaxonInterface) {
            throw new NotFoundException(
                sprintf(
                    'Location with ID "%s" not found.',
                    $id
                )
            );
        }

        return $this->buildLocation($taxon);
    }

    public function loadItem($value): ItemInterface
    {
        $product = $this->productRepository->find($value);

        if (!$product instanceof ProductInterface) {
            throw new NotFoundException(
                sprintf(
                    'Item with value "%s" not found.',
                    $value
                )
            );
        }

        return $this->buildItem($product);
    }

    public function getSubLocations(LocationInterface $location)
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return [];
        }

        $taxons = $this->taxonRepository->findBy(
            [
                'parent' => $location->getTaxon(),
            ]
        );

        return $this->buildLocations($taxons);
    }

    public function getSubLocationsCount(LocationInterface $location): int
    {
        return count($this->getSubLocations($location));
    }

    public function getSubItems(LocationInterface $location, $offset = 0, $limit = 25)
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return [];
        }

        $paginator = $this->productRepository->createByTaxonPaginator(
            $location->getTaxon(),
            $this->localeContext->getLocaleCode()
        );

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage((int) ($offset / $limit) + 1);

        return $this->buildItems(
            $paginator->getCurrentPageResults()
        );
    }

    public function getSubItemsCount(LocationInterface $location): int
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return 0;
        }

        $paginator = $this->productRepository->createByTaxonPaginator(
            $location->getTaxon(),
            $this->localeContext->getLocaleCode()
        );

        return $paginator->getNbResults();
    }

    public function search($searchText, $offset = 0, $limit = 25)
    {
        $paginator = $this->productRepository->createSearchPaginator(
            $searchText,
            $this->localeContext->getLocaleCode()
        );

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage((int) ($offset / $limit) + 1);

        return $this->buildItems(
            $paginator->getCurrentPageResults()
        );
    }

    public function searchCount($searchText): int
    {
        $paginator = $this->productRepository->createSearchPaginator(
            $searchText,
            $this->localeContext->getLocaleCode()
        );

        return $paginator->getNbResults();
    }

    /**
     * Builds the location from provided taxon.
     */
    private function buildLocation(TaxonInterface $taxon): Location
    {
        return new Location($taxon);
    }

    /**
     * Builds the locations from provided taxons.
     *
     * @param \Sylius\Component\Taxonomy\Model\TaxonInterface[] $taxons
     *
     * @return \Netgen\ContentBrowser\Item\Sylius\Product\Location[]
     */
    private function buildLocations(array $taxons): array
    {
        return array_map(
            function (TaxonInterface $taxon): Location {
                return $this->buildLocation($taxon);
            },
            $taxons
        );
    }

    /**
     * Builds the item from provided product.
     */
    private function buildItem(ProductInterface $product): Item
    {
        return new Item($product);
    }

    /**
     * Builds the items from provided products.
     *
     * @param \Sylius\Component\Product\Model\ProductInterface[] $products
     *
     * @return \Netgen\ContentBrowser\Item\Sylius\Product\Item[]
     */
    private function buildItems(iterable $products): array
    {
        $items = [];

        foreach ($products as $product) {
            $items[] = $this->buildItem($product);
        }

        return $items;
    }
}
