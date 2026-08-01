import { Route, Routes } from 'react-router-dom'
import './App.css'
import { RequireManager } from './features/auth/components/RequireManager'
import { LoginPage } from './features/auth/pages/LoginPage'
import { DashboardPage } from './features/health/pages/DashboardPage'
import { EmployeesPage } from './features/health/pages/EmployeesPage'
import { ScheduleGridPage } from './features/schedule/pages/ScheduleGridPage'

function App() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<DashboardPage />} />
      <Route path="/schedule" element={<ScheduleGridPage />} />

      {/* Manager-only routes */}
      <Route element={<RequireManager />}>
        <Route path="/employees" element={<EmployeesPage />} />
      </Route>
    </Routes>
  )
}

export default App
