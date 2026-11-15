import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { ResourceFeedback } from '../components/ui/ResourceFeedback'
import { useAuth } from '../contexts/useAuth'
import { useApiResource } from '../hooks/useApiResource'
import { getCart } from '../lib/api/cart'
import { getCsrfToken } from '../lib/api/auth'
import { checkout } from '../lib/api/orders'
import { ApiError } from '../lib/api/client'

function formatPrice(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
  }).format(amountMinor / 100)
}

function CheckoutPage() {
  const { user, loading: authLoading, refreshCart } = useAuth()
  const cartResource = useApiResource('checkout:cart', getCart, user !== null)
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [idempotencyKey] = useState(() => crypto.randomUUID())

  if (authLoading) {
    return (
      <div className="flex min-h-screen flex-col bg-background text-text">
        <SiteHeader />
        <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-10 md:px-8">
          <p role="status">Checking your account...</p>
        </main>
        <SiteFooter />
      </div>
    )
  }

  if (user === null) {
    return <Navigate replace to="/login?returnTo=%2Fcheckout" />
  }

  const cart = cartResource.data
  const currencies = new Set(cart?.items
    .filter((item) => item.offerAvailable && item.currency !== null)
    .map((item) => item.currency))
  const unavailableItems = cart?.items.some((item) => !item.offerAvailable) ?? false
  const canCheckout = Boolean(
    cart
    && cart.items.length > 0
    && !unavailableItems
    && currencies.size === 1,
  )

  async function submitCheckout(): Promise<void> {
    setSubmitting(true)
    setError(null)
    try {
      const csrfToken = await getCsrfToken()
      const order = await checkout(csrfToken, idempotencyKey)
      let notice: string | undefined
      try {
        await refreshCart()
      } catch {
        notice = 'Your order completed, but the cart count could not be refreshed. Reload the page to update it.'
      }
      navigate(`/orders/${order.orderId}`, {
        replace: true,
        state: notice ? { notice } : undefined,
      })
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : 'Checkout could not be completed. Your cart has been kept; please try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 md:px-8 md:py-10">
        <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
          Simulated checkout
        </p>
        <h1 className="mt-2 text-3xl font-black md:text-4xl">Review your order</h1>
        <p className="mt-3 max-w-3xl border border-outline bg-card p-4 text-sm text-muted">
          No payment provider is used and no real payment is collected. Successful purchases deliver fictional, non-redeemable keys.
        </p>

        <div className="mt-6">
          <ResourceFeedback
            empty={false}
            emptyMessage=""
            error={cartResource.error}
            loading={cartResource.loading}
            onRetry={cartResource.reload}
          />
        </div>

        {error ? (
          <p className="mt-4 border border-region bg-card p-4 text-sm" role="alert">
            {error} Your cart remains unchanged.
          </p>
        ) : null}

        {!cartResource.loading && !cartResource.error && cart ? (
          cart.items.length === 0 ? (
            <section className="mt-6 border border-outline bg-section p-6">
              <h2 className="text-xl font-black">Your cart is empty</h2>
              <Link className="mt-4 inline-block underline decoration-savings underline-offset-4" to="/store">
                Browse games
              </Link>
            </section>
          ) : (
            <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
              <section aria-label="Items for checkout" className="space-y-3">
                {cart.items.map((item) => (
                  <article className="border border-outline bg-card p-4" key={item.id}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h2 className="font-black">{item.product.name}</h2>
                        <p className="mt-1 text-sm text-muted">
                          {item.seller.name} / {item.platform.name} / {item.region.name}
                        </p>
                        {!item.offerAvailable ? (
                          <p className="mt-2 text-sm text-region">{item.unavailableReason}</p>
                        ) : null}
                      </div>
                      <p className="font-black">
                        {item.lineTotalMinor !== null && item.currency
                          ? formatPrice(item.lineTotalMinor, item.currency)
                          : 'Unavailable'}
                      </p>
                    </div>
                    <p className="mt-2 text-sm text-muted">Quantity: {item.quantity}</p>
                  </article>
                ))}
              </section>

              <aside className="border border-outline bg-section p-5">
                <h2 className="text-lg font-black">Order summary</h2>
                {cart.totals.map((total) => (
                  <p className="mt-4 flex justify-between gap-3 border-t border-outline pt-4" key={total.currency}>
                    <span>Simulated total</span>
                    <strong>{formatPrice(total.subtotalMinor, total.currency)}</strong>
                  </p>
                ))}
                {currencies.size > 1 ? (
                  <p className="mt-4 text-sm text-region">
                    All items must use one currency. Update your cart before checkout.
                  </p>
                ) : null}
                {unavailableItems ? (
                  <p className="mt-4 text-sm text-region">
                    Remove unavailable offers before checkout.
                  </p>
                ) : null}
                <button
                  className="mt-5 w-full border border-savings bg-savings px-4 py-3 font-black text-background hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                  disabled={!canCheckout || submitting}
                  onClick={() => void submitCheckout()}
                  type="button"
                >
                  {submitting ? 'Completing simulated purchase...' : 'Complete simulated purchase'}
                </button>
                <Link
                  className="mt-4 block text-center text-sm font-bold underline decoration-savings underline-offset-4"
                  to="/cart"
                >
                  Return to cart
                </Link>
              </aside>
            </div>
          )
        ) : null}
      </main>
      <SiteFooter />
    </div>
  )
}

export { CheckoutPage }
