interface StatusMessageProps {
  title: string
  message: string
  tone?: 'notice' | 'empty' | 'error'
}

const toneClasses = {
  notice: 'border-outline bg-card text-muted',
  empty: 'border-outline bg-section text-muted',
  error: 'border-region bg-card text-text',
}

function StatusMessage({
  title,
  message,
  tone = 'notice',
}: StatusMessageProps) {
  return (
    <div
      aria-live={tone === 'error' ? 'assertive' : 'polite'}
      className={`border p-4 ${toneClasses[tone]}`}
      role={tone === 'error' ? 'alert' : 'status'}
    >
      <p className="font-bold text-text">{title}</p>
      <p className="mt-1 text-sm">{message}</p>
    </div>
  )
}

export { StatusMessage }
