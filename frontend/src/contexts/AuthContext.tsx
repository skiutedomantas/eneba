import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import {
  getCsrfToken,
  getCurrentUser,
  login as requestLogin,
  logout as requestLogout,
  register as requestRegister,
  type AuthUser,
} from '../lib/api/auth'
import { ApiError } from '../lib/api/client'
import {
  addCartItem,
  getCart,
  removeCartItem as requestRemoveCartItem,
  updateCartItem as requestUpdateCartItem,
} from '../lib/api/cart'
import { AuthContext } from './auth-context'

interface InitialAuthState {
  csrfToken: string
  user: AuthUser | null
  cartItemCount: number
}

function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [csrfToken, setCsrfToken] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [cartItemCount, setCartItemCount] = useState(0)
  const initialization = useRef<Promise<InitialAuthState> | null>(null)

  useEffect(() => {
    let active = true

    if (initialization.current === null) {
      initialization.current = (async () => {
        const token = await getCsrfToken()
        let currentUser: AuthUser | null = null
        try {
          currentUser = await getCurrentUser()
        } catch (cause) {
          if (!(cause instanceof ApiError && cause.status === 401)) {
            throw cause
          }
        }
        const cart = await getCart()
        return {
          csrfToken: token,
          user: currentUser,
          cartItemCount: cart.itemCount,
        }
      })()
    }

    initialization.current
      .then((state) => {
        if (!active) return
        setCsrfToken(state.csrfToken)
        setUser(state.user)
        setCartItemCount(state.cartItemCount)
      })
      .catch((cause: unknown) => {
        if (active) {
          setError(
            cause instanceof Error
              ? cause.message
              : 'Your account and cart could not be loaded. Please refresh the page.',
          )
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const login = useCallback(
    async (input: { email: string; password: string }) => {
      setError(null)
      const token = csrfToken ?? (await getCsrfToken())
      const result = await requestLogin(input, token)
      setCsrfToken(result.csrfToken)
      setUser(result.user)
      try {
        const cart = await getCart()
        setCartItemCount(cart.itemCount)
      } catch (cause) {
        setError(
          cause instanceof Error
            ? `Signed in, but the cart could not be loaded: ${cause.message}`
            : 'Signed in, but the cart could not be loaded.',
        )
      }
    },
    [csrfToken],
  )

  const register = useCallback(
    async (input: { email: string; password: string; displayName: string }) => {
      setError(null)
      const token = csrfToken ?? (await getCsrfToken())
      const result = await requestRegister(input, token)
      setCsrfToken(result.csrfToken)
      setUser(result.user)
      try {
        const cart = await getCart()
        setCartItemCount(cart.itemCount)
      } catch (cause) {
        setError(
          cause instanceof Error
            ? `Account created, but the cart could not be loaded: ${cause.message}`
            : 'Account created, but the cart could not be loaded.',
        )
      }
    },
    [csrfToken],
  )

  const logout = useCallback(async () => {
    const token = csrfToken ?? (await getCsrfToken())
    await requestLogout(token)
    setUser(null)
    setCsrfToken(null)
    setCartItemCount(0)
  }, [csrfToken])

  const refreshCart = useCallback(async () => {
    const cart = await getCart()
    setCartItemCount(cart.itemCount)
  }, [])

  const addToCart = useCallback(
    async (offerId: number) => {
      const token = csrfToken ?? (await getCsrfToken())
      const cart = await addCartItem(offerId, 1, token)
      setCartItemCount(cart.itemCount)
    },
    [csrfToken],
  )

  const updateCartQuantity = useCallback(
    async (itemId: number, quantity: number) => {
      const token = csrfToken ?? (await getCsrfToken())
      const cart = await requestUpdateCartItem(itemId, quantity, token)
      setCartItemCount(cart.itemCount)
    },
    [csrfToken],
  )

  const removeFromCart = useCallback(
    async (itemId: number) => {
      const token = csrfToken ?? (await getCsrfToken())
      const cart = await requestRemoveCartItem(itemId, token)
      setCartItemCount(cart.itemCount)
    },
    [csrfToken],
  )

  const value = useMemo(
    () => ({
      user,
      loading,
      error,
      cartItemCount,
      refreshCart,
      login,
      register,
      logout,
      addToCart,
      updateCartQuantity,
      removeFromCart,
    }),
    [
      user,
      loading,
      error,
      cartItemCount,
      refreshCart,
      login,
      register,
      logout,
      addToCart,
      updateCartQuantity,
      removeFromCart,
    ],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export { AuthProvider }
