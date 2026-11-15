import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { ResourceFeedback } from '../components/ui/ResourceFeedback'
import { useAuth } from '../contexts/useAuth'
import { useApiResource } from '../hooks/useApiResource'
import { getCart, type CartData, type CartItem } from '../lib/api/cart'
import { ApiError } from '../lib/api/client'

function formatPrice(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
  }).format(amountMinor / 100)
}

interface CartRowProps {
  item: CartItem
  busy: boolean
  onUpdate: (itemId: number, quantity: number) => void
  onRemove: (itemId: number) => void
}

function CartRow({ item, busy, onUpdate, onRemove }: CartRowProps) {
  const [quantity, setQuantity] = useState(item.quantity)

  function submitUpdate(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault()
    onUpdate(item.id, quantity)
  }

  return (
    <article className="border border-outline bg-card p-4 md:p-5">
      <div className="flex flex-col justify-between gap-5 md:flex-row md:items-start">
        <div className="min-w-0">
          <Link
            className="text-lg font-black underline decoration-savings underline-offset-4 hover:text-savings"
            to={`/products/${item.product.slug}`}
          >
            {item.product.name}
          </Link>
          <p className="mt-2 text-sm text-muted">
            {item.seller.name} / {item.platform.name} /{' '}
            <span className="font-bold text-region">{item.region.name}</span>
          </p>
          {!item.offerAvailable ? (
            <p className="mt-3 border border-region px-3 py-2 text-sm" role="status">
              {item.unavailableReason}
            </p>
          ) : null}
          <p className="mt-3 text-sm text-muted">
            {item.offerAvailable && item.unitPriceMinor !== null && item.currency
              ? `${formatPrice(item.unitPriceMinor, item.currency)} each`
              : 'Price unavailable'}
          </p>
        </div>
        <div className="flex flex-wrap items-end gap-3">
          {item.offerAvailable ? (
            <form className="flex items-end gap-2" onSubmit={submitUpdate}>
              <label className="text-xs font-bold uppercase tracking-wide text-muted">
                Quantity
                <input
                  className="mt-1 block w-24 border border-outline bg-section px-3 py-2 text-text outline-none focus:border-savings"
                  max={99}
                  min={1}
                  onChange={(event) => setQuantity(Number(event.target.value))}
                  required
                  type="number"
                  value={quantity}
                />
              </label>
              <button
                className="border border-outline px-3 py-2 text-sm font-bold hover:bg-section disabled:opacity-60"
                disabled={busy || quantity === item.quantity}
                type="submit"
              >
                Update
              </button>
            </form>
          ) : null}
          <button
            className="border border-outline px-3 py-2 text-sm font-bold text-text hover:border-region hover:text-region disabled:opacity-60"
            disabled={busy}
            onClick={() => onRemove(item.id)}
            type="button"
          >
            Remove
          </button>
          <p className="min-w-28 text-right text-lg font-black">
            {item.lineTotalMinor !== null && item.currency
              ? formatPrice(item.lineTotalMinor, item.currency)
              : '--'}
          </p>
        </div>
      </div>
    </article>
  )
}

function CartPage() {
  const { user, updateCartQuantity, removeFromCart } = useAuth()
  const cartResource = useApiResource<CartData>(
    'cart',
    () => getCart(),
  )
  const [busyItemId, setBusyItemId] = useState<number | null>(null)
  const [mutationError, setMutationError] = useState<string | null>(null)

  async function updateItem(itemId: number, quantity: number): Promise<void> {
    setBusyItemId(itemId)
    setMutationError(null)
    try {
      await updateCartQuantity(itemId, quantity)
      cartResource.reload()
    } catch (cause) {
      setMutationError(
        cause instanceof ApiError
          ? cause.message
          : 'Your cart could not be updated. Please try again.',
      )
    } finally {
      setBusyItemId(null)
    }
  }

  async function removeItem(itemId: number): Promise<void> {
    setBusyItemId(itemId)
    setMutationError(null)
    try {
      await removeFromCart(itemId)
      cartResource.reload()
    } catch (cause) {
      setMutationError(
        cause instanceof ApiError
          ? cause.message
          : 'The cart item could not be removed. Please try again.',
      )
    } finally {
      setBusyItemId(null)
    }
  }

  const cart = cartResource.data
  const cartCurrencies = new Set(cart?.items
    .filter((item) => item.offerAvailable && item.currency !== null)
    .map((item) => item.currency))
  const hasUnavailableOffers = cart?.items.some((item) => !item.offerAvailable) ?? false
  const canProceedToCheckout = Boolean(
    cart
    && cart.items.length > 0
    && !hasUnavailableOffers
    && cartCurrencies.size === 1,
  )

  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 md:px-8 md:py-10">
        <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
          Your selections
        </p>
        <h1 className="mt-2 text-3xl font-black md:text-4xl">Shopping cart</h1>
        <div className="mt-6">
          <ResourceFeedback
            empty={false}
            emptyMessage=""
            error={cartResource.error}
            loading={cartResource.loading}
            onRetry={cartResource.reload}
          />
        </div>

        {mutationError ? (
          <p className="mt-4 border border-region bg-card p-3 text-sm" role="alert">
            {mutationError}
          </p>
        ) : null}

        {!cartResource.loading && !cartResource.error && cart ? (
          cart.items.length === 0 ? (
            <section className="mt-6 border border-outline bg-section p-6">
              <h2 className="text-xl font-black">Your cart is empty</h2>
              <p className="mt-2 text-sm text-muted">
                Browse the store and add a seller offer to get started.
              </p>
              <Link
                className="mt-5 inline-block border border-savings bg-savings px-5 py-3 font-black text-background hover:brightness-110"
                to="/store"
              >
                Browse games
              </Link>
            </section>
          ) : (
            <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
              <section aria-label="Cart items" className="space-y-3">
                {cart.items.map((item) => (
                  <CartRow
                    busy={busyItemId === item.id}
                    item={item}
                    key={item.id}
                    onRemove={(id) => void removeItem(id)}
                    onUpdate={(id, quantity) => void updateItem(id, quantity)}
                  />
                ))}
              </section>
              <aside className="border border-outline bg-section p-5">
                <h2 className="text-lg font-black">Order summary</h2>
                <p className="mt-2 text-sm text-muted">
                  Prices are refreshed from the selected seller offers.
                </p>
                {!user ? (
                  <p className="mt-3 border border-outline px-3 py-2 text-sm text-muted">
                    You are shopping as a guest. Your cart will merge into your account when you sign in; checkout will require sign-in.
                  </p>
                ) : null}
                <dl className="mt-5 space-y-3 border-t border-outline pt-4">
                  {cart.totals.map((total) => (
                    <div
                      className="flex items-center justify-between gap-3"
                      key={total.currency}
                    >
                      <dt className="font-bold">Subtotal</dt>
                      <dd className="text-lg font-black">
                        {formatPrice(total.subtotalMinor, total.currency)}
                      </dd>
                    </div>
                  ))}
                </dl>
                {cart.items.some((item) => !item.offerAvailable) ? (
                  <p className="mt-4 text-sm text-region">
                    Remove unavailable offers to keep your cart up to date.
                  </p>
                ) : null}
                {cartCurrencies.size > 1 ? (
                  <p className="mt-4 text-sm text-region">
                    Checkout requires items in one currency. Adjust your cart to continue.
                  </p>
                ) : null}
                {canProceedToCheckout ? (
                  <Link
                    className="mt-5 block border border-savings bg-savings px-4 py-3 text-center font-black text-background hover:brightness-110"
                    to="/checkout"
                  >
                    Continue to checkout
                  </Link>
                ) : null}
              </aside>
            </div>
          )
        ) : null}
      </main>
      <SiteFooter />
    </div>
  )
}

export { CartPage }
