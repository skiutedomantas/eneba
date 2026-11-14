const controlClassName =
  'w-full appearance-none border border-outline bg-card px-3 py-3 text-sm text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings'

function CatalogControls() {
  return (
    <div
      aria-label="Catalog filter preview"
      className="mb-5 grid grid-cols-2 gap-3 border border-outline bg-card p-3 md:grid-cols-3"
      role="group"
    >
      <label className="text-xs font-bold uppercase tracking-wide text-muted">
        Platform
        <select className={`${controlClassName} mt-2`} defaultValue="all">
          <option value="all">All platforms</option>
          <option value="pc">PC</option>
          <option value="console">Console</option>
        </select>
      </label>
      <label className="text-xs font-bold uppercase tracking-wide text-muted">
        Region
        <select className={`${controlClassName} mt-2`} defaultValue="all">
          <option value="all">All regions</option>
          <option value="global">Global</option>
          <option value="eu">Europe</option>
        </select>
      </label>
      <label className="col-span-2 text-xs font-bold uppercase tracking-wide text-muted md:col-span-1">
        Sort by
        <select className={`${controlClassName} mt-2`} defaultValue="featured">
          <option value="featured">Featured</option>
          <option value="price-low">Price: low to high</option>
          <option value="price-high">Price: high to low</option>
        </select>
      </label>
    </div>
  )
}

export { CatalogControls }
