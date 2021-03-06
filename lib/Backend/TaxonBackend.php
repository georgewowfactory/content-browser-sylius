<?php

declare(strict_types=1);

namespace Netgen\ContentBrowser\Sylius\Backend;

use Netgen\ContentBrowser\Backend\BackendInterface;
use Netgen\ContentBrowser\Exceptions\NotFoundException;
use Netgen\ContentBrowser\Item\ItemInterface;
use Netgen\ContentBrowser\Item\LocationInterface;
use Netgen\ContentBrowser\Sylius\Item\Taxon\Item;
use Netgen\ContentBrowser\Sylius\Item\Taxon\TaxonInterface as ContentBrowserTaxonInterface;
use Netgen\ContentBrowser\Sylius\Repository\TaxonRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonBackend implements BackendInterface
{
    /**
     * @var \Netgen\ContentBrowser\Sylius\Repository\TaxonRepositoryInterface
     */
    private $taxonRepository;

    /**
     * @var \Sylius\Component\Locale\Context\LocaleContextInterface
     */
    private $localeContext;

    public function __construct(
        TaxonRepositoryInterface $taxonRepository,
        LocaleContextInterface $localeContext
    ) {
        $this->taxonRepository = $taxonRepository;
        $this->localeContext = $localeContext;
    }

    public function getSections(): iterable
    {
        return $this->buildItems(
            $this->taxonRepository->findRootNodes()
        );
    }

    public function loadLocation($id): LocationInterface
    {
        return $this->loadItem($id);
    }

    public function loadItem($value): ItemInterface
    {
        $taxon = $this->taxonRepository->find($value);

        if (!$taxon instanceof TaxonInterface) {
            throw new NotFoundException(
                sprintf(
                    'Item with value "%s" not found.',
                    $value
                )
            );
        }

        return $this->buildItem($taxon);
    }

    public function getSubLocations(LocationInterface $location): iterable
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return [];
        }

        $taxons = $this->taxonRepository->findChildren(
            (string) $location->getTaxon()->getCode(),
            $this->localeContext->getLocaleCode()
        );

        return $this->buildItems($taxons);
    }

    public function getSubLocationsCount(LocationInterface $location): int
    {
        $subLocations = $this->getSubLocations($location);

        return is_countable($subLocations) ? count($subLocations) : 0;
    }

    public function getSubItems(LocationInterface $location, int $offset = 0, int $limit = 25): iterable
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return [];
        }

        $paginator = $this->taxonRepository->createListPaginator(
            (string) $location->getTaxon()->getCode(),
            $this->localeContext->getLocaleCode()
        );

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage((int) ($offset / $limit) + 1);

        return $this->buildItems($paginator->getCurrentPageResults());
    }

    public function getSubItemsCount(LocationInterface $location): int
    {
        if (!$location instanceof ContentBrowserTaxonInterface) {
            return 0;
        }

        $paginator = $this->taxonRepository->createListPaginator(
            (string) $location->getTaxon()->getCode(),
            $this->localeContext->getLocaleCode()
        );

        return $paginator->getNbResults();
    }

    public function search(string $searchText, int $offset = 0, int $limit = 25): iterable
    {
        $paginator = $this->taxonRepository->createSearchPaginator(
            $searchText,
            $this->localeContext->getLocaleCode()
        );

        $paginator->setMaxPerPage($limit);
        $paginator->setCurrentPage((int) ($offset / $limit) + 1);

        return $this->buildItems(
            $paginator->getCurrentPageResults()
        );
    }

    public function searchCount(string $searchText): int
    {
        $paginator = $this->taxonRepository->createSearchPaginator(
            $searchText,
            $this->localeContext->getLocaleCode()
        );

        return $paginator->getNbResults();
    }

    /**
     * Builds the item from provided taxon.
     */
    private function buildItem(TaxonInterface $taxon): Item
    {
        return new Item($taxon);
    }

    /**
     * Builds the items from provided products.
     *
     * @param \Sylius\Component\Taxonomy\Model\TaxonInterface[] $taxons
     *
     * @return \Netgen\ContentBrowser\Sylius\Item\Taxon\Item[]
     */
    private function buildItems(iterable $taxons): array
    {
        $items = [];

        foreach ($taxons as $taxon) {
            $items[] = $this->buildItem($taxon);
        }

        return $items;
    }
}
