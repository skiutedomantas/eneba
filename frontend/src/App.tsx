import { Link, Route, Routes } from 'react-router-dom'
import { SiteFooter } from './components/layout/SiteFooter'
import { SiteHeader } from './components/layout/SiteHeader'
import { StatusMessage } from './components/ui/StatusMessage'
import { HomePage } from './features/home/HomePage'
import { CatalogPage } from './pages/CatalogPage'
import { ProductDetailPage } from './pages/ProductDetailPage'

function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col bg-background text-text">
      <SiteHeader />
      <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col justify-center px-4 py-16 md:px-8">
        <StatusMessage
          title="Page not found"
          message="The page you requested does not exist in this preview."
          tone="notice"
        />
        <Link
          className="mt-6 w-fit border border-outline bg-card px-5 py-3 font-semibold text-text hover:bg-section focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings"
          to="/"
        >
          Return home
        </Link>
      </main>
      <SiteFooter />
    </div>
  )
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/store" element={<CatalogPage />} />
      <Route path="/search" element={<CatalogPage searchMode />} />
      <Route path="/products/:slug" element={<ProductDetailPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}

export default App
