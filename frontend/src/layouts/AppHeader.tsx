import {
  CONTENT_PADDING_END,
  CONTENT_PADDING_START,
} from '@/constants/layout.constants'
import { cn } from '@/lib/utils'

interface AppHeaderProps {
  title: string
  description?: string
}

/** Top bar with page title and description. */
export function AppHeader({ title, description }: AppHeaderProps) {
  return (
    <header
      className={cn(
        'flex h-14 shrink-0 items-center border-b border-border bg-background',
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
    </header>
  )
}
