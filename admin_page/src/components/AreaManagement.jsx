import React, { useState, useEffect } from 'react';
import './AreaManagement.css';
import SweetAlert from './SweetAlert';

const AreaManagement = () => {
  // State สำหรับจัดการข้อมูลพื้นที่
  const [areas, setAreas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [selectedArea, setSelectedArea] = useState(null);
  const [formData, setFormData] = useState({
    area_name: '',
    user_id: 0
  });
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(10);
  const [sweetAlert, setSweetAlert] = useState({
    isOpen: false,
    type: 'success',
    title: '',
    message: '',
    showConfirmButton: true,
    showCancelButton: false,
    onConfirm: null,
    onCancel: null
  });

  // ฟังก์ชันสำหรับแสดง Sweet Alert
  const showSweetAlert = (type, title, message, options = {}) => {
    setSweetAlert({
      isOpen: true,
      type,
      title,
      message,
      showConfirmButton: options.showConfirmButton !== false,
      showCancelButton: options.showCancelButton || false,
      onConfirm: options.onConfirm || null,
      onCancel: options.onCancel || null,
      autoClose: options.autoClose !== false,
      autoCloseTime: options.autoCloseTime || 3000
    });
  };

  const closeSweetAlert = () => {
    setSweetAlert(prev => ({ ...prev, isOpen: false }));
  };

  // ฟังก์ชันสำหรับดึงข้อมูลพื้นที่จาก API
  const fetchAreas = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_get_areas.php`);
      if (!response.ok) throw new Error('ไม่สามารถดึงข้อมูลได้');
      const data = await response.json();
      if (data.success) {
        console.log('Areas data:', data.areas); // Debug log
        setAreas(data.areas || []);
      } else {
        setError(data.message || 'เกิดข้อผิดพลาดในการดึงข้อมูล');
      }
    } catch (error) {
      console.error('Fetch error:', error);
      setError('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAreas();
  }, []);

  // ฟิลเตอร์พื้นที่ตามการค้นหา
  const filteredAreas = areas.filter(area =>
    area.area_name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // คำนวณ pagination
  const totalPages = Math.ceil(filteredAreas.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentAreas = filteredAreas.slice(startIndex, endIndex);

  // ฟังก์ชันสำหรับเปลี่ยนหน้า
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // รีเซ็ตหน้าเมื่อค้นหา
  const handleSearchChange = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
  };

  const handleAddArea = () => {
    setEditMode(false);
    setFormData({ area_name: '', user_id: 0 });
    setShowModal(true);
  };

  const handleEditArea = (area) => {
    setEditMode(true);
    setSelectedArea(area);
    setFormData({
      area_name: area.area_name,
      user_id: area.user_id || 0
    });
    setShowModal(true);
  };

  const handleDeleteArea = async (areaId) => {
    // แสดง Sweet Alert สำหรับยืนยันการลบ
    showSweetAlert(
      'confirm',
      'ยืนยันการลบ',
      'คุณแน่ใจหรือไม่ที่จะลบพื้นที่นี้? การลบจะส่งผลกระทบต่อสิ่งของที่อยู่ในพื้นที่นี้',
      {
        showCancelButton: true,
        confirmText: 'ลบ',
        cancelText: 'ยกเลิก',
        autoClose: false,
        onConfirm: async () => {
          try {
            const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_delete_area.php?area_id=${areaId}`, {
              method: 'DELETE',
              headers: {
                'Content-Type': 'application/json',
              }
            });
            
            const data = await response.json();
            
            if (data.success) {
              // อัปเดต state โดยลบพื้นที่ออก
              setAreas(areas.filter(area => area.area_id !== areaId));
              showSweetAlert(
                'success',
                'ลบสำเร็จ!',
                'ลบพื้นที่เรียบร้อยแล้ว'
              );
            } else {
              showSweetAlert(
                'error',
                'เกิดข้อผิดพลาด!',
                data.message || 'ไม่สามารถลบพื้นที่ได้'
              );
            }
          } catch (error) {
            console.error('Delete error:', error);
            showSweetAlert(
              'error',
              'เกิดข้อผิดพลาด!',
              'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
            );
          }
        }
      }
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    try {
      if (editMode) {
        // แก้ไขพื้นที่
        const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_update_area.php`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            area_id: selectedArea.area_id,
            area_name: formData.area_name
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          // อัปเดต state
          setAreas(areas.map(area =>
            area.area_id === selectedArea.area_id
              ? { ...area, area_name: formData.area_name }
              : area
          ));
          showSweetAlert(
            'success',
            'แก้ไขสำเร็จ!',
            'แก้ไขพื้นที่เรียบร้อยแล้ว'
          );
        } else {
          showSweetAlert(
            'error',
            'เกิดข้อผิดพลาด!',
            data.message || 'ไม่สามารถแก้ไขพื้นที่ได้'
          );
          return;
        }
      } else {
        // เพิ่มพื้นที่ใหม่
        const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_add_area.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            area_name: formData.area_name
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          // เพิ่มพื้นที่ใหม่ใน state
          setAreas([...areas, data.area]);
          showSweetAlert(
            'success',
            'เพิ่มสำเร็จ!',
            'เพิ่มพื้นที่ใหม่เรียบร้อยแล้ว'
          );
        } else {
          showSweetAlert(
            'error',
            'เกิดข้อผิดพลาด!',
            data.message || 'ไม่สามารถเพิ่มพื้นที่ได้'
          );
          return;
        }
      }
      
      // ปิด modal และรีเซ็ตฟอร์ม
      setShowModal(false);
      setFormData({ area_name: '', user_id: 0 });
      
    } catch (error) {
      console.error('Submit error:', error);
      showSweetAlert(
        'error',
        'เกิดข้อผิดพลาด!',
        'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
      );
    }
  };

  return (
    <div className="area-management">
      <div className="header">
        <h2>จัดการพื้นที่</h2>
        <div className="header-actions">
          <input
            type="text"
            className="search-input"
            placeholder="🔍 ค้นหาพื้นที่..."
            value={searchTerm}
            onChange={handleSearchChange}
          />
          <button className="btn-add" onClick={handleAddArea}>
            + เพิ่มพื้นที่ใหม่
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-error">
          <span className="alert-icon">⚠️</span>
          <span className="alert-message">{error}</span>
        </div>
      )}

      {loading ? (
        <div className="loading-state">
          <div className="loading-spinner"></div>
          <p>กำลังโหลดข้อมูลพื้นที่...</p>
        </div>
      ) : (
        <div className="areas-table-wrapper">
          <table className="areas-table">
            <thead>
              <tr>
                <th style={{width: '60px'}}>ลำดับ</th>
                <th>ชื่อพื้นที่</th>
                <th style={{width: '140px'}}>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              {currentAreas.map((area, idx) => (
                <tr key={area.area_id}>
                  <td>{startIndex + idx + 1}</td>
                  <td>{area.area_name}</td>
                  <td>
                    <div className="action-buttons">
                      <button 
                        className="btn-edit"
                        onClick={() => handleEditArea(area)}
                        title="แก้ไข"
                      >
                        ✏️
                      </button>
                      <button 
                        className="btn-delete"
                        onClick={() => handleDeleteArea(area.area_id)}
                        title="ลบ"
                      >
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filteredAreas.length === 0 && !loading && (
                <tr>
                  <td colSpan="3">
                    <div className="empty-state">
                      <div className="empty-icon">📭</div>
                      <h3>ไม่พบพื้นที่</h3>
                      <p>
                        {searchTerm 
                          ? `ไม่พบพื้นที่ที่ตรงกับ "${searchTerm}"`
                          : 'ยังไม่มีพื้นที่ในระบบ'
                        }
                      </p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {filteredAreas.length > 0 && (
        <div className="pagination-wrapper">
          <div className="pagination-info">
            แสดง {startIndex + 1}-{Math.min(endIndex, filteredAreas.length)} จาก {filteredAreas.length} รายการ
          </div>
          <div className="pagination">
            <button 
              className="page-btn prev"
              onClick={() => handlePageChange(currentPage - 1)}
              disabled={currentPage === 1}
            >
              ‹ ก่อนหน้า
            </button>
            
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
              <button
                key={page}
                className={`page-btn ${currentPage === page ? 'active' : ''}`}
                onClick={() => handlePageChange(page)}
              >
                {page}
              </button>
            ))}
            
            <button 
              className="page-btn next"
              onClick={() => handlePageChange(currentPage + 1)}
              disabled={currentPage === totalPages}
            >
              ถัดไป ›
            </button>
          </div>
        </div>
      )}

      {/* Modal สำหรับเพิ่ม/แก้ไขพื้นที่ */}
      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{editMode ? 'แก้ไขพื้นที่' : 'เพิ่มพื้นที่ใหม่'}</h3>
              <button className="close-btn" onClick={() => setShowModal(false)}>×</button>
            </div>
            <div className="modal-body">
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="area_name">ชื่อพื้นที่ *</label>
                  <input
                    type="text"
                    id="area_name"
                    value={formData.area_name}
                    onChange={(e) => setFormData({...formData, area_name: e.target.value})}
                    placeholder="กรอกชื่อพื้นที่"
                    required
                  />
                </div>

                <div className="form-actions">
                  <button type="button" className="btn-cancel" onClick={() => setShowModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn-submit">
                    {editMode ? 'บันทึกการแก้ไข' : 'เพิ่มพื้นที่'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Sweet Alert Component */}
      <SweetAlert
        isOpen={sweetAlert.isOpen}
        type={sweetAlert.type}
        title={sweetAlert.title}
        message={sweetAlert.message}
        showConfirmButton={sweetAlert.showConfirmButton}
        showCancelButton={sweetAlert.showCancelButton}
        confirmText={sweetAlert.type === 'confirm' ? 'ลบ' : 'ตกลง'}
        cancelText="ยกเลิก"
        onConfirm={sweetAlert.onConfirm}
        onCancel={sweetAlert.onCancel}
        onClose={closeSweetAlert}
        autoClose={sweetAlert.autoClose}
        autoCloseTime={sweetAlert.autoCloseTime}
      />
    </div>
  );
};

export default AreaManagement;
