import * as React from 'react'

import { FORM_INPUT_CLASS } from '@/constants/formField.constants'
import { cn } from '@/lib/utils'

/** Text/date input with shared form chrome. */
const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<'input'>>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(FORM_INPUT_CLASS, className)}
        ref={ref}
        {...props}
      />
    )
  },
)
Input.displayName = 'Input'

export { Input }
