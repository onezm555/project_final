import React, { useState, useEffect } from 'react';
import './CategoryManagement.css';
import SweetAlert from './SweetAlert';

const CategoryManagement = () => {
  // State สำหรับจัดการข้อมูลหมวดหมู่
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(10);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [formData, setFormData] = useState({
    type_name: '',
    default_image: ''
  });
  const [selectedFile, setSelectedFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [previewUrl, setPreviewUrl] = useState('');
  const [customFileName, setCustomFileName] = useState(() => `${Date.now()}_default`);
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
  const fetchCategories = async () => {
    try {
      setLoading(true);
      setError('');
      const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_get_categories.php`);
      if (!response.ok) throw new Error('ไม่สามารถดึงข้อมูลได้');
      const data = await response.json();
      if (data.success) {
        console.log('Categories data:', data.categories); // Debug log
        setCategories(data.categories || []);
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
    fetchCategories();
  }, []);

  // ฟิลเตอร์หมวดหมู่ตามการค้นหา
  const filteredCategories = categories.filter(cat =>
    cat.type_name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // คำนวณ pagination
  const totalPages = Math.ceil(filteredCategories.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentCategories = filteredCategories.slice(startIndex, endIndex);

  // ฟังก์ชันสำหรับเปลี่ยนหน้า
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // รีเซ็ตหน้าเมื่อค้นหา
  const handleSearchChange = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
  };

  const handleAddCategory = () => {
    setEditMode(false);
    setFormData({ type_name: '', default_image: '' });
    setSelectedFile(null);
    setPreviewUrl('');
    setCustomFileName(() => `${Date.now()}_default`);
    setError(''); // ล้างข้อผิดพลาด
    setShowModal(true);
  };

  const handleEditCategory = (category) => {
    setEditMode(true);
    setSelectedCategory(category);
    setFormData({
      type_name: category.type_name,
      default_image: category.default_image
    });
    setSelectedFile(null);
    setPreviewUrl(category.default_image_url || '');
    setCustomFileName(() => `${Date.now()}_default`);
    setError(''); // ล้างข้อผิดพลาด
    setShowModal(true);
  };

  // ฟังก์ชันสำหรับจัดการการเลือกไฟล์
  const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
      // ตรวจสอบประเภทไฟล์
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        setError('รองรับเฉพาะไฟล์ JPG, PNG, GIF และ WebP เท่านั้น');
        return;
      }

      // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
      if (file.size > 5 * 1024 * 1024) {
        setError('ขนาดไฟล์ไม่ควรเกิน 5MB');
        return;
      }

      setSelectedFile(file);
      setError('');
      
      // สร้าง preview URL
      const reader = new FileReader();
      reader.onload = (e) => {
        setPreviewUrl(e.target.result);
      };
      reader.readAsDataURL(file);
    }
  };

  // ฟังก์ชันสำหรับอัปโหลดรูปภาพ
  const uploadImage = async () => {
    if (!selectedFile) return null;

    const uploadFormData = new FormData();
    uploadFormData.append('image', selectedFile);
    
    // เพิ่มชื่อไฟล์ที่ผู้ใช้กำหนด
    if (customFileName.trim()) {
      uploadFormData.append('filename', customFileName.trim());
    }

    try {
      setUploading(true);
      const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_upload_image.php`, {
        method: 'POST',
        body: uploadFormData
      });

      const result = await response.json();
      
      if (result.success) {
        return result.filename;
      } else {
        setError(result.message || 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ');
        return null;
      }
    } catch (error) {
      console.error('Upload error:', error);
      setError('ไม่สามารถอัปโหลดรูปภาพได้');
      return null;
    } finally {
      setUploading(false);
    }
  };

  const handleDeleteCategory = async (categoryId) => {
    // แสดง Sweet Alert สำหรับยืนยันการลบ
    showSweetAlert(
      'confirm',
      'ยืนยันการลบ',
      'คุณแน่ใจหรือไม่ที่จะลบหมวดหมู่นี้? การลบจะส่งผลกระทบต่อผู้ใช้ที่มีสิ่งของในหมวดหมู่นี้',
      {
        showCancelButton: true,
        confirmText: 'ลบ',
        cancelText: 'ยกเลิก',
        autoClose: false,
        onConfirm: async () => {
          try {
            const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_delete_category.php`, {
              method: 'DELETE',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({ type_id: categoryId })
            });

            const result = await response.json();

            // Log debug info ใน console
            console.log('Delete response:', result);
            if (result.debug_info) {
              console.log('=== DEBUG INFO ===');
              console.log('Available columns:', result.debug_info.available_columns);
              console.log('Image column found:', result.debug_info.found_image_column || result.debug_info.image_column_status);
              console.log('Category data:', result.debug_info.category_data);
              console.log('Image filename:', result.debug_info.image_filename);
              if (result.debug_info.checked_paths) {
                console.log('Checked paths:', result.debug_info.checked_paths);
              }
              console.log('Delete result:', result.debug_info.result);
              console.log('==================');
            }

            if (result.success) {
              // ลบหมวดหมู่ออกจาก state
              setCategories(categories.filter(cat => cat.type_id !== categoryId));
              
              // แสดง Sweet Alert สำเร็จ
              showSweetAlert(
                'success',
                'ลบสำเร็จ!',
                result.message || 'ลบหมวดหมู่เรียบร้อยแล้ว'
              );
            } else {
              // ตรวจสอบว่าเป็นข้อผิดพลาดเรื่องการใช้งานหรือไม่
              if (result.message.includes('ผู้ใช้มีสิ่งของ') && result.message.includes('รายการในหมวดหมู่นี้')) {
                showSweetAlert(
                  'error',
                  'เกิดข้อผิดพลาด!',
                  'ไม่สามารถลบได้เนื่องจากผู้ใช้มีสิ่งของในหมวดหมู่นี้'
                );
              } else {
                showSweetAlert(
                  'error',
                  'เกิดข้อผิดพลาด',
                  result.message || 'เกิดข้อผิดพลาดในการลบหมวดหมู่'
                );
              }
            }
          } catch (error) {
            console.error('Delete error:', error);
            showSweetAlert(
              'error',
              'เกิดข้อผิดพลาด',
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
      let imageFilename = formData.default_image;
      
      // อัปโหลดรูปภาพใหม่หากมีการเลือกไฟล์
      if (selectedFile) {
        imageFilename = await uploadImage();
        if (!imageFilename) {
          return; // หยุดการดำเนินการหากอัปโหลดไม่สำเร็จ
        }
      }
      
      if (editMode) {
        // อัปเดตหมวดหมู่ผ่าน API
        const updateData = {
          type_id: selectedCategory.type_id,
          type_name: formData.type_name,
          default_image: imageFilename || null
        };
        
        const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_update_category.php`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(updateData)
        });
        
        const result = await response.json();
        
        if (result.success) {
          // อัปเดต state ด้วยข้อมูลที่ได้จาก API
          setCategories(categories.map(cat =>
            cat.type_id === selectedCategory.type_id
              ? result.category
              : cat
          ));
          
          // แสดง Sweet Alert สำเร็จ
          showSweetAlert(
            'success',
            'แก้ไขสำเร็จ!',
            result.message || 'แก้ไขหมวดหมู่เรียบร้อยแล้ว'
          );
        } else {
          showSweetAlert(
            'error',
            'เกิดข้อผิดพลาด',
            result.message || 'เกิดข้อผิดพลาดในการแก้ไขหมวดหมู่'
          );
          return; // ไม่ปิด modal หากเกิดข้อผิดพลาด
        }
      } else {
        // เพิ่มหมวดหมู่ใหม่ผ่าน API
        const addData = {
          type_name: formData.type_name,
          default_image: imageFilename || 'default.png'
        };
        
        const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/admin_add_category.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(addData)
        });
        
        const result = await response.json();
        
        if (result.success) {
          // เพิ่มหมวดหมู่ใหม่ลงใน state
          setCategories([...categories, result.category]);
          
          // แสดง Sweet Alert สำเร็จ
          showSweetAlert(
            'success',
            'เพิ่มสำเร็จ!',
            result.message || 'เพิ่มหมวดหมู่เรียบร้อยแล้ว'
          );
        } else {
          showSweetAlert(
            'error',
            'เกิดข้อผิดพลาด',
            result.message || 'เกิดข้อผิดพลาดในการเพิ่มหมวดหมู่'
          );
          return; // ไม่ปิด modal หากเกิดข้อผิดพลาด
        }
      }
      
      setShowModal(false);
      setFormData({ type_name: '', default_image: '' });
      setSelectedFile(null);
      setPreviewUrl('');
      setCustomFileName(() => `${Date.now()}_default`);
    } catch (error) {
      console.error('Submit error:', error);
      showSweetAlert(
        'error',
        'เกิดข้อผิดพลาด',
        'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
      );
    }
  };


  return (
    <div className="category-management">
      <div className="header">
        <h2>จัดการหมวดหมู่สิ่งของ</h2>
        <div className="header-actions">
          <input
            type="text"
            className="search-input"
            placeholder="🔍 ค้นหาหมวดหมู่..."
            value={searchTerm}
            onChange={handleSearchChange}
          />
          <button className="btn-add" onClick={handleAddCategory}>
            + เพิ่มหมวดหมู่ใหม่
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
          <p>กำลังโหลดข้อมูลหมวดหมู่...</p>
        </div>
      ) : (
        <div className="categories-table-wrapper">
          <table className="categories-table">
            <thead>
              <tr>
                <th style={{width: '60px'}}>ลำดับ</th>
                <th>ชื่อหมวดหมู่</th>
                <th style={{width: '120px'}}>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              {currentCategories.map((category, idx) => (
                <tr key={category.type_id}>
                  <td>{startIndex + idx + 1}</td>
                  <td>{category.type_name}</td>
                  <td>
                    <div className="action-buttons">
                      <button 
                        className="btn-edit"
                        onClick={() => handleEditCategory(category)}
                        title="แก้ไข"
                      >
                        ✏️
                      </button>
                      <button 
                        className="btn-delete"
                        onClick={() => handleDeleteCategory(category.type_id)}
                        title="ลบ"
                      >
                        🗑️
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filteredCategories.length === 0 && !loading && (
                <tr>
                  <td colSpan="3">
                    <div className="empty-state">
                      <div className="empty-icon">📭</div>
                      <h3>ไม่พบหมวดหมู่</h3>
                      <p>
                        {searchTerm 
                          ? `ไม่พบหมวดหมู่ที่ตรงกับ "${searchTerm}"`
                          : 'ยังไม่มีหมวดหมู่ในระบบ'
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
      {filteredCategories.length > 0 && (
        <div className="pagination-wrapper">
          <div className="pagination-info">
            แสดง {startIndex + 1}-{Math.min(endIndex, filteredCategories.length)} จาก {filteredCategories.length} รายการ
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

      {showModal && (
        <div className="modal-overlay" onClick={() => {
          setShowModal(false);
          setError(''); // ล้างข้อผิดพลาดเมื่อปิด modal
          setSelectedFile(null);
          setPreviewUrl('');
          setCustomFileName(() => `${Date.now()}_default`);
        }}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{editMode ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่'}</h3>
              <button className="close-btn" onClick={() => {
                setShowModal(false);
                setError(''); // ล้างข้อผิดพลาดเมื่อปิด modal
                setSelectedFile(null);
                setPreviewUrl('');
                setCustomFileName(() => `${Date.now()}_default`);
              }}>×</button>
            </div>
            <div className="modal-body">
              {error && (
                <div className="alert alert-error" style={{ marginBottom: '1rem' }}>
                  <span className="alert-icon">⚠️</span>
                  <span className="alert-message">{error}</span>
                </div>
              )}
              <form onSubmit={handleSubmit}>
                <div className="form-group">
                  <label htmlFor="type_name">ชื่อหมวดหมู่ *</label>
                  <input
                    type="text"
                    id="type_name"
                    value={formData.type_name}
                    onChange={(e) => setFormData({...formData, type_name: e.target.value})}
                    placeholder="กรอกชื่อหมวดหมู่"
                    required
                  />
                </div>
                
                <div className="form-group">
                  <label>รูปภาพหมวดหมู่</label>
                  
                  {/* Preview รูปภาพ */}
                  {previewUrl && (
                    <div style={{ marginBottom: '1rem' }}>
                      <img 
                        src={previewUrl} 
                        alt="Preview" 
                        style={{ 
                          width: '100px', 
                          height: '100px', 
                          objectFit: 'cover', 
                          borderRadius: '8px',
                          border: '2px solid #ddd'
                        }} 
                      />
                    </div>
                  )}
                  
                  {/* Input สำหรับเลือกไฟล์ */}
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleFileSelect}
                    style={{ marginBottom: '0.5rem' }}
                  />
                  
                  {/* Input สำหรับตั้งชื่อไฟล์ */}
                  {selectedFile && (
                    <div style={{ marginTop: '0.5rem', marginBottom: '0.5rem' , display:'none'}}>
                      <label htmlFor="customFileName" style={{ fontSize: '0.9rem', fontWeight: 'bold' }}>
                        ตั้งชื่อไฟล์ (ไม่ต้องใส่นามสกุล):
                      </label>
                      <input
                        type="text"
                        id="customFileName"
                        value={customFileName}
                        style={{ 
                          width: '100%', 
                          padding: '0.5rem', 
                          marginTop: '0.25rem',
                          border: '1px solid #ddd',
                          borderRadius: '4px'
                        }}
                      />
                      <small style={{ display: 'block', color: '#666', marginTop: '0.25rem' }}>
                        หากไม่ระบุ จะใช้ชื่อไฟล์แบบอัตโนมัติ
                      </small>
                    </div>
                  )}
                  
                  <small style={{ display: 'block', color: '#666', marginBottom: '1rem' }}>
                    รองรับไฟล์ JPG, PNG, GIF, WebP (ขนาดไม่เกิน 5MB)
                  </small>
                </div>

                <div className="form-actions">
                  <button type="button" className="btn-cancel" onClick={() => {
                    setShowModal(false);
                    setError(''); // ล้างข้อผิดพลาดเมื่อยกเลิก
                    setSelectedFile(null);
                    setPreviewUrl('');
                    setCustomFileName(() => `${Date.now()}_default`);
                  }}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn-submit" disabled={uploading}>
                    {uploading ? (
                      <>
                        <span>🔄</span> กำลังอัปโหลด...
                      </>
                    ) : (
                      editMode ? 'บันทึกการแก้ไข' : 'เพิ่มหมวดหมู่'
                    )}
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

export default CategoryManagement;