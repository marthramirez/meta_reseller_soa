import * as React from 'react'

import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

interface DatePickerInputProps extends Omit<React.ComponentProps<'input'>, 'type'> {}

/** Date picker with the native calendar icon pinned to the right edge. */
const DatePickerInput = React.forwardRef<HTMLInputElement, DatePickerInputProps>(
  ({ className, ...props }, ref) => {
    return (
      <Input
        ref={ref}
        type="date"
        className={cn('datetime-local-input w-full', className)}
        {...props}
      />
    )
  },
)
DatePickerInput.displayName = 'DatePickerInput'

export { DatePickerInput }
