import { Link, Route, Routes } from 'react-router-dom'

function HomePage() {
  return (
    <main className="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 sm:px-10">
      <header className="flex items-center justify-between border-b border-white/10 pb-6">
        <Link className="text-xl font-bold tracking-tight text-white" to="/">
          GameHub
        </Link>
        <span className="rounded-full border border-violet-400/30 bg-violet-400/10 px-3 py-1 text-sm text-violet-200">
          Marketplace foundation
        </span>
      </header>
      <section className="flex flex-1 flex-col justify-center py-16">
        <p className="mb-4 text-sm font-semibold uppercase tracking-[0.2em] text-violet-300">
          Phase 1
        </p>
        <h1 className="max-w-3xl text-4xl font-bold tracking-tight text-white sm:text-6xl">
          Your digital game marketplace starts here.
        </h1>
        <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
          The React application and PHP API foundation are ready for the catalog,
          independent homepage services, and shopping flows.
        </p>
        <div className="mt-10">
          <a
            className="inline-flex rounded-lg bg-violet-500 px-5 py-3 font-semibold text-white transition hover:bg-violet-400"
            href="/api/v1/health"
          >
            Check API health
          </a>
        </div>
      </section>
      <footer className="border-t border-white/10 py-5 text-sm text-slate-400">
        GameHub Marketplace
      </footer>
    </main>
  )
}

function NotFoundPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-7xl flex-col justify-center px-6 py-16">
      <h1 className="text-4xl font-bold text-white">Page not found</h1>
      <Link className="mt-4 text-violet-300 hover:text-violet-200" to="/">
        Return home
      </Link>
    </main>
  )
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}

export default App
