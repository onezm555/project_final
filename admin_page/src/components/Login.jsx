import React, { useState } from 'react';
import './Login.css';
import API_CONFIG from '../config/api.js';

const Login = ({ onLogin }) => {
  const [formData, setFormData] = useState({
    username: '',
    password: ''
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    // Clear error when user starts typing
    if (error) setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');

    try {
      // เรียก API สำหรับล็อกอิน
      const response = await fetch(API_CONFIG.ENDPOINTS.ADMIN_LOGIN, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          username: formData.username,
          password: formData.password
        }),
      });

      const data = await response.json();

      if (data.status === 'success') {
        // บันทึกข้อมูลการล็อกอินไว้ใน localStorage
        localStorage.setItem('admin_token', data.token || 'admin_logged_in');
        localStorage.setItem('admin_user', JSON.stringify(data.user));
        
        // เรียกฟังก์ชัน onLogin เพื่อแจ้งว่าล็อกอินสำเร็จ
        onLogin(data.user);
      } else {
        setError(data.message || 'เกิดข้อผิดพลาดในการล็อกอิน');
      }
    } catch (error) {
      console.error('Login error:', error);
      setError('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-card">
        <div className="login-header">
          <div className="login-logo">
            <span className="logo-icon">🛡️</span>
            <h1>ผู้ดูแลระบบ</h1>
          </div>
          <p className="login-subtitle">เข้าสู่ระบบจัดการ</p>
        </div>

        <form onSubmit={handleSubmit} className="login-form">
          {error && (
            <div className="error-message">
              <span className="error-icon">⚠️</span>
              {error}
            </div>
          )}

          <div className="form-group">
            <label htmlFor="username">ชื่อผู้ใช้</label>
            <div className="input-wrapper">
              <input
                type="text"
                id="username"
                name="username"
                value={formData.username}
                onChange={handleChange}
                placeholder="กรอกชื่อผู้ใช้ของคุณ"
                required
                disabled={isLoading}
              />
            </div>
          </div>

          <div className="form-group">
            <label htmlFor="password">รหัสผ่าน</label>
            <div className="input-wrapper">
              <input
                type={showPassword ? "text" : "password"}
                id="password"
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder="กรอกรหัสผ่านของคุณ"
                required
                disabled={isLoading}
              />
              <button
                type="button"
                className="password-toggle"
                onClick={() => setShowPassword(!showPassword)}
                disabled={isLoading}
              >
                {showPassword ? '👁️' : '👁️‍🗨️'}
              </button>
            </div>
          </div>

          <button 
            type="submit" 
            className="login-btn"
            disabled={isLoading || !formData.username || !formData.password}
          >
            {isLoading ? (
              <>
                <span className="loading-spinner"></span>
                กำลังเข้าสู่ระบบ...
              </>
            ) : (
              'เข้าสู่ระบบ'
            )}
          </button>
        </form>

        <div className="login-footer">
          <p>© 2025 Admin Panel. All rights reserved.</p>
        </div>
      </div>
    </div>
  );
};

export default Login;