import { Link } from 'react-router-dom'
import { SiteFooter } from '../../components/layout/SiteFooter'
import { SiteHeader } from '../../components/layout/SiteHeader'
import { ProductCarousel } from '../../components/ui/ProductCarousel'
import { PromoBanner } from '../../components/ui/PromoBanner'
import { ResourceFeedback } from '../../components/ui/ResourceFeedback'
import { SectionPanel } from '../../components/ui/SectionPanel'
import { useApiResource } from '../../hooks/useApiResource'
import {
  getBestSellers,
  getHeroBanners,
  getPopularCategories,
  getSpecialDeals,
} from '../../lib/api/home'
import type { ProductCardData } from '../../types/catalog'

function HomePage() {
  const banners = useApiResource('home:hero-banners', getHeroBanners)
  const bestSellers = useApiResource('home:best-sellers', getBestSellers)
  const specialDeals = useApiResource('home:special-deals', getSpecialDeals)
  const popularCategories = useApiResource(
    'home:popular-categories',
    getPopularCategories,
  )

  const dealProducts: ProductCardData[] =
    specialDeals.data?.data.map((deal) => ({
      id: deal.product.id,
      slug: deal.product.slug,
      name: deal.product.name,
      description: '',
      imageUrl: deal.product.imageUrl,
      lowestOffer: {
        offerId: deal.offerId,
        priceMinor: deal.priceMinor,
        compareAtPriceMinor: deal.compareAtPriceMinor,
        currency: deal.currency,
        platform: deal.platform,
        region: deal.region,
        availableQuantity: deal.availableQuantity,
        discountPercent: deal.discountPercent,
      },
    })) ?? []

  return (
    <div className="min-h-screen bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <div>
          <ResourceFeedback
            empty={banners.data !== null && banners.data.data.length === 0}
            emptyMessage="Promotional banners will appear here when available."
            error={banners.error}
            loading={banners.loading}
            onRetry={banners.reload}
          />
          {banners.data?.data.map((banner) => (
            <PromoBanner
              imageUrl={banner.imageUrl}
              key={banner.id}
              targetUrl={banner.targetUrl}
              title={banner.title}
            />
          ))}
        </div>

        <SectionPanel
          description="Products ranked by recent sales activity."
          eyebrow="Popular with players"
          id="best-sellers"
          title="Best sellers"
        >
          <ResourceFeedback
            empty={
              bestSellers.data !== null &&
              bestSellers.data.data.length === 0
            }
            emptyMessage="No recent best sellers are available."
            error={bestSellers.error}
            loading={bestSellers.loading}
            onRetry={bestSellers.reload}
          />
          {bestSellers.data && bestSellers.data.data.length > 0 ? (
            <ProductCarousel
              id="best-sellers-carousel"
              label="Best sellers"
              products={bestSellers.data.data}
            />
          ) : null}
        </SectionPanel>

        <SectionPanel
          description="Current discounted seller offers, ordered by savings."
          eyebrow="Limited-time value"
          id="special-deals"
          title="Special deals"
        >
          <ResourceFeedback
            empty={
              specialDeals.data !== null &&
              specialDeals.data.data.length === 0
            }
            emptyMessage="No discounted offers are available."
            error={specialDeals.error}
            loading={specialDeals.loading}
            onRetry={specialDeals.reload}
          />
          {specialDeals.data && dealProducts.length > 0 ? (
            <ProductCarousel
              id="special-deals-carousel"
              label="Special deals"
              products={dealProducts}
            />
          ) : null}
        </SectionPanel>

        <SectionPanel
          description="Categories ranked by recent sales activity."
          eyebrow="Find your next genre"
          id="categories"
          title="Popular categories"
        >
          <ResourceFeedback
            empty={
              popularCategories.data !== null &&
              popularCategories.data.data.length === 0
            }
            emptyMessage="No popular categories are available."
            error={popularCategories.error}
            loading={popularCategories.loading}
            onRetry={popularCategories.reload}
          />
          {popularCategories.data &&
          popularCategories.data.data.length > 0 ? (
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
              {popularCategories.data.data.map((category) => (
                <Link
                  className="border border-outline bg-card p-4 text-lg font-black text-text hover:border-savings"
                  key={category.id}
                  to={`/store?category=${encodeURIComponent(category.slug)}`}
                >
                  {category.name}
                </Link>
              ))}
            </div>
          ) : null}
        </SectionPanel>
      </main>
      <SiteFooter />
    </div>
  )
}

export { HomePage }
