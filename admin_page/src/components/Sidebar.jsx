import React, { useState } from 'react';
import './Sidebar.css';

const Sidebar = ({ activeTab, setActiveTab }) => {
  const [isCollapsed, setIsCollapsed] = useState(false);

  const menuItems = [
    {
      id: 'dashboard',
      icon: '📊',
      label: 'ช้อมูลสถิติ',
      count: null
    },
    {
      id: 'users',
      icon: '👥',
      label: 'ข้อมูลผู้ใช้',
      count: null
    },
    {
      id: 'categories',
      icon: '📋',
      label: 'จัดการหมวดหมู่',
      count: null
    },
    {
      id: 'areas',
      icon: '🏠',
      label: 'จัดการพื้นที่',
      count: null
    }
  ];

  return (
    <div className={`sidebar ${isCollapsed ? 'collapsed' : ''}`}>
      <div className="sidebar-header">
        <div className="logo">
          <span className="logo-icon">🛡️</span>
          {!isCollapsed && <span className="logo-text">ผู้ดูแลระบบ</span>}
        </div>
        <button 
          className="toggle-btn"
          onClick={() => setIsCollapsed(!isCollapsed)}
        >
          {isCollapsed ? '▶️' : '◀️'}
        </button>
      </div>

      <nav className="sidebar-nav">
        <ul>
          {menuItems.map(item => (
            <li key={item.id}>
              <button
                className={`nav-item ${activeTab === item.id ? 'active' : ''}`}
                onClick={() => setActiveTab(item.id)}
                data-tooltip={item.label}
              >
                <span className="nav-icon">{item.icon}</span>
                {!isCollapsed && (
                  <>
                    <span className="nav-label">{item.label}</span>
                    {item.count !== null && (
                      <span className="nav-count">{item.count}</span>
                    )}
                  </>
                )}
              </button>
            </li>
          ))}
        </ul>
      </nav>


    </div>
  );
};

export default Sidebar;
