import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/useAuth'
import { ApiError } from '../../lib/api/client'
import { SearchBar } from '../ui/SearchBar'

function SiteHeader() {
  const { user, loading, cartItemCount, logout, error } = useAuth()
  const navigate = useNavigate()
  const [logoutError, setLogoutError] = useState<string | null>(null)

  async function signOut(): Promise<void> {
    setLogoutError(null)
    try {
      await logout()
      navigate('/', { replace: true })
    } catch (cause) {
      setLogoutError(
        cause instanceof ApiError
          ? cause.message
          : 'Sign out could not be completed. Please try again.',
      )
    }
  }

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
        <div className="flex items-center gap-3 text-sm font-bold">
          {!loading ? (
            <>
              <Link
                className="border border-outline px-3 py-2 hover:bg-card"
                to="/cart"
              >
                Cart ({cartItemCount})
              </Link>
              {user ? (
                <>
                  <Link
                    className="border border-outline px-3 py-2 hover:bg-card"
                    to="/orders"
                  >
                    Orders
                  </Link>
                  <span className="hidden max-w-36 truncate text-muted lg:block">
                    {user.displayName}
                  </span>
                  <button
                    className="border border-outline px-3 py-2 hover:bg-card"
                    onClick={() => void signOut()}
                    type="button"
                  >
                    Sign out
                  </button>
                </>
              ) : (
                <Link
                  className="border border-outline px-4 py-3 text-sm font-bold text-text hover:bg-card"
                  to="/login"
                >
                  Sign in
                </Link>
              )}
            </>
          ) : null}
        </div>
      </div>
      {error || logoutError ? (
        <p
          className="mx-auto max-w-7xl px-4 pb-3 text-sm text-region md:px-8"
          role="status"
        >
          {logoutError ?? error}
        </p>
      ) : null}
    </header>
  )
}

export { SiteHeader }
