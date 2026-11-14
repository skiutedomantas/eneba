function SearchBar() {
  return (
    <form
      aria-label="Marketplace search preview"
      className="flex min-w-0 border border-outline bg-card"
      role="search"
      onSubmit={(event) => event.preventDefault()}
    >
      <label className="sr-only" htmlFor="marketplace-search">
        Search games and digital products
      </label>
      <input
        className="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm text-text placeholder:text-muted"
        id="marketplace-search"
        placeholder="Search games, gift cards, and more"
        type="search"
      />
      <button
        className="shrink-0 border-l border-outline bg-section px-4 text-sm font-bold text-text hover:bg-card"
        type="submit"
      >
        Search
      </button>
    </form>
  )
}

export { SearchBar }
