import { getJson, sendJson } from './client'

export interface AuthUser {
  id: number
  email: string
  displayName: string
}

interface AuthResponse {
  data: {
    user: AuthUser
    csrfToken: string
  }
}

interface CsrfResponse {
  data: { csrfToken: string }
}

interface CurrentUserResponse {
  data: { user: AuthUser }
}

async function getCsrfToken(): Promise<string> {
  const response = await getJson<CsrfResponse>('/api/v1/auth/csrf')
  return response.data.csrfToken
}

async function getCurrentUser(): Promise<AuthUser> {
  const response = await getJson<CurrentUserResponse>('/api/v1/auth/me')
  return response.data.user
}

async function login(
  input: { email: string; password: string },
  csrfToken: string,
): Promise<AuthResponse['data']> {
  const response = await sendJson<AuthResponse>(
    '/api/v1/auth/login',
    'POST',
    input,
    csrfToken,
  )
  return response.data
}

async function register(
  input: { email: string; password: string; displayName: string },
  csrfToken: string,
): Promise<AuthResponse['data']> {
  const response = await sendJson<AuthResponse>(
    '/api/v1/auth/register',
    'POST',
    input,
    csrfToken,
  )
  return response.data
}

async function logout(csrfToken: string): Promise<void> {
  await sendJson('/api/v1/auth/logout', 'POST', {}, csrfToken)
}

export { getCsrfToken, getCurrentUser, login, logout, register }
