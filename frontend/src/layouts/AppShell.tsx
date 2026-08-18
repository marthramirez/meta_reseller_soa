import { Outlet, useLocation } from 'react-router-dom'

import { AppHeader } from '@/layouts/AppHeader'
import { getPageHeader } from '@/constants/pageHeaders.constants'

/** App shell — header plus page content. */
export function AppShell() {
  const { pathname } = useLocation()
  const { title, description } = getPageHeader(pathname)

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <AppHeader title={title} description={description} />
      <div className="flex flex-1 flex-col">
        <Outlet />
      </div>
    </div>
  )
}
