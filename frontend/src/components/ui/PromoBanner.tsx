function PromoBanner() {
  return (
    <section
      aria-labelledby="promo-heading"
      aria-describedby="promo-description"
      className="promo-pattern relative grid overflow-hidden border border-outline bg-section md:min-h-80 md:grid-cols-[1.4fr_0.6fr]"
    >
      <div className="relative z-10 flex flex-col items-start justify-center p-6 md:p-10">
        <p className="text-xs font-black uppercase tracking-[0.2em] text-savings">
          Your next adventure starts here
        </p>
        <h1
          className="mt-4 max-w-3xl text-4xl font-black leading-[1.02] tracking-tight text-text sm:text-5xl lg:text-6xl"
          id="promo-heading"
        >
          Big worlds.
          <br />
          Better prices.
        </h1>
        <p
          className="mt-5 max-w-xl text-base leading-7 text-muted"
          id="promo-description"
        >
          Explore fresh picks, standout deals, and digital favorites selected
          for your next play session.
        </p>
        <a
          className="mt-7 border border-savings bg-savings px-5 py-3 text-sm font-black text-card hover:bg-transparent hover:text-savings focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-savings"
          href="#featured"
        >
          Explore featured games
        </a>
      </div>
      <div
        aria-hidden="true"
        className="relative hidden min-h-64 border-l border-outline bg-card md:block"
      >
        <div className="absolute inset-0 bg-[linear-gradient(135deg,transparent_0_38%,rgba(131,233,22,0.85)_38%_39%,transparent_39%),linear-gradient(45deg,transparent_0_61%,rgba(230,106,140,0.85)_61%_62%,transparent_62%)]" />
        <div className="absolute inset-8 border border-outline" />
        <div className="absolute bottom-8 left-8 border border-outline bg-background px-5 py-4">
          <p className="text-xs font-black uppercase tracking-[0.16em] text-text">
            The weekly selection
          </p>
          <p className="mt-2 text-3xl font-black text-savings">PLAY MORE</p>
        </div>
      </div>
    </section>
  )
}

export { PromoBanner }
