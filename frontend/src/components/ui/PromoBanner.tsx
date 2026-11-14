interface PromoBannerProps {
  title: string
  imageUrl: string
  targetUrl: string
}

function PromoBanner({ title, imageUrl, targetUrl }: PromoBannerProps) {
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
          {title}
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
          href={targetUrl}
        >
          Explore now
        </a>
      </div>
      <div
        className="relative hidden min-h-64 border-l border-outline bg-card md:block"
      >
        <img
          alt=""
          className="absolute inset-0 h-full w-full object-cover"
          src={imageUrl}
        />
      </div>
    </section>
  )
}

export { PromoBanner }
