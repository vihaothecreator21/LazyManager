import { Route, Routes } from 'react-router-dom'
import './App.css'
import { RequireManager } from './features/auth/components/RequireManager'
import { LoginPage } from './features/auth/pages/LoginPage'
import { DashboardPage } from './features/health/pages/DashboardPage'
import { EmployeesPage } from './features/health/pages/EmployeesPage'
import { InventoryPage } from './features/inventory/pages/InventoryPage'
import { ProductsPage } from './features/inventory/pages/ProductsPage'
import { StockImportPage } from './features/inventory/pages/StockImportPage'
import { ScheduleGridPage } from './features/schedule/pages/ScheduleGridPage'

function App() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<DashboardPage />} />
      <Route path="/schedule" element={<ScheduleGridPage />} />
      <Route path="/products" element={<ProductsPage />} />
      <Route path="/inventory" element={<InventoryPage />} />
      <Route path="/inventory/import" element={<StockImportPage />} />

      {/* Manager-only routes */}
      <Route element={<RequireManager />}>
        <Route path="/employees" element={<EmployeesPage />} />
      </Route>
    </Routes>
  )
}

export default App
