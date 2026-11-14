import type { ProductCardData } from '../../types/catalog'

interface ProductCardProps {
  product: ProductCardData
}

function ProductCard({ product }: ProductCardProps) {
  return (
    <article
      className="flex h-full min-w-0 flex-col border border-outline bg-card"
      role="listitem"
    >
      <div
        aria-hidden="true"
        className={`game-artwork game-artwork--${product.artworkTheme} aspect-[4/5]`}
      >
        <span className="relative z-10 max-w-[80%] text-center text-xs font-black uppercase tracking-[0.2em] text-text/90">
          GameHub
          <br />
          Preview Art
        </span>
        {product.savingsLabel ? (
          <span className="absolute left-0 top-0 z-10 bg-savings px-2 py-1 text-xs font-black text-card">
            {product.savingsLabel}
          </span>
        ) : null}
      </div>
      <div className="flex flex-1 flex-col p-3">
        <p className="text-xs font-bold uppercase tracking-wide text-muted">
          {product.platform}
        </p>
        <h3 className="mt-2 line-clamp-2 min-h-12 text-sm font-extrabold leading-5 text-text">
          {product.title}
        </h3>
        <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-4">
          <div>
            <p className="text-xs text-muted line-through">
              {product.compareAtPrice}
            </p>
            <p className="text-lg font-black text-text">{product.price}</p>
          </div>
          <span className="border border-region px-2 py-1 text-[0.65rem] font-black uppercase tracking-wide text-region">
            {product.region}
          </span>
        </div>
      </div>
    </article>
  )
}

export { ProductCard }
