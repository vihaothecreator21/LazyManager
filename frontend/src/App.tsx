import { Route, Routes } from 'react-router-dom'
import './App.css'
import { RequireAuth } from './features/auth/components/RequireAuth'
import { LoginPage } from './features/auth/pages/LoginPage'
import { DashboardPage } from './features/health/pages/DashboardPage'
import { EmployeesPage } from './features/health/pages/EmployeesPage'

function App() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<LoginPage />} />

      {/* Protected routes */}
      <Route element={<RequireAuth />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/employees" element={<EmployeesPage />} />
      </Route>
    </Routes>
  )
}

export default App
