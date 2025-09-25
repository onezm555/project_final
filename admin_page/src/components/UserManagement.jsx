import React, { useState, useEffect } from 'react';
import './UserManagement.css';
import API_CONFIG from '../config/api.js';

const ROWS_PER_PAGE = 10;

const UserManagement = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedUser, setSelectedUser] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await fetch(API_CONFIG.ENDPOINTS.GET_USERS, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });
      const data = await response.json();
      if (data.status === 'success') {
        setUsers(data.data);
      } else {
        setError(data.message || 'เกิดข้อผิดพลาดในการดึงข้อมูล');
      }
    } catch (error) {
      console.error('Fetch users error:', error);
      setError('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
    } finally {
      setLoading(false);
    }
  };

  // ฟิลเตอร์ผู้ใช้ตามการค้นหา
  const filteredUsers = users.filter(user =>
    user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.phone.includes(searchTerm)
  );

  // Pagination logic
  const totalPages = Math.ceil(filteredUsers.length / ROWS_PER_PAGE);
  const paginatedUsers = filteredUsers.slice(
    (currentPage - 1) * ROWS_PER_PAGE,
    currentPage * ROWS_PER_PAGE
  );

  const handleViewUser = (user) => {
    setSelectedUser(user);
    setShowModal(true);
  };

  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // Reset to page 1 when searchTerm changes
  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm]);

  return (
    <div className="user-management">
      <div className="header">
        <h2>จัดการผู้ใช้</h2>
      </div>
      <div className="search-section">
        <div className="search-box long">
          <input
            type="text"
            placeholder="ค้นหาผู้ใช้..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={{
              width: '320px',
              maxWidth: '100%',
              padding: '10px 15px',
              fontSize: '1rem',
              borderRadius: '20px',
              border: '1px solid #d1d5db',
              boxShadow: '0 2px 8px rgba(102,126,234,0.08)',
              outline: 'none',
              marginBottom: '8px',
              background: '#fff',
              transition: 'box-shadow 0.2s',
              color: '#222',
            }}
          />
        </div>
        <button 
          className="refresh-btn"
          onClick={fetchUsers}
          disabled={loading}
          title="รีเฟรชข้อมูล"
          style={{
            marginLeft: '0',
            marginTop: '8px',
            padding: '10px 24px',
            fontSize: '1rem',
            borderRadius: '24px',
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            color: '#fff',
            border: 'none',
            boxShadow: '0 2px 8px rgba(102,126,234,0.08)',
            cursor: 'pointer',
            transition: 'background 0.2s',
          }}
        >
          🔄 รีเฟรช
        </button>
      </div>

      {error && (
        <div className="error-message">
          <span className="error-icon">⚠️</span>
          {error}
        </div>
      )}

      {loading && (
        <div className="loading-container">
          <div className="loading-spinner"></div>
          <p>กำลังโหลดข้อมูล...</p>
        </div>
      )}

      <div className="users-table">
        <table>
          <thead>
            <tr>
              <th>ลำดับ</th>
              <th>รูปภาพ</th>
              <th>ชื่อ</th>
              <th>อีเมล</th>
              <th>เบอร์โทร</th>
              <th>สถานะ</th>
              <th>ดูรายละเอียด</th>
            </tr>
          </thead>
          <tbody>
            {paginatedUsers.map((user, index) => (
              <tr key={user.id}>
                <td>{(currentPage - 1) * ROWS_PER_PAGE + index + 1}</td>
                <td>
                  <div className="user-avatar">
                    <img
                      src={user.profile_url}
                      alt={user.name}
                      onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextSibling.style.display = 'flex';
                      }}
                    />
                    <div 
                      className="default-avatar"
                      style={{
                        display: 'none',
                        alignItems: 'center',
                        justifyContent: 'center',
                        width: '50px',
                        height: '50px',
                        borderRadius: '50%',
                        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        color: 'white',
                        fontSize: '1.5rem'
                      }}
                    >
                      👤
                    </div>
                  </div>
                </td>
                <td>
                  <div className="user-info">
                    <span className="user-name">{user.name}</span>
                  </div>
                </td>
                <td>{user.email}</td>
                <td>{user.phone}</td>
                <td>
                  <span className={`status-badge ${user.is_verified ? 'verified' : 'pending'}`}>
                    {user.status}
                  </span>
                </td>
                <td>
                  <div className="action-buttons">
                    <button 
                      className="btn-view"
                      onClick={() => handleViewUser(user)}
                      title="ดูรายละเอียด"
                      style={{
                        backgroundColor: '#4f46e5',
                        color: '#ffffff',
                        border: 'none',
                        padding: '8px 12px',
                        borderRadius: '6px',
                        cursor: 'pointer',
                        fontSize: '14px',
                        fontWeight: '500',
                        transition: 'background-color 0.2s'
                      }}
                      onMouseOver={(e) => e.target.style.backgroundColor = '#3730a3'}
                      onMouseOut={(e) => e.target.style.backgroundColor = '#4f46e5'}
                    >
                      👁️ ดูข้อมูล
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {filteredUsers.length === 0 && (
          <div className="no-data">
            <p>ไม่พบผู้ใช้ที่ค้นหา</p>
          </div>
        )}
      </div>
        {totalPages > 1 && (
          <div className="pagination" style={{ margin: '16px 0', textAlign: 'center', color: '#222' }}>
            <button
          onClick={() => handlePageChange(currentPage - 1)}
          disabled={currentPage === 1}
          style={{
            margin: '0 4px',
            padding: '6px 12px',
            borderRadius: '6px',
            border: '1px solid #d1d5db',
            background: currentPage === 1 ? '#eee' : '#fff',
            cursor: currentPage === 1 ? 'not-allowed' : 'pointer',
            color: '#222'
          }}
            >
          ก่อนหน้า
            </button>
            {Array.from({ length: totalPages }, (_, i) => (
          <button
            key={i + 1}
            onClick={() => handlePageChange(i + 1)}
            style={{
              margin: '0 2px',
              padding: '6px 12px',
              borderRadius: '6px',
              border: '1px solid #d1d5db',
              background: currentPage === i + 1 ? '#667eea' : '#fff',
              color: currentPage === i + 1 ? '#fff' : '#222',
              fontWeight: currentPage === i + 1 ? 'bold' : 'normal',
              cursor: 'pointer'
            }}
          >
            {i + 1}
          </button>
            ))}
            <button
          onClick={() => handlePageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          style={{
            margin: '0 4px',
            padding: '6px 12px',
            borderRadius: '6px',
            border: '1px solid #d1d5db',
            background: currentPage === totalPages ? '#eee' : '#fff',
            cursor: currentPage === totalPages ? 'not-allowed' : 'pointer',
            color: '#222'
          }}
            >
          ถัดไป
            </button>
          </div>
        )}

        {showModal && selectedUser && (
          <div className="modal-overlay" onClick={() => setShowModal(false)}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ color: '#222' }}>
          <div className="modal-header">
            <h3 style={{ color: '#222' }}>รายละเอียดผู้ใช้</h3>
            <button className="close-btn" onClick={() => setShowModal(false)} style={{ color: '#222' }}>×</button>
          </div>
          <div className="modal-body">
            <div className="user-detail">
              <div className="user-detail-avatar">
            <img
              src={selectedUser.profile_url}
              alt={selectedUser.name}
              onError={(e) => {
                      e.target.style.display = 'none';
                      e.target.nextSibling.style.display = 'flex';
                    }}
                  />
                  <div 
                    className="default-avatar"
                    style={{
                      display: 'none',
                      alignItems: 'center',
                      justifyContent: 'center',
                      width: '100px',
                      height: '100px',
                      borderRadius: '50%',
                      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                      color: 'white',
                      fontSize: '2.5rem'
                    }}
                  >
                    👤
                  </div>
                </div>
                <div className="user-detail-info">
                  <div className="detail-row">
                    <label>ชื่อ:</label>
                    <span>{selectedUser.name}</span>
                  </div>
                  <div className="detail-row">
                    <label>อีเมล:</label>
                    <span>{selectedUser.email}</span>
                  </div>
                  <div className="detail-row">
                    <label>เบอร์โทร:</label>
                    <span>{selectedUser.phone}</span>
                  </div>
                  <div className="detail-row">
                    <label>สถานะ:</label>
                    <span className={`status-badge ${selectedUser.is_verified ? 'verified' : 'pending'}`}>
                      {selectedUser.status}
                    </span>
                  </div>
                  {selectedUser.last_activity && (
                    <div className="detail-row">
                      <label>กิจกรรมล่าสุด:</label>
                      <span>{selectedUser.last_activity}</span>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default UserManagement;
