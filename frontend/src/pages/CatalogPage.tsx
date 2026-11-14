import { useMemo, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { CatalogControls, type CatalogFilterValues } from '../components/ui/CatalogControls'
import { ProductGrid } from '../components/ui/ProductGrid'
import { ResourceFeedback } from '../components/ui/ResourceFeedback'
import { SectionPanel } from '../components/ui/SectionPanel'
import { StatusMessage } from '../components/ui/StatusMessage'
import { useApiResource } from '../hooks/useApiResource'
import {
  getCategories,
  getPlatforms,
  getProducts,
  getRegions,
} from '../lib/api/catalog'
import type { CatalogQuery } from '../lib/api/catalog'

function toMajorPrice(minorValue: string | null): string {
  if (!minorValue || !/^\d+$/.test(minorValue)) {
    return ''
  }

  const minor = Number(minorValue)
  return Number.isSafeInteger(minor)
    ? `${Math.floor(minor / 100)}.${String(minor % 100).padStart(2, '0')}`
    : ''
}

function toMinorPrice(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) {
    return null
  }

  const match = /^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/.exec(trimmed)
  if (!match) {
    return null
  }

  const major = Number(match[1])
  const minor = Number((match[2] ?? '').padEnd(2, '0'))
  const total = major * 100 + minor
  return Number.isSafeInteger(total) ? String(total) : null
}

function initialFilters(params: URLSearchParams): CatalogFilterValues {
  return {
    category: params.get('category') ?? '',
    platform: params.get('platform') ?? '',
    region: params.get('region') ?? '',
    minPrice: toMajorPrice(params.get('minPriceMinor')),
    maxPrice: toMajorPrice(params.get('maxPriceMinor')),
    sort: params.get('sort') ?? 'newest',
  }
}

function CatalogPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const searchKey = searchParams.toString()
  const [priceError, setPriceError] = useState<string | null>(null)

  const query = useMemo<CatalogQuery>(() => {
    const params = new URLSearchParams(searchKey)
    return {
      page: Number(params.get('page') ?? 1),
      perPage: Number(params.get('perPage') ?? 20),
      category: params.get('category') ?? '',
      platform: params.get('platform') ?? '',
      region: params.get('region') ?? '',
      currency: params.get('currency') ?? 'EUR',
      minPriceMinor: params.get('minPriceMinor') ?? '',
      maxPriceMinor: params.get('maxPriceMinor') ?? '',
      sort: params.get('sort') ?? 'newest',
    }
  }, [searchKey])
  const catalogKey = JSON.stringify(query)

  const products = useApiResource(catalogKey, (signal) =>
    getProducts(query, signal),
  )
  const categories = useApiResource('lookup:categories', getCategories)
  const platforms = useApiResource('lookup:platforms', getPlatforms)
  const regions = useApiResource('lookup:regions', getRegions)

  const applyFilters = (
    event: FormEvent<HTMLFormElement>,
    filters: CatalogFilterValues,
  ) => {
    event.preventDefault()
    const minPriceMinor = toMinorPrice(filters.minPrice)
    const maxPriceMinor = toMinorPrice(filters.maxPrice)

    if (filters.minPrice.trim() && minPriceMinor === null) {
      setPriceError('Minimum price must be a non-negative amount with up to two decimals.')
      return
    }
    if (filters.maxPrice.trim() && maxPriceMinor === null) {
      setPriceError('Maximum price must be a non-negative amount with up to two decimals.')
      return
    }
    if (
      minPriceMinor !== null &&
      maxPriceMinor !== null &&
      Number(minPriceMinor) > Number(maxPriceMinor)
    ) {
      setPriceError('Minimum price must not exceed maximum price.')
      return
    }

    setPriceError(null)
    const next = new URLSearchParams()
    next.set('page', '1')
    next.set('perPage', String(query.perPage))
    next.set('currency', 'EUR')
    next.set('sort', filters.sort)
    if (filters.category) next.set('category', filters.category)
    if (filters.platform) next.set('platform', filters.platform)
    if (filters.region) next.set('region', filters.region)
    if (minPriceMinor !== null) next.set('minPriceMinor', minPriceMinor)
    if (maxPriceMinor !== null) next.set('maxPriceMinor', maxPriceMinor)
    setSearchParams(next)
  }

  const changePage = (page: number) => {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  const result = products.data
  const filters = initialFilters(new URLSearchParams(searchKey))
  const totalPages = result
    ? Math.max(1, Math.ceil(result.meta.total / result.meta.perPage))
    : 1

  return (
    <div className="min-h-screen bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <SectionPanel
          description="Browse the current catalog and narrow results by category, platform, region, or price."
          eyebrow="Game marketplace"
          id="catalog"
          title="Games and digital goods"
        >
          <CatalogControls
            categories={categories.data?.data ?? []}
            platforms={platforms.data?.data ?? []}
            regions={regions.data?.data ?? []}
            initialValues={filters}
            key={searchKey}
            onApply={applyFilters}
          />
          {priceError ? (
            <div className="mb-4">
              <StatusMessage
                message={priceError}
                title="Check the price range"
                tone="error"
              />
            </div>
          ) : null}
          {categories.error || platforms.error || regions.error ? (
            <div className="mb-4">
              <StatusMessage
                message="Some filter choices could not be loaded. You can still browse the catalog."
                title="Filter options unavailable"
                tone="error"
              />
            </div>
          ) : null}
          {result ? (
            <p className="mb-4 text-sm text-muted" aria-live="polite">
              {result.meta.total} products
            </p>
          ) : null}
          <ResourceFeedback
            empty={result !== null && result.data.length === 0}
            emptyMessage="Try changing or clearing your filters."
            error={products.error}
            loading={products.loading}
            onRetry={products.reload}
          />
          {result && result.data.length > 0 && !products.loading ? (
            <>
              <ProductGrid products={result.data} />
              <nav
                aria-label="Catalog pagination"
                className="mt-6 flex items-center justify-between border-t border-outline pt-4"
              >
                <button
                  className="border border-outline bg-card px-4 py-2 text-sm font-bold text-text disabled:cursor-not-allowed disabled:opacity-40"
                  disabled={query.page <= 1}
                  onClick={() => changePage(query.page - 1)}
                  type="button"
                >
                  Previous
                </button>
                <span className="text-sm text-muted" aria-live="polite">
                  Page {query.page} of {totalPages}
                </span>
                <button
                  className="border border-outline bg-card px-4 py-2 text-sm font-bold text-text disabled:cursor-not-allowed disabled:opacity-40"
                  disabled={query.page >= totalPages}
                  onClick={() => changePage(query.page + 1)}
                  type="button"
                >
                  Next
                </button>
              </nav>
            </>
          ) : null}
        </SectionPanel>
      </main>
      <SiteFooter />
    </div>
  )
}

export { CatalogPage }
