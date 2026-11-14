import { Link } from 'react-router-dom'
import { SearchBar } from '../ui/SearchBar'

function SiteHeader() {
  return (
    <header className="border-b border-outline bg-section">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-4 px-4 py-4 md:px-8">
        <Link
          aria-label="GameHub home"
          className="shrink-0 text-2xl font-black tracking-tight text-text"
          to="/"
        >
          GAMEHUB
        </Link>
        <nav
          aria-label="Main navigation"
          className="order-3 flex w-full items-center gap-6 overflow-x-auto text-sm font-bold text-muted md:order-none md:w-auto"
        >
          <Link className="whitespace-nowrap hover:text-text" to="/store">
            Deals
          </Link>
          <Link className="whitespace-nowrap hover:text-text" to="/store">
            Games
          </Link>
          <a className="whitespace-nowrap hover:text-text" href="/#categories">
            Categories
          </a>
        </nav>
        <div className="w-full md:ml-auto md:max-w-xl">
          <SearchBar />
        </div>
        <button
          className="hidden border border-outline px-4 py-3 text-sm font-bold text-text hover:bg-card md:block"
          type="button"
        >
          Sign in
        </button>
      </div>
    </header>
  )
}

export { SiteHeader }
