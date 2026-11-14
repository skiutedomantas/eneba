import { SiteFooter } from '../../components/layout/SiteFooter'
import { SiteHeader } from '../../components/layout/SiteHeader'
import { CatalogControls } from '../../components/ui/CatalogControls'
import { ProductGrid } from '../../components/ui/ProductGrid'
import { PromoBanner } from '../../components/ui/PromoBanner'
import { SectionPanel } from '../../components/ui/SectionPanel'
import { StatusMessage } from '../../components/ui/StatusMessage'
import { previewProducts } from './previewProducts'

function HomePage() {
  return (
    <div className="min-h-screen bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <StatusMessage
          message="Fictional content only: search, filters, and account controls are visual preview controls."
          title="Phase 2A visual preview"
          tone="notice"
        />
        <PromoBanner />
        <SectionPanel
          description="A presentation preview of the dense, responsive product grid and reusable offer details."
          eyebrow="Picked for your next session"
          id="featured"
          title="Featured games"
        >
          <CatalogControls />
          <ProductGrid products={previewProducts} />
        </SectionPanel>
        <SectionPanel
          description="Rectangular content blocks use the shared section and card surfaces."
          eyebrow="Browse your way"
          id="categories"
          title="Popular categories"
        >
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
            {['Action', 'Role-playing', 'Adventure'].map((category) => (
              <a
                className="border border-outline bg-card p-4 text-lg font-black text-text hover:border-savings"
                href="#featured"
                key={category}
              >
                {category}
              </a>
            ))}
          </div>
        </SectionPanel>
      </main>
      <SiteFooter />
    </div>
  )
}

export { HomePage }
