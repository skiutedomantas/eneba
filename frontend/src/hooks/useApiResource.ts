import { useEffect, useState } from 'react'
import { ApiError } from '../lib/api/client'

interface ApiResourceState<T> {
  data: T | null
  error: string | null
  loading: boolean
  reload: () => void
}

function useApiResource<T>(
  resourceKey: string,
  request: (signal: AbortSignal) => Promise<T>,
  enabled = true,
): ApiResourceState<T> {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [reloadCount, setReloadCount] = useState(0)

  useEffect(() => {
    if (!enabled) {
      setData(null)
      setError(null)
      setLoading(false)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    setError(null)
    setData(null)

    request(controller.signal)
      .then((response) => {
        if (!controller.signal.aborted) {
          setData(response)
        }
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted) {
          return
        }
        setError(
          cause instanceof ApiError
            ? cause.message
            : 'Could not load this section. Please try again.',
        )
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false)
        }
      })

    return () => controller.abort()
    // The resource key changes whenever its request parameters change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, resourceKey, reloadCount])

  return {
    data,
    error,
    loading,
    reload: () => setReloadCount((current) => current + 1),
  }
}

export { useApiResource }
