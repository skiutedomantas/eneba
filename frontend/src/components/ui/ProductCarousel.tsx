import { useRef } from 'react'
import type { ProductCardData } from '../../types/catalog'
import { ProductCard } from './ProductCard'

interface ProductCarouselProps {
  id: string
  label: string
  products: ProductCardData[]
}

function ProductCarousel({ id, label, products }: ProductCarouselProps) {
  const trackRef = useRef<HTMLDivElement>(null)

  function scroll(direction: -1 | 1) {
    const track = trackRef.current
    if (!track) {
      return
    }

    track.scrollBy({
      left: direction * track.clientWidth,
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'auto'
        : 'smooth',
    })
  }

  return (
    <div
      aria-label={label}
      aria-roledescription="carousel"
      className="space-y-3"
      role="region"
    >
      <div
        aria-label={`${label} controls`}
        className="flex justify-end gap-2"
        role="group"
      >
        <button
          aria-controls={id}
          aria-label={`Previous ${label.toLowerCase()}`}
          className="grid size-10 place-items-center border border-outline bg-card text-xl font-black text-text hover:border-savings focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings"
          onClick={() => scroll(-1)}
          type="button"
        >
          <span aria-hidden="true">←</span>
        </button>
        <button
          aria-controls={id}
          aria-label={`Next ${label.toLowerCase()}`}
          className="grid size-10 place-items-center border border-outline bg-card text-xl font-black text-text hover:border-savings focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings"
          onClick={() => scroll(1)}
          type="button"
        >
          <span aria-hidden="true">→</span>
        </button>
      </div>
      <div
        aria-label={`${label} products`}
        className="grid auto-cols-[calc((100%_-_0.75rem)/2)] grid-flow-col gap-3 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings md:auto-cols-[calc((100%_-_2rem)/3)] md:gap-4 lg:auto-cols-[calc((100%_-_4rem)/5)]"
        id={id}
        ref={trackRef}
        role="list"
        tabIndex={0}
      >
        {products.map((product, index) => (
          <div
            aria-label={`${product.name}, ${index + 1} of ${products.length}`}
            aria-roledescription="slide"
            className="min-w-0 snap-start"
            key={product.id}
            role="listitem"
          >
            <ProductCard product={product} />
          </div>
        ))}
      </div>
    </div>
  )
}

export { ProductCarousel }
