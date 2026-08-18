import { ROUTES } from '@/constants/routes.constants'

export interface IPageHeader {
  title: string
  description?: string
}

export const PAGE_HEADERS = {
  upload: {
    title: 'Meta Reseller SOA',
    description: 'version 1.0.0',
  },
} as const satisfies Record<string, IPageHeader>

/** Return the header copy for the current path. */
export function getPageHeader(pathname: string): IPageHeader {
  if (pathname === ROUTES.upload) {
    return PAGE_HEADERS.upload
  }

  return PAGE_HEADERS.upload
}
