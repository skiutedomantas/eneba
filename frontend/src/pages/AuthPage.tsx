import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { useAuth } from '../contexts/useAuth'
import { ApiError } from '../lib/api/client'

function AuthPage({ mode }: { mode: 'login' | 'register' }) {
  const { user, loading, login, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [displayName, setDisplayName] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const isRegister = mode === 'register'
  const requestedReturnTo = new URLSearchParams(location.search).get('returnTo')
  const returnTo = (
    requestedReturnTo === '/checkout'
    || requestedReturnTo === '/orders'
    || /^\/orders\/[1-9][0-9]*$/.test(requestedReturnTo ?? '')
  )
    ? requestedReturnTo ?? '/cart'
    : '/cart'

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      if (isRegister) {
        await register({ email, displayName, password })
      } else {
        await login({ email, password })
      }
      navigate(returnTo, { replace: true })
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : 'We could not complete your request. Please try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  if (!loading && user !== null) {
    return <Navigate replace to={returnTo} />
  }

  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-1 items-start justify-center px-4 py-10 md:px-8 md:py-16">
        <section className="w-full max-w-xl border border-outline bg-section p-5 md:p-8">
          <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
            GameHub account
          </p>
          <h1 className="mt-3 text-3xl font-black">
            {isRegister ? 'Create your account' : 'Sign in'}
          </h1>
          <p className="mt-2 text-sm text-muted">
            {isRegister
              ? 'Create an account to save your selected offers in a cart.'
              : returnTo === '/checkout'
                ? 'Sign in to continue to checkout. Your cart will be preserved.'
                : 'Sign in to access your account.'}
          </p>

          <form className="mt-7 space-y-5" onSubmit={handleSubmit}>
            {isRegister ? (
              <label className="block text-sm font-bold">
                Display name
                <input
                  autoComplete="name"
                  className="mt-2 block w-full border border-outline bg-card px-4 py-3 text-text outline-none focus:border-savings"
                  maxLength={80}
                  minLength={2}
                  onChange={(event) => setDisplayName(event.target.value)}
                  required
                  value={displayName}
                />
              </label>
            ) : null}
            <label className="block text-sm font-bold">
              Email
              <input
                autoComplete="email"
                className="mt-2 block w-full border border-outline bg-card px-4 py-3 text-text outline-none focus:border-savings"
                onChange={(event) => setEmail(event.target.value)}
                required
                type="email"
                value={email}
              />
            </label>
            <label className="block text-sm font-bold">
              Password
              <input
                autoComplete={isRegister ? 'new-password' : 'current-password'}
                className="mt-2 block w-full border border-outline bg-card px-4 py-3 text-text outline-none focus:border-savings"
                maxLength={72}
                minLength={12}
                onChange={(event) => setPassword(event.target.value)}
                required
                type="password"
                value={password}
              />
              {isRegister ? (
                <span className="mt-2 block text-xs font-normal text-muted">
                  Use at least 12 characters.
                </span>
              ) : null}
            </label>
            {error ? (
              <p className="border border-region bg-card p-3 text-sm text-text" role="alert">
                {error}
              </p>
            ) : null}
            <button
              className="w-full border border-savings bg-savings px-5 py-3 font-black text-background hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
              disabled={submitting || loading}
              type="submit"
            >
              {submitting
                ? 'Please wait...'
                : isRegister
                  ? 'Create account'
                  : 'Sign in'}
            </button>
          </form>

          <p className="mt-6 text-sm text-muted">
            {isRegister ? 'Already have an account?' : 'New to GameHub?'}{' '}
            <Link
              className="font-bold text-text underline decoration-savings underline-offset-4 hover:text-savings"
              to={{
                pathname: isRegister ? '/login' : '/register',
                search: requestedReturnTo ? `?returnTo=${encodeURIComponent(returnTo)}` : '',
              }}
            >
              {isRegister ? 'Sign in' : 'Create an account'}
            </Link>
          </p>
        </section>
      </main>
      <SiteFooter />
    </div>
  )
}

export { AuthPage }
