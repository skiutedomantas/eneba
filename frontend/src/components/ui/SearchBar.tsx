import type { FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

function SearchBar() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const currentQuery = searchParams.get('q') ?? ''

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const formData = new FormData(event.currentTarget)
    const query = String(formData.get('q') ?? '').trim()
    if (!query) {
      navigate('/search')
      return
    }

    const params = new URLSearchParams({ q: query })
    navigate(`/search?${params.toString()}`)
  }

  return (
    <form
      aria-label="Search games and digital products"
      className="flex min-w-0 border border-outline bg-card"
      role="search"
      onSubmit={submit}
    >
      <label className="sr-only" htmlFor="marketplace-search">
        Search games and digital products
      </label>
      <input
        className="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm text-text placeholder:text-muted"
        defaultValue={currentQuery}
        id="marketplace-search"
        key={currentQuery}
        name="q"
        placeholder="Search games, gift cards, and more"
        maxLength={200}
        minLength={2}
        type="search"
      />
      <button
        className="shrink-0 border-l border-outline bg-section px-4 text-sm font-bold text-text hover:bg-card"
        type="submit"
      >
        Search
      </button>
    </form>
  )
}

export { SearchBar }
