import { createContext } from 'react'
import type { AuthUser } from '../lib/api/auth'

export interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  error: string | null
  cartItemCount: number
  login: (input: { email: string; password: string }) => Promise<void>
  register: (input: {
    email: string
    password: string
    displayName: string
  }) => Promise<void>
  logout: () => Promise<void>
  addToCart: (offerId: number) => Promise<void>
  updateCartQuantity: (itemId: number, quantity: number) => Promise<void>
  removeFromCart: (itemId: number) => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
