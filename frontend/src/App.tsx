import { Route, Routes } from 'react-router-dom'
import './App.css'
import { RequireAuth } from './features/auth/components/RequireAuth'
import { LoginPage } from './features/auth/pages/LoginPage'
import { DashboardPage } from './features/health/pages/DashboardPage'
import { EmployeesPage } from './features/health/pages/EmployeesPage'
import { InventoryPage } from './features/inventory/pages/InventoryPage'
import { ProductsPage } from './features/inventory/pages/ProductsPage'
import { StockImportPage } from './features/inventory/pages/StockImportPage'
import { DailySalesPage } from './features/inventory/pages/DailySalesPage'
import { NewDailySalePage } from './features/inventory/pages/NewDailySalePage'
import { BorrowedPage } from './features/inventory/pages/BorrowedPage'
import { ScheduleGridPage } from './features/schedule/pages/ScheduleGridPage'

function App() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<LoginPage />} />

      {/* Auth required */}
      <Route element={<RequireAuth />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/schedule" element={<ScheduleGridPage />} />
        <Route path="/products" element={<ProductsPage />} />
        <Route path="/inventory" element={<InventoryPage />} />
        <Route path="/inventory/import" element={<StockImportPage />} />
        <Route path="/daily-sales" element={<DailySalesPage />} />
        <Route path="/daily-sales/new" element={<NewDailySalePage />} />
        <Route path="/borrowed" element={<BorrowedPage />} />
        <Route path="/employees" element={<EmployeesPage />} />
      </Route>
    </Routes>
  )
}

export default App
