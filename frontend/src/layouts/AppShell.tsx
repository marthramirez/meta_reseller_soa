import { useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'

import { AppHeader } from '@/layouts/AppHeader'
import { getPageHeader } from '@/constants/pageHeaders.constants'
import { ROUTES } from '@/constants/routes.constants'
import { cn } from '@/lib/utils'

/** App shell — header plus page content. */
export function AppShell() {
  const { pathname } = useLocation()
  const { title, description } = getPageHeader(pathname)
  const isResult = pathname === ROUTES.result
  const [isDark, setIsDark] = useState(false)

  return (
    <div
      className={cn(
        'flex flex-col bg-background',
        isDark && 'dark',
        isResult ? 'h-dvh min-h-0 overflow-hidden' : 'min-h-screen',
      )}
    >
      <AppHeader
        title={title}
        description={description}
        isDark={isDark}
        onToggleTheme={() => setIsDark((current) => !current)}
      />
      <div
        className={cn(
          'flex flex-1 flex-col',
          isResult && 'min-h-0 overflow-hidden',
        )}
      >
        <Outlet />
      </div>
    </div>
  )
}
