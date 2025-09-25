import { useState } from 'react'
import { AuthProvider, useAuth } from './context/AuthContext'
import Login from './components/Login'
import Sidebar from './components/Sidebar'
import Header from './components/Header'
import Dashboard from './components/Dashboard'
import UserManagement from './components/UserManagement'
import CategoryManagement from './components/CategoryManagement'
import AreaManagement from './components/AreaManagement'
import './App.css'

// Main App Component ที่อยู่ภายใน AuthProvider
const MainApp = () => {
  const { isAuthenticated, isLoading, login } = useAuth()
  const [activeTab, setActiveTab] = useState('dashboard')

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <Dashboard />
      case 'users':
        return <UserManagement />
      case 'categories':
        return <CategoryManagement />
      case 'areas':
        return <AreaManagement />
      default:
        return <Dashboard />
    }
  }

  // แสดง Loading ขณะตรวจสอบการล็อกอิน
  if (isLoading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
        <p>กำลังตรวจสอบการเข้าสู่ระบบ...</p>
      </div>
    )
  }

  // แสดงหน้า Login ถ้ายังไม่ได้ล็อกอิน
  if (!isAuthenticated) {
    return <Login onLogin={login} />
  }

  // แสดงหน้าหลักถ้าล็อกอินแล้ว
  return (
    <div className="app">
      <Sidebar activeTab={activeTab} setActiveTab={setActiveTab} />
      <main className="main-content">
        <Header />
        <div className="content-area">
          {renderContent()}
        </div>
      </main>
    </div>
  )
}

// App Component หลักที่ wrap ด้วย AuthProvider
function App() {
  return (
    <AuthProvider>
      <MainApp />
    </AuthProvider>
  )
}

export default App
