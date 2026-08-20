import { BrowserRouter, Route, Routes } from 'react-router-dom'

import { AppShell } from '@/layouts/AppShell'
import SoaResultPage from '@/pages/SoaResultPage'
import UploadOrdersPage from '@/pages/UploadOrdersPage'
import { ROUTES } from '@/constants/routes.constants'

/** Root router for the app. */
export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<AppShell />}>
          <Route path={ROUTES.upload} element={<UploadOrdersPage />} />
          <Route path={ROUTES.result} element={<SoaResultPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
