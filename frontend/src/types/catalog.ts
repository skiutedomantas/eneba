export interface PriceOffer {
  offerId: number
  priceMinor: number
  compareAtPriceMinor: number | null
  currency: string
  platform: {
    code: string
    name: string
  }
  region: {
    code: string
    name: string
  }
  availableQuantity: number
  discountPercent: number | null
}

export interface ProductCardData {
  id: number
  slug: string
  name: string
  description: string
  imageUrl: string | null
  lowestOffer: PriceOffer | null
  unitsSold?: number
}

export interface HeroBannerData {
  id: number
  title: string
  imageUrl: string
  targetUrl: string
  sortOrder: number
}

export interface SpecialDealData {
  offerId: number
  product: Pick<ProductCardData, 'id' | 'slug' | 'name' | 'imageUrl'>
  priceMinor: number
  compareAtPriceMinor: number
  currency: string
  platform: PriceOffer['platform']
  region: PriceOffer['region']
  availableQuantity: number
  discountPercent: number
}

export interface CategoryOption {
  id: number
  slug: string
  name: string
}

export interface CodedOption {
  id: number
  code: string
  name: string
}

export interface ProductListResponse {
  data: ProductCardData[]
  meta: {
    page: number
    perPage: number
    total: number
    sort: string
    currency: string
    q?: string
    candidateLimit?: number
    capped?: boolean
  }
}

export interface ProductDetailOffer {
  offerId: number
  seller: {
    id: number
    name: string
  }
  priceMinor: number
  compareAtPriceMinor: number | null
  currency: string
  platform: PriceOffer['platform']
  region: PriceOffer['region']
  availableQuantity: number
  discountPercent: number | null
}

export interface ProductDetailResponse {
  data: {
    product: Pick<
      ProductCardData,
      'id' | 'slug' | 'name' | 'description' | 'imageUrl'
    >
    offers: ProductDetailOffer[]
  }
}
