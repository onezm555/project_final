// นำเข้า API configuration
import API_CONFIG from '../config/api.js';

// กำหนด base URL ของ API จาก environment variable
const API_BASE_URL = API_CONFIG.BASE_URL;

// ฟังก์ชันสำหรับเรียก API
const apiCall = async (endpoint, options = {}) => {
  const url = `${API_BASE_URL}${endpoint}`;
  
  const defaultOptions = {
    headers: {
      'Content-Type': 'application/json',
    },
  };

  // เพิ่ม Authorization header ถ้ามี token
  const token = localStorage.getItem('admin_token');
  if (token && token !== 'admin_logged_in') {
    defaultOptions.headers.Authorization = `Bearer ${token}`;
  }

  const finalOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...options.headers,
    },
  };

  try {
    const response = await fetch(url, finalOptions);
    
    // ตรวจสอบสถานะ response
    if (!response.ok) {
      if (response.status === 401) {
        // Token หมดอายุหรือไม่ถูกต้อง
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user');
        window.location.reload();
        return;
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('API call error:', error);
    throw error;
  }
};

// API functions สำหรับการล็อกอิน
export const authAPI = {
  login: async (credentials) => {
    return apiCall('/admin_login.php', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },
  
  logout: async () => {
    return apiCall('/admin_logout.php', {
      method: 'POST',
    });
  },
  
  checkAuth: async () => {
    return apiCall('/admin_check_auth.php', {
      method: 'GET',
    });
  },
};

// API functions สำหรับการจัดการผู้ใช้
export const userAPI = {
  getUsers: async () => {
    return apiCall('/admin_get_users.php', {
      method: 'GET',
    });
  },
  
  deleteUser: async (userId) => {
    return apiCall('/admin_delete_user.php', {
      method: 'DELETE',
      body: JSON.stringify({ user_id: userId }),
    });
  },
  
  toggleUserStatus: async (userId, status) => {
    return apiCall('/admin_toggle_user_status.php', {
      method: 'PUT',
      body: JSON.stringify({ user_id: userId, is_verified: status }),
    });
  },
};

// API functions สำหรับการจัดการหมวดหมู่
export const categoryAPI = {
  getCategories: async () => {
    return apiCall('/admin_get_categories.php', {
      method: 'GET',
    });
  },
  
  addCategory: async (categoryData) => {
    return apiCall('/admin_add_category.php', {
      method: 'POST',
      body: JSON.stringify(categoryData),
    });
  },
  
  updateCategory: async (categoryId, categoryData) => {
    return apiCall('/admin_update_category.php', {
      method: 'PUT',
      body: JSON.stringify({ type_id: categoryId, ...categoryData }),
    });
  },
  
  deleteCategory: async (categoryId) => {
    return apiCall('/admin_delete_category.php', {
      method: 'DELETE',
      body: JSON.stringify({ type_id: categoryId }),
    });
  },
};

// API functions สำหรับการจัดการพื้นที่
export const areaAPI = {
  getAreas: async () => {
    return apiCall('/admin_get_areas.php', {
      method: 'GET',
    });
  },
  
  addArea: async (areaData) => {
    return apiCall('/admin_add_area.php', {
      method: 'POST',
      body: JSON.stringify(areaData),
    });
  },
  
  updateArea: async (areaId, areaData) => {
    return apiCall('/admin_update_area.php', {
      method: 'PUT',
      body: JSON.stringify({ area_id: areaId, ...areaData }),
    });
  },
  
  deleteArea: async (areaId) => {
    return apiCall('/admin_delete_area.php', {
      method: 'DELETE',
      body: JSON.stringify({ area_id: areaId }),
    });
  },
};

// API functions สำหรับดึงสถิติแดชบอร์ด
export const dashboardAPI = {
  getStats: async () => {
    return apiCall('/admin_get_stats.php', {
      method: 'GET',
    });
  },
  
  getRecentActivity: async () => {
    return apiCall('/admin_get_recent_activity.php', {
      method: 'GET',
    });
  },
};

export default apiCall;
