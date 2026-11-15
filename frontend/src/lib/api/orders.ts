import { getJson, sendJson } from './client'

export interface OrderSummary {
  id: number
  status: string
  currency: string
  subtotalMinor: number
  totalMinor: number
  itemCount: number
  createdAt: string
  paidAt: string | null
}

export interface OrderItem {
  id: number
  offerId: number
  productName: string
  sellerName: string
  platformName: string
  regionName: string
  quantity: number
  unitPriceMinor: number
  currency: string
}

export interface OrderDetail extends Omit<OrderSummary, 'itemCount'> {
  items: OrderItem[]
}

export interface OrderKeys {
  orderId: number
  items: Array<{
    orderItemId: number
    productName: string
    keys: string[]
  }>
}

interface OrderListResponse {
  data: OrderSummary[]
  meta: { page: number; perPage: number; total: number }
}

interface OrderDetailResponse {
  data: OrderDetail
}

interface OrderKeysResponse {
  data: OrderKeys
}

interface CheckoutResponse {
  data: {
    orderId: number
    status: string
    currency: string
    subtotalMinor: number
    totalMinor: number
  }
}

async function checkout(csrfToken: string, idempotencyKey: string): Promise<CheckoutResponse['data']> {
  const response = await sendJson<CheckoutResponse>(
    '/api/v1/checkout',
    'POST',
    {},
    csrfToken,
    { 'Idempotency-Key': idempotencyKey },
  )
  return response.data
}

async function getOrders(page = 1, signal?: AbortSignal): Promise<OrderListResponse> {
  return getJson<OrderListResponse>(`/api/v1/orders?page=${page}`, signal)
}

async function getOrder(orderId: string, signal?: AbortSignal): Promise<OrderDetail> {
  const response = await getJson<OrderDetailResponse>(`/api/v1/orders/${orderId}`, signal)
  return response.data
}

async function getOrderKeys(orderId: string): Promise<OrderKeys> {
  const response = await getJson<OrderKeysResponse>(`/api/v1/orders/${orderId}/keys`)
  return response.data
}

export { checkout, getOrder, getOrderKeys, getOrders }
