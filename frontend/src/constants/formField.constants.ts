export const FORM_FIELD_FOCUS_CLASS =
  'focus:outline-none focus-visible:outline-none focus-visible:ring-0 focus-visible:border-foreground/40' as const

export const FORM_FIELD_BASE_CLASS =
  `rounded-md border border-input bg-transparent text-sm text-foreground shadow-xs transition-colors ${FORM_FIELD_FOCUS_CLASS} disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30 dark:placeholder:text-muted-foreground` as const

export const FORM_INPUT_CLASS =
  `flex h-10 w-full px-3 py-2 placeholder:text-muted-foreground ${FORM_FIELD_BASE_CLASS}` as const
