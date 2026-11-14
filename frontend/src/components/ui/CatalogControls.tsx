import { useState, type FormEvent } from 'react'
import type { CategoryOption, CodedOption } from '../../types/catalog'

export interface CatalogFilterValues {
  category: string
  platform: string
  region: string
  minPrice: string
  maxPrice: string
  sort: string
}

interface CatalogControlsProps {
  categories: CategoryOption[]
  platforms: CodedOption[]
  regions: CodedOption[]
  initialValues: CatalogFilterValues
  onApply: (
    event: FormEvent<HTMLFormElement>,
    values: CatalogFilterValues,
  ) => void
}

const controlClassName =
  'mt-2 w-full appearance-none border border-outline bg-card px-3 py-3 text-sm text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings'

function CatalogControls({
  categories,
  platforms,
  regions,
  initialValues,
  onApply,
}: CatalogControlsProps) {
  const [values, setValues] = useState(initialValues)
  const update = (field: keyof CatalogFilterValues, value: string) => {
    setValues((current) => ({ ...current, [field]: value }))
  }

  return (
    <form
      className="mb-5 border border-outline bg-card p-3 md:p-4"
      onSubmit={(event) => onApply(event, values)}
    >
      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Category
          <select
            className={controlClassName}
            onChange={(event) => update('category', event.target.value)}
            value={values.category}
          >
            <option value="">All categories</option>
            {categories.map((category) => (
              <option key={category.id} value={category.slug}>
                {category.name}
              </option>
            ))}
          </select>
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Platform
          <select
            className={controlClassName}
            onChange={(event) => update('platform', event.target.value)}
            value={values.platform}
          >
            <option value="">All platforms</option>
            {platforms.map((platform) => (
              <option key={platform.id} value={platform.code}>
                {platform.name}
              </option>
            ))}
          </select>
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Region
          <select
            className={controlClassName}
            onChange={(event) => update('region', event.target.value)}
            value={values.region}
          >
            <option value="">All regions</option>
            {regions.map((region) => (
              <option key={region.id} value={region.code}>
                {region.name}
              </option>
            ))}
          </select>
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Minimum price
          <input
            autoComplete="off"
            className={controlClassName}
            inputMode="decimal"
            onChange={(event) => update('minPrice', event.target.value)}
            placeholder="€0.00"
            value={values.minPrice}
          />
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Maximum price
          <input
            autoComplete="off"
            className={controlClassName}
            inputMode="decimal"
            onChange={(event) => update('maxPrice', event.target.value)}
            placeholder="No limit"
            value={values.maxPrice}
          />
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-muted">
          Sort by
          <select
            className={controlClassName}
            onChange={(event) => update('sort', event.target.value)}
            value={values.sort}
          >
            <option value="newest">Newest</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
            <option value="popular">Popular</option>
          </select>
        </label>
      </div>
      <div className="mt-4 flex justify-end">
        <button
          className="border border-savings bg-savings px-5 py-3 text-sm font-black text-card hover:bg-transparent hover:text-savings"
          type="submit"
        >
          Apply filters
        </button>
      </div>
    </form>
  )
}

export { CatalogControls }
