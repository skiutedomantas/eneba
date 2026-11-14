import type { PropsWithChildren } from 'react'

interface SectionPanelProps extends PropsWithChildren {
  id: string
  eyebrow: string
  title: string
  description?: string
}

function SectionPanel({
  id,
  eyebrow,
  title,
  description,
  children,
}: SectionPanelProps) {
  return (
    <section
      aria-labelledby={`${id}-heading`}
      aria-describedby={description ? `${id}-description` : undefined}
      className="bg-section p-4 md:p-6"
      id={id}
    >
      <div className="mb-6 border-b border-outline pb-4">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-savings">
          {eyebrow}
        </p>
        <h2
          className="mt-2 text-2xl font-black tracking-tight text-text md:text-3xl"
          id={`${id}-heading`}
        >
          {title}
        </h2>
        {description ? (
          <p
            className="mt-2 max-w-2xl text-sm leading-6 text-muted"
            id={`${id}-description`}
          >
            {description}
          </p>
        ) : null}
      </div>
      {children}
    </section>
  )
}

export { SectionPanel }
