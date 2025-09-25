# Admin Panel - ระบบจัดการแอดมิน

ระบบจัดการแอดมินสำหรับการจัดการผู้ใช้ หมวดหมู่ และพื้นที่ในระบบ

## ฟีเจอร์หลัก

### 🔐 ระบบการเข้าสู่ระบบ
- การล็อกอินด้วยอีเมลและรหัสผ่าน
- การจัดการ Authentication ด้วย JWT Token
- การออกจากระบบอย่างปลอดภัย

### 📊 แดชบอร์ด
- สถิติภาพรวมของระบบ
- แสดงจำนวนผู้ใช้ สิ่งของ และข้อมูลสำคัญ
- กราฟแสดงสถานะสิ่งของ
- กิจกรรมล่าสุดในระบบ

### 👥 จัดการผู้ใช้
- ดูรายชื่อผู้ใช้ทั้งหมด
- ค้นหาผู้ใช้ตามชื่อ อีเมล หรือเบอร์โทร
- เปิด/ปิดการใช้งานบัญชีผู้ใช้
- ลบผู้ใช้ออกจากระบบ
- ดูรายละเอียดผู้ใช้

### 📋 จัดการหมวดหมู่
- เพิ่ม แก้ไข ลบหมวดหมู่สิ่งของ
- กำหนดรูปภาพเริ่มต้นสำหรับหมวดหมู่
- แสดงไอคอนที่เหมาะสมกับแต่ละหมวดหมู่

### 🏠 จัดการพื้นที่
- เพิ่ม แก้ไข ลบพื้นที่เก็บสิ่งของ
- กำหนดเจ้าของพื้นที่ (ส่วนกลางหรือส่วนตัว)
- ค้นหาพื้นที่ตามชื่อ
- แสดงไอคอนที่เหมาะสมกับแต่ละพื้นที่

## การติดตั้งและรันโปรเจค

### ข้อกำหนดระบบ
- Node.js (เวอร์ชัน 16 หรือใหม่กว่า)
- npm หรือ yarn
- เซิร์ฟเวอร์ PHP สำหรับ Backend API

### การติดตั้ง
1. Clone โปรเจค
```bash
git clone <repository-url>
cd admin_page
```

2. ติดตั้ง dependencies
```bash
npm install
```

3. รันโปรเจคในโหมดพัฒนา
```bash
npm run dev
```

4. เปิดเบราว์เซอร์ไปที่ `http://localhost:5173`

## โครงสร้างโปรเจค

```
src/
├── components/           # React Components
│   ├── Dashboard.jsx     # หน้าแดชบอร์ด
│   ├── Login.jsx         # หน้าล็อกอิน
│   ├── Header.jsx        # Header ส่วนบน
│   ├── Sidebar.jsx       # เมนูด้านข้าง
│   ├── UserManagement.jsx      # จัดการผู้ใช้
│   ├── CategoryManagement.jsx  # จัดการหมวดหมู่
│   ├── AreaManagement.jsx      # จัดการพื้นที่
│   └── *.css            # ไฟล์ CSS สำหรับแต่ละ component
├── context/             # React Context
│   └── AuthContext.jsx  # จัดการสถานะการล็อกอิน
├── utils/               # Utility functions
│   └── api.js          # ฟังก์ชันเรียก API
├── App.jsx             # Component หลัก
├── App.css             # CSS หลัก
└── main.jsx            # Entry point
```

## การเชื่อมต่อ API

ระบบนี้เชื่อมต่อกับ PHP Backend API ที่ URL: `http://10.10.35.5/project/`

### API Endpoints ที่ใช้:
- `POST /admin_login.php` - เข้าสู่ระบบ
- `POST /admin_logout.php` - ออกจากระบบ
- `GET /admin_get_users.php` - ดึงข้อมูลผู้ใช้
- `GET /admin_get_categories.php` - ดึงข้อมูลหมวดหมู่
- `GET /admin_get_areas.php` - ดึงข้อมูลพื้นที่
- `GET /admin_get_stats.php` - ดึงสถิติแดชบอร์ด

## ฟีเจอร์เพิ่มเติม

### 🎨 UI/UX
- Responsive Design ที่ใช้งานได้ทุกขนาดหน้าจอ
- Sidebar ที่สามารถย่อ/ขยายได้
- Loading states และ Error handling
- Smooth animations และ transitions

### 🔒 ความปลอดภัย
- Token-based authentication
- Automatic token refresh
- Session management
- Input validation

### 📱 Responsive Design
- รองรับการใช้งานบนมือถือ
- Layout ปรับตัวตามขนาดหน้าจอ
- Touch-friendly interface

## การปรับแต่ง

### เปลี่ยน API URL
แก้ไขไฟล์ `src/utils/api.js`:
```javascript
const API_BASE_URL = 'http://your-api-url/project';
```

### เปลี่ยนสี Theme
แก้ไขไฟล์ CSS ในส่วน CSS Variables หรือ gradient colors

### เพิ่ม Menu Item ใหม่
แก้ไขไฟล์ `src/components/Sidebar.jsx` ในส่วน `menuItems` array

## การ Deploy

### สำหรับ Production
1. Build โปรเจค
```bash
npm run build
```

2. ไฟล์ที่ build เสร็จจะอยู่ในโฟลเดอร์ `dist/`

3. Upload ไฟล์ในโฟลเดอร์ `dist/` ไปยังเซิร์ฟเวอร์

## การแก้ไขปัญหา

### ปัญหาการเชื่อมต่อ API
- ตรวจสอบ CORS settings ในเซิร์ฟเวอร์ PHP
- ตรวจสอบ URL ของ API ใน `src/utils/api.js`

### ปัญหา Authentication
- ลบ localStorage และรีเฟรชหน้า
- ตรวจสอบ Token ใน Developer Tools

## ข้อมูลการติดต่อ

หากมีปัญหาหรือต้องการความช่วยเหลือ กรุณาติดต่อทีมพัฒนา+ Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.
