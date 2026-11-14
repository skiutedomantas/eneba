function SiteFooter() {
  return (
    <footer
      aria-label="GameHub footer"
      className="mt-12 border-t border-outline bg-card"
    >
      <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 md:flex-row md:items-center md:justify-between md:px-8">
        <div>
          <p className="font-black tracking-wide text-text">GAMEHUB</p>
          <p className="mt-2 text-sm text-muted">
            A bold new home for digital games.
          </p>
        </div>
        <nav aria-label="Footer navigation" className="flex flex-wrap gap-5 text-sm text-muted">
          <a className="hover:text-text" href="#featured">
            Featured
          </a>
          <a className="hover:text-text" href="#categories">
            Categories
          </a>
          <a className="hover:text-text" href="/api/v1/health">
            API status
          </a>
        </nav>
      </div>
    </footer>
  )
}

export { SiteFooter }
