import { OrderExportUploadForm } from '@/features/order-export/OrderExportUploadForm'
import { PAGE_BODY_SCROLL } from '@/constants/layout.constants'
import { cn } from '@/lib/utils'

/** Upload page for Meta order exports. */
export default function UploadOrdersPage() {
  return (
    <div
      className={cn(
        PAGE_BODY_SCROLL,
        'flex items-center px-12 py-4 md:px-24 lg:px-32',
      )}
    >
      <OrderExportUploadForm />
    </div>
  )
}
