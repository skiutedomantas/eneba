import { useState } from 'react'
import { Link, Navigate, useLocation, useParams } from 'react-router-dom'
import { SiteFooter } from '../components/layout/SiteFooter'
import { SiteHeader } from '../components/layout/SiteHeader'
import { ResourceFeedback } from '../components/ui/ResourceFeedback'
import { useAuth } from '../contexts/useAuth'
import { useApiResource } from '../hooks/useApiResource'
import { ApiError } from '../lib/api/client'
import { getOrder, getOrderKeys, getOrders, type OrderKeys } from '../lib/api/orders'

function formatPrice(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
  }).format(amountMinor / 100)
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString()
}

function OrderHistoryPage() {
  const { user, loading: authLoading } = useAuth()
  const [page, setPage] = useState(1)
  const ordersResource = useApiResource(
    `orders:list:${page}`,
    (signal) => getOrders(page, signal),
    user !== null,
  )

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
  if (user === null) return <Navigate replace to="/login?returnTo=%2Forders" />

  const orders = ordersResource.data?.data ?? []
  const totalPages = Math.max(
    1,
    Math.ceil((ordersResource.data?.meta.total ?? 0) / (ordersResource.data?.meta.perPage ?? 20)),
  )

  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 md:px-8 md:py-10">
        <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
          Account
        </p>
        <h1 className="mt-2 text-3xl font-black md:text-4xl">Order history</h1>
        <div className="mt-6">
          <ResourceFeedback
            empty={false}
            emptyMessage=""
            error={ordersResource.error}
            loading={ordersResource.loading}
            onRetry={ordersResource.reload}
          />
        </div>
        {!ordersResource.loading && !ordersResource.error ? (
          orders.length === 0 ? (
            <section className="mt-6 border border-outline bg-section p-6">
              <h2 className="text-xl font-black">No orders yet</h2>
              <p className="mt-2 text-sm text-muted">
                Completed simulated purchases will appear here.
              </p>
              <Link className="mt-4 inline-block underline decoration-savings underline-offset-4" to="/store">
                Browse games
              </Link>
            </section>
          ) : (
            <section aria-label="Orders" className="mt-6 space-y-3">
              {orders.map((order) => (
                <article className="border border-outline bg-card p-4 md:p-5" key={order.id}>
                  <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                      <h2 className="font-black">Order #{order.id}</h2>
                      <p className="mt-1 text-sm text-muted">
                        {formatDate(order.createdAt)} / {order.itemCount} item(s) / {order.status}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="font-black">{formatPrice(order.totalMinor, order.currency)}</p>
                      <Link
                        className="mt-1 inline-block text-sm font-bold underline decoration-savings underline-offset-4"
                        to={`/orders/${order.id}`}
                      >
                        View order
                      </Link>
                    </div>
                  </div>
                </article>
              ))}
            </section>
          )
        ) : null}
        {totalPages > 1 ? (
          <nav aria-label="Order history pages" className="mt-6 flex items-center justify-between">
            <button
              className="border border-outline px-4 py-2 font-bold hover:bg-card disabled:opacity-50"
              disabled={page <= 1 || ordersResource.loading}
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              type="button"
            >
              Previous
            </button>
            <span className="text-sm text-muted">Page {page} of {totalPages}</span>
            <button
              className="border border-outline px-4 py-2 font-bold hover:bg-card disabled:opacity-50"
              disabled={page >= totalPages || ordersResource.loading}
              onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
              type="button"
            >
              Next
            </button>
          </nav>
        ) : null}
      </main>
      <SiteFooter />
    </div>
  )
}

function OrderDetailPage() {
  const { user, loading: authLoading } = useAuth()
  const { orderId = '' } = useParams()
  const location = useLocation()
  const isValidId = /^[1-9][0-9]*$/.test(orderId)
  const orderResource = useApiResource(
    `orders:detail:${orderId}`,
    (signal) => getOrder(orderId, signal),
    user !== null && isValidId,
  )
  const [deliveredKeys, setDeliveredKeys] = useState<{
    orderId: string
    data: OrderKeys
  } | null>(null)
  const [deliveryError, setDeliveryError] = useState<{
    orderId: string
    message: string
  } | null>(null)
  const [revealingOrderId, setRevealingOrderId] = useState<string | null>(null)

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
    const returnTo = isValidId ? `/orders/${orderId}` : '/orders'
    return <Navigate replace to={`/login?returnTo=${encodeURIComponent(returnTo)}`} />
  }

  async function revealKeys(): Promise<void> {
    setRevealingOrderId(orderId)
    setDeliveryError(null)
    try {
      setDeliveredKeys({ orderId, data: await getOrderKeys(orderId) })
    } catch (cause) {
      setDeliveryError({
        orderId,
        message: cause instanceof ApiError
          ? cause.message
          : 'Keys could not be retrieved. Please try again.',
      })
    } finally {
      setRevealingOrderId(null)
    }
  }

  const order = orderResource.data
  const notice = (location.state as { notice?: unknown } | null)?.notice
  const currentKeys = deliveredKeys?.orderId === orderId ? deliveredKeys.data : null
  const currentDeliveryError = deliveryError?.orderId === orderId
    ? deliveryError.message
    : null

  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 md:px-8 md:py-10">
        <Link className="text-sm font-bold underline decoration-savings underline-offset-4" to="/orders">
          Back to order history
        </Link>
        <div className="mt-5">
          <ResourceFeedback
            empty={false}
            emptyMessage=""
            error={isValidId ? orderResource.error : 'The order ID is invalid.'}
            loading={isValidId && orderResource.loading}
            onRetry={orderResource.reload}
          />
        </div>
        {typeof notice === 'string' ? (
          <p className="mt-4 border border-outline bg-card p-3 text-sm" role="status">
            {notice}
          </p>
        ) : null}

        {!orderResource.loading && !orderResource.error && order ? (
          <>
            <section className="mt-6 border border-outline bg-section p-5 md:p-7">
              <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
                Simulated order
              </p>
              <h1 className="mt-2 text-3xl font-black">Order #{order.id}</h1>
              <p className="mt-2 text-sm text-muted">
                {formatDate(order.createdAt)} / {order.status}
              </p>
              <div className="mt-6 space-y-3">
                {order.items.map((item) => (
                  <article className="border border-outline bg-card p-4" key={item.id}>
                    <div className="flex flex-wrap justify-between gap-3">
                      <div>
                        <h2 className="font-black">{item.productName}</h2>
                        <p className="mt-1 text-sm text-muted">
                          {item.sellerName} / {item.platformName} / {item.regionName}
                        </p>
                        <p className="mt-1 text-sm text-muted">Quantity: {item.quantity}</p>
                      </div>
                      <p className="font-black">
                        {formatPrice(item.unitPriceMinor * item.quantity, item.currency)}
                      </p>
                    </div>
                  </article>
                ))}
              </div>
              <p className="mt-5 border-t border-outline pt-4 text-right text-xl font-black">
                Total: {formatPrice(order.totalMinor, order.currency)}
              </p>
            </section>

            {order.status === 'paid' ? (
              <section className="mt-6 border border-outline bg-section p-5 md:p-7">
                <h2 className="text-xl font-black">Digital key delivery</h2>
                <p className="mt-2 text-sm text-muted">
                  These keys are fictional and cannot be redeemed. No real payment was taken.
                </p>
                {currentDeliveryError ? (
                  <p className="mt-4 border border-region bg-card p-3 text-sm" role="alert">
                    {currentDeliveryError}
                  </p>
                ) : null}
                {!currentKeys ? (
                  <button
                    className="mt-5 border border-savings bg-savings px-5 py-3 font-black text-background hover:brightness-110 disabled:opacity-60"
                    disabled={revealingOrderId === orderId}
                    onClick={() => void revealKeys()}
                    type="button"
                  >
                    {revealingOrderId === orderId ? 'Retrieving keys...' : 'Reveal fictional keys'}
                  </button>
                ) : (
                  <div className="mt-5 space-y-4">
                    {currentKeys.items.map((item) => (
                      <div className="border border-outline bg-card p-4" key={item.orderItemId}>
                        <h3 className="font-bold">{item.productName}</h3>
                        <ul className="mt-3 space-y-2">
                          {item.keys.map((key, index) => (
                            <li className="break-all border border-outline p-3 font-mono text-sm" key={`${item.orderItemId}-${index}`}>
                              {key}
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                )}
              </section>
            ) : null}
          </>
        ) : null}
      </main>
      <SiteFooter />
    </div>
  )
}

export { OrderDetailPage, OrderHistoryPage }
