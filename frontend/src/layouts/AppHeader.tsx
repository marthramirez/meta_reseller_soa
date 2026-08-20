import {
  CONTENT_PADDING_END,
  CONTENT_PADDING_START,
} from '@/constants/layout.constants'
import { cn } from '@/lib/utils'

interface AppHeaderProps {
  title: string
  description?: string
  isDark: boolean
  onToggleTheme: () => void
}

/** Top bar with page title, description, and theme toggle. */
export function AppHeader({
  title,
  description,
  isDark,
  onToggleTheme,
}: AppHeaderProps) {
  return (
    <header
      className={cn(
        'flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-background',
        CONTENT_PADDING_START,
        CONTENT_PADDING_END,
      )}
    >
      <div className="min-w-0">
        <h1 className="truncate text-sm font-semibold text-foreground">{title}</h1>
        {description ? (
          <p className="truncate text-xs text-muted-foreground">{description}</p>
        ) : null}
      </div>
      <button
        type="button"
        aria-label={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
        className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
        onClick={onToggleTheme}
      >
        {isDark ? (
          <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" className="h-4 w-4">
            <circle cx="8" cy="8" r="3" stroke="currentColor" strokeWidth="1.5" />
            <path
              d="M8 1.5v1.5M8 13v1.5M1.5 8H3M13 8h1.5M3.4 3.4l1.1 1.1M11.5 11.5l1.1 1.1M3.4 12.6l1.1-1.1M11.5 4.5l1.1-1.1"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
            />
          </svg>
        ) : (
          <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" className="h-4 w-4">
            <path
              d="M13.5 9.2A5.5 5.5 0 1 1 6.8 2.5 4.5 4.5 0 0 0 13.5 9.2Z"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinejoin="round"
            />
          </svg>
        )}
      </button>
    </header>
  )
}
