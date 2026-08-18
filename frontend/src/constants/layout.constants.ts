export const CONTENT_PADDING_START = 'pl-4 md:pl-5 lg:pl-6' as const

export const CONTENT_PADDING_END = 'pr-4 md:pr-5 lg:pr-6' as const

export const CONTENT_PADDING_X = `${CONTENT_PADDING_START} ${CONTENT_PADDING_END}` as const

export const PAGE_CONTENT_INSET = `${CONTENT_PADDING_X} pt-4 pb-6` as const

export const PAGE_BODY_SCROLL =
  'flex-1 overflow-y-auto custom-scrollbar [scrollbar-gutter:stable] min-w-0' as const
