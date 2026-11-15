import { getJson, sendJson } from './client'

export interface CartItem {
  id: number
  offerId: number
  product: { slug: string; name: string }
  seller: { name: string }
  platform: { name: string }
  region: { name: string }
  quantity: number
  offerAvailable: boolean
  unavailableReason: string | null
  unitPriceMinor: number | null
  lineTotalMinor: number | null
  currency: string | null
}

export interface CartData {
  items: CartItem[]
  totals: Array<{ currency: string; subtotalMinor: number }>
  itemCount: number
}

interface CartResponse {
  data: CartData
}

async function getCart(): Promise<CartData> {
  const response = await getJson<CartResponse>('/api/v1/cart')
  return response.data
}

async function addCartItem(
  offerId: number,
  quantity: number,
  csrfToken: string,
): Promise<CartData> {
  const response = await sendJson<CartResponse>(
    '/api/v1/cart/items',
    'POST',
    { offerId, quantity },
    csrfToken,
  )
  return response.data
}

async function updateCartItem(
  itemId: number,
  quantity: number,
  csrfToken: string,
): Promise<CartData> {
  const response = await sendJson<CartResponse>(
    `/api/v1/cart/items/${itemId}`,
    'PATCH',
    { quantity },
    csrfToken,
  )
  return response.data
}

async function removeCartItem(itemId: number, csrfToken: string): Promise<CartData> {
  const response = await sendJson<CartResponse>(
    `/api/v1/cart/items/${itemId}`,
    'DELETE',
    {},
    csrfToken,
  )
  return response.data
}

export { getCart, addCartItem, updateCartItem, removeCartItem }
