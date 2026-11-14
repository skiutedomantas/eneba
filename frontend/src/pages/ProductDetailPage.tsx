import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { ResourceFeedback } from '../components/ui/ResourceFeedback'
import { SectionPanel } from '../components/ui/SectionPanel'
import { useApiResource } from '../hooks/useApiResource'
import { getProductDetail } from '../lib/api/catalog'
import type { ProductDetailOffer } from '../types/catalog'

function formatPrice(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
  }).format(amountMinor / 100)
}

function offerAvailability(offer: ProductDetailOffer): string {
  if (offer.availableQuantity === 0) {
    return 'Out of stock'
  }
  return `${offer.availableQuantity} available`
}

function ProductDetailPage() {
  const { slug } = useParams()
  const [selectedOfferId, setSelectedOfferId] = useState<number | null>(null)
  const productResource = useApiResource(
    `product:${slug ?? ''}`,
    (signal) => getProductDetail(slug ?? '', signal),
    Boolean(slug),
  )
  const result = productResource.data?.data
  const defaultOffer =
    result?.offers.find((offer) => offer.availableQuantity > 0) ??
    result?.offers[0] ??
    null
  const selectedOffer =
    result?.offers.find((offer) => offer.offerId === selectedOfferId) ??
    defaultOffer

  return (
    <div className="min-h-screen bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <Link
          className="w-fit text-sm font-bold text-text underline decoration-savings underline-offset-4 hover:text-savings"
          to="/store"
        >
          Back to the store
        </Link>
        <ResourceFeedback
          empty={false}
          emptyMessage=""
          error={productResource.error}
          loading={productResource.loading}
          onRetry={productResource.reload}
        />
        {result ? (
          <>
            <section className="grid gap-6 border border-outline bg-section p-4 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] md:p-6">
              <div className="game-artwork relative aspect-[4/3] overflow-hidden border border-outline">
                {result.product.imageUrl ? (
                  <img
                    alt={result.product.name}
                    className="absolute inset-0 h-full w-full object-cover"
                    src={result.product.imageUrl}
                  />
                ) : (
                  <span className="relative z-10 text-center text-lg font-black uppercase tracking-[0.2em]">
                    GameHub
                  </span>
                )}
              </div>
              <div className="flex flex-col justify-center">
                <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
                  Digital product
                </p>
                <h1 className="mt-3 text-3xl font-black md:text-5xl">
                  {result.product.name}
                </h1>
                <p className="mt-4 leading-7 text-muted">
                  {result.product.description}
                </p>
                {selectedOffer ? (
                  <div className="mt-6 border-l-4 border-savings bg-card p-4">
                    <p className="text-xs font-black uppercase tracking-wide text-savings">
                      Selected offer
                    </p>
                    <p className="mt-2 text-lg font-extrabold">
                      {selectedOffer.seller.name}
                    </p>
                    <p className="mt-1 text-sm text-muted">
                      {selectedOffer.platform.name} / {selectedOffer.region.name}
                    </p>
                    <p className="mt-3 text-2xl font-black">
                      {formatPrice(selectedOffer.priceMinor, selectedOffer.currency)}
                    </p>
                    <p className="mt-1 text-sm font-bold text-region">
                      {offerAvailability(selectedOffer)}
                    </p>
                  </div>
                ) : null}
              </div>
            </section>

            <SectionPanel
              description="Compare seller, current price, platform, region, and live key availability before choosing an offer."
              eyebrow="Offer comparison"
              id="seller-offers"
              title={`Compare offers (${result.offers.length})`}
            >
              {result.offers.length > 0 ? (
                <ol className="space-y-3">
                  {result.offers.map((offer) => {
                    const selected = selectedOffer?.offerId === offer.offerId
                    const compareAtPrice =
                      offer.compareAtPriceMinor !== null &&
                      offer.compareAtPriceMinor > offer.priceMinor
                        ? formatPrice(offer.compareAtPriceMinor, offer.currency)
                        : null

                    return (
                      <li key={offer.offerId}>
                        <button
                          aria-pressed={selected}
                          className={`grid w-full gap-3 border p-4 text-left transition-colors md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_auto] md:items-center ${
                            selected
                              ? 'border-savings bg-card'
                              : 'border-outline bg-card hover:border-text'
                          }`}
                          onClick={() => setSelectedOfferId(offer.offerId)}
                          type="button"
                        >
                          <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-muted">
                              Seller
                            </span>
                            <span className="mt-1 block font-extrabold">
                              {offer.seller.name}
                            </span>
                            {selected ? (
                              <span className="mt-1 inline-block text-xs font-black uppercase text-savings">
                                Selected
                              </span>
                            ) : null}
                          </span>
                          <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-muted">
                              Price
                            </span>
                            {compareAtPrice ? (
                              <span className="mt-1 block text-xs text-muted line-through">
                                {compareAtPrice}
                              </span>
                            ) : null}
                            <span className="block text-lg font-black">
                              {formatPrice(offer.priceMinor, offer.currency)}
                            </span>
                          </span>
                          <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-muted">
                              Platform
                            </span>
                            <span className="mt-1 block font-bold">
                              {offer.platform.name}
                            </span>
                          </span>
                          <span>
                            <span className="block text-xs font-bold uppercase tracking-wide text-muted">
                              Region
                            </span>
                            <span className="mt-1 block font-bold text-region">
                              {offer.region.name}
                            </span>
                          </span>
                          <span
                            className={`text-sm font-black ${
                              offer.availableQuantity > 0
                                ? 'text-savings'
                                : 'text-muted'
                            }`}
                          >
                            {offerAvailability(offer)}
                          </span>
                        </button>
                      </li>
                    )
                  })}
                </ol>
              ) : (
                <p className="border border-outline bg-card p-4 text-muted">
                  There are no active seller offers for this product yet.
                </p>
              )}
            </SectionPanel>
          </>
        ) : null}
      </main>
      <SiteFooter />
    </div>
  )
}

export { ProductDetailPage }
