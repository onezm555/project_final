// API Configuration
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/project';
const PROXY_URL = '/api'; // สำหรับ development โดยใช้ Vite proxy

// ใช้ proxy ใน development, ใช้ full URL ใน production
const API_BASE = import.meta.env.DEV ? PROXY_URL : `${BASE_URL}/admin_page`;

const API_CONFIG = {
  BASE_URL: BASE_URL,
  ENDPOINTS: {
    // สร้าง endpoint URLs จาก API_BASE อัตโนมัติ
    ADMIN_LOGIN: `${API_BASE}/admin_login.php`,
    ADMIN_LOGOUT: `${API_BASE}/admin_logout.php`,
    ADMIN_CHECK_AUTH: `${API_BASE}/admin_check_auth.php`,
    
    // User Management APIs
    GET_USERS: `${API_BASE}/admin_get_users.php`,
    DELETE_USER: `${API_BASE}/admin_delete_user.php`,
    TOGGLE_USER_STATUS: `${API_BASE}/admin_toggle_user_status.php`,
    
    // Category Management APIs
    GET_CATEGORIES: `${API_BASE}/admin_get_categories.php`,
    ADD_CATEGORY: `${API_BASE}/admin_add_category.php`,
    UPDATE_CATEGORY: `${API_BASE}/admin_update_category.php`,
    DELETE_CATEGORY: `${API_BASE}/admin_delete_category.php`,
    
    // Area Management APIs
    GET_AREAS: `${API_BASE}/admin_get_areas.php`,
    ADD_AREA: `${API_BASE}/admin_add_area.php`,
    UPDATE_AREA: `${API_BASE}/admin_update_area.php`,
    DELETE_AREA: `${API_BASE}/admin_delete_area.php`,
    
    // Dashboard APIs
    GET_STATS: `${API_BASE}/admin_get_stats.php`,
    GET_RECENT_ACTIVITY: `${API_BASE}/admin_get_recent_activity.php`,
    
    // Item Statistics APIs
    GET_ITEM_STATS: `${API_BASE}/admin_get_item_stats.php`,
  }
};

export default API_CONFIG;
