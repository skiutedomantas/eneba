import type { ProductCardData } from '../../types/catalog'

interface ProductCardProps {
  product: ProductCardData
}

function formatPrice(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
  }).format(amountMinor / 100)
}

function ProductCard({ product }: ProductCardProps) {
  const offer = product.lowestOffer
  const compareAtPrice =
    offer?.compareAtPriceMinor !== null &&
    offer?.compareAtPriceMinor !== undefined &&
    offer.compareAtPriceMinor > offer.priceMinor
      ? formatPrice(offer.compareAtPriceMinor, offer.currency)
      : null

  return (
    <article
      className="flex h-full min-w-0 flex-col border border-outline bg-card"
      role="listitem"
    >
      <div className="game-artwork relative aspect-[4/5] overflow-hidden">
        {product.imageUrl ? (
          <img
            alt=""
            className="absolute inset-0 h-full w-full object-cover"
            loading="lazy"
            src={product.imageUrl}
          />
        ) : (
          <span className="relative z-10 max-w-[80%] text-center text-xs font-black uppercase tracking-[0.2em] text-text/90">
            GameHub
          </span>
        )}
        {offer?.discountPercent ? (
          <span className="absolute left-0 top-0 z-10 bg-savings px-2 py-1 text-xs font-black text-card">
            -{offer.discountPercent}%
          </span>
        ) : null}
      </div>
      <div className="flex flex-1 flex-col p-3">
        <p className="text-xs font-bold uppercase tracking-wide text-muted">
          {offer?.platform.name ?? 'Digital game'}
        </p>
        <h3 className="mt-2 min-h-12 text-sm font-extrabold leading-5 text-text">
          {product.name}
        </h3>
        {product.unitsSold !== undefined ? (
          <p className="mt-1 text-xs text-muted">
            {product.unitsSold} sold in the last 30 days
          </p>
        ) : null}
        <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-4">
          {offer ? (
            <div>
              {compareAtPrice ? (
                <p className="text-xs text-muted line-through">{compareAtPrice}</p>
              ) : null}
              <p className="text-lg font-black text-text">
                {formatPrice(offer.priceMinor, offer.currency)}
              </p>
              {offer.availableQuantity === 0 ? (
                <p className="text-xs font-bold text-muted">Out of stock</p>
              ) : null}
            </div>
          ) : (
            <p className="text-sm font-bold text-muted">No active offers</p>
          )}
          {offer ? (
            <span className="border border-region px-2 py-1 text-[0.65rem] font-black uppercase tracking-wide text-region">
              {offer.region.name}
            </span>
          ) : null}
        </div>
      </div>
    </article>
  )
}

export { ProductCard }
