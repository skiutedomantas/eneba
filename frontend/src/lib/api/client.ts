export interface ApiListResponse<T> {
  data: T
  meta: Record<string, number | string>
}

export class ApiError extends Error {
  public readonly status: number
  public readonly code: string | null

  constructor(
    message: string,
    status: number,
    code: string | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

async function responseJson<T>(response: Response): Promise<T> {
  let payload: unknown
  try {
    payload = await response.json()
  } catch {
    throw new ApiError('The server returned an invalid response.', response.status)
  }

  if (!response.ok) {
    const message =
      typeof payload === 'object' &&
      payload !== null &&
      'error' in payload &&
      typeof payload.error === 'object' &&
      payload.error !== null &&
      'message' in payload.error &&
      typeof payload.error.message === 'string'
        ? payload.error.message
        : 'The request could not be completed.'
    const code =
      typeof payload === 'object' &&
      payload !== null &&
      'error' in payload &&
      typeof payload.error === 'object' &&
      payload.error !== null &&
      'code' in payload.error &&
      typeof payload.error.code === 'string'
        ? payload.error.code
        : null
    throw new ApiError(message, response.status, code)
  }

  return payload as T
}

async function getJson<T>(path: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(path, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  })

  return responseJson<T>(response)
}

async function sendJson<T>(
  path: string,
  method: 'POST' | 'PATCH' | 'DELETE',
  body: unknown,
  csrfToken: string,
): Promise<T> {
  const response = await fetch(path, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    ...(method === 'DELETE' ? {} : { body: JSON.stringify(body) }),
  })

  return responseJson<T>(response)
}

export { getJson, sendJson }
