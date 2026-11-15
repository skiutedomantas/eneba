import type { ProductCardData } from '../../types/catalog'
import { ProductCard } from './ProductCard'

interface ProductGridProps {
  products: ProductCardData[]
}

function ProductGrid({ products }: ProductGridProps) {
  return (
    <div
      aria-label="Products"
      className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-5"
      role="list"
    >
      {products.map((product) => (
        <div className="min-w-0" key={product.id} role="listitem">
          <ProductCard product={product} />
        </div>
      ))}
    </div>
  )
}

export { ProductGrid }
