import type {
  CategoryOption,
  CodedOption,
  ProductDetailResponse,
  ProductListResponse,
} from '../../types/catalog'
import { getJson, type ApiListResponse } from './client'

export interface CatalogQuery {
  page: number
  perPage: number
  platform: string
  region: string
  category: string
  currency: string
  minPriceMinor: string
  maxPriceMinor: string
  sort: string
}

export interface SearchQuery extends CatalogQuery {
  q: string
}

function getProducts(query: CatalogQuery, signal?: AbortSignal) {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== '' && value !== 'all') {
      params.set(key, value)
    }
  }

  return getJson<ProductListResponse>(
    `/api/v1/products?${params.toString()}`,
    signal,
  )
}

function getSearchResults(query: SearchQuery, signal?: AbortSignal) {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== '' && value !== 'all') {
      params.set(key, value)
    }
  }

  return getJson<ProductListResponse>(
    `/api/v1/search?${params.toString()}`,
    signal,
  )
}

function getProductDetail(slug: string, signal?: AbortSignal) {
  return getJson<ProductDetailResponse>(
    `/api/v1/products/${encodeURIComponent(slug)}`,
    signal,
  )
}

function getCategories(signal?: AbortSignal) {
  return getJson<ApiListResponse<CategoryOption[]>>(
    '/api/v1/categories',
    signal,
  )
}

function getPlatforms(signal?: AbortSignal) {
  return getJson<ApiListResponse<CodedOption[]>>('/api/v1/platforms', signal)
}

function getRegions(signal?: AbortSignal) {
  return getJson<ApiListResponse<CodedOption[]>>('/api/v1/regions', signal)
}

export {
  getCategories,
  getPlatforms,
  getProductDetail,
  getProducts,
  getRegions,
  getSearchResults,
}
