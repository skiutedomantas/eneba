import { StatusMessage } from './StatusMessage'

interface ResourceFeedbackProps {
  loading: boolean
  error: string | null
  empty: boolean
  emptyMessage: string
  onRetry: () => void
}

function ResourceFeedback({
  loading,
  error,
  empty,
  emptyMessage,
  onRetry,
}: ResourceFeedbackProps) {
  if (loading) {
    return (
      <StatusMessage
        message="This section is loading."
        title="Loading"
        tone="notice"
      />
    )
  }

  if (error) {
    return (
      <div className="space-y-3">
        <StatusMessage message={error} title="Could not load this section" tone="error" />
        <button
          className="border border-outline bg-card px-4 py-2 text-sm font-bold text-text hover:bg-section"
          onClick={onRetry}
          type="button"
        >
          Try again
        </button>
      </div>
    )
  }

  if (empty) {
    return (
      <StatusMessage
        message={emptyMessage}
        title="Nothing to show yet"
        tone="empty"
      />
    )
  }

  return null
}

export { ResourceFeedback }
