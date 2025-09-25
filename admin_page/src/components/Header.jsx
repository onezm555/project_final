import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import SweetAlert from './SweetAlert';
import './Header.css';

const Header = () => {
  const { logout } = useAuth();
  const [alert, setAlert] = useState(null);

  const handleLogout = () => {
    setAlert({
      type: 'confirm',
      title: 'ยืนยันการออกจากระบบ',
      message: 'คุณต้องการออกจากระบบหรือไม่?',
      confirmText: 'ออกจากระบบ',
      cancelText: 'ยกเลิก',
      onConfirm: () => {
        logout();
        setAlert(null);
      },
      onCancel: () => setAlert(null)
    });
  };


  return (
    <>
      <header className="main-header">
        <div className="header-content">
          <div className="header-title">
            <h1>แอปพลิเคชันจัดการของอุปโภค-บริโภคภายในบ้าน</h1>
            
          </div>
          
          <div className="header-actions">
            
            <button 
              className="logout-btn"
              onClick={handleLogout}
              title="ออกจากระบบ"
            >
              <span className="logout-icon">🚪</span>
              <span className="logout-text">ออกจากระบบ</span>
            </button>
          </div>
        </div>
      </header>
      
      {alert && (
        <SweetAlert
          isOpen={true}
          type={alert.type}
          title={alert.title}
          message={alert.message}
          confirmText={alert.confirmText}
          cancelText={alert.cancelText}
          showCancelButton={true}
          onConfirm={alert.onConfirm}
          onCancel={alert.onCancel}
          onClose={() => setAlert(null)}
          autoClose={false}
        />
      )}
    </>
  );
};

export default Header;
