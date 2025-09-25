# Environment Variables Setup

## การตั้งค่า Environment Variables สำหรับโปรเจค Admin Panel

### 1. สร้างไฟล์ .env
คัดลอกไฟล์ `.env.example` และเปลี่ยนชื่อเป็น `.env`

```bash
cp .env.example .env
```

### 2. แก้ไข IP Address ใน .env
แก้ไข IP address ในไฟล์ `.env` ให้ตรงกับ XAMPP server ของคุณ:

```env
# ตัวอย่าง: ถ้า XAMPP ของคุณอยู่ที่ IP 192.168.1.100
VITE_API_BASE_URL=http://192.168.1.100/project
```

**หมายเหตุ**: ทุก API endpoints (admin_login.php, admin_get_users.php, ฯลฯ) จะถูกสร้างจาก `VITE_API_BASE_URL` อัตโนมัติ

### 3. วิธีหา IP ของ XAMPP
- เปิด Command Prompt
- พิมพ์ `ipconfig` 
- ดู IP Address ในส่วน "IPv4 Address"

### 4. การใช้งาน
หลังจากแก้ไขไฟล์ `.env` แล้ว:
1. รีสตาร์ท development server
2. ระบบจะใช้ IP ใหม่ที่คุณตั้งค่าไว้อัตโนมัติ

### 5. หมายเหตุ
- ไฟล์ `.env` จะไม่ถูก commit ลง Git เพื่อความปลอดภัย
- หากต้องการแชร์โปรเจค ให้ใช้ไฟล์ `.env.example` เป็นตัวอย่าง
- ต้องรีสตาร์ท development server ทุกครั้งหลังแก้ไข `.env`
