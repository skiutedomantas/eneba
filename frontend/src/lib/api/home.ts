import type {
  CategoryOption,
  HeroBannerData,
  ProductCardData,
  SpecialDealData,
} from '../../types/catalog'
import { getJson, type ApiListResponse } from './client'

function getHeroBanners(signal?: AbortSignal) {
  return getJson<ApiListResponse<HeroBannerData[]>>(
    '/api/v1/home/hero-banners',
    signal,
  )
}

function getBestSellers(signal?: AbortSignal) {
  return getJson<ApiListResponse<ProductCardData[]>>(
    '/api/v1/home/best-sellers',
    signal,
  )
}

function getSpecialDeals(signal?: AbortSignal) {
  return getJson<ApiListResponse<SpecialDealData[]>>(
    '/api/v1/home/special-deals',
    signal,
  )
}

function getPopularCategories(signal?: AbortSignal) {
  return getJson<ApiListResponse<CategoryOption[]>>(
    '/api/v1/home/popular-categories',
    signal,
  )
}

export {
  getBestSellers,
  getHeroBanners,
  getPopularCategories,
  getSpecialDeals,
}
