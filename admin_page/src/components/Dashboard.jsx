import React, { useState, useEffect } from "react";
import "./Dashboard.css";
import API_CONFIG from "../config/api.js";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  LineElement,
  PointElement,
  Filler,
} from "chart.js";
import { Bar, Doughnut, Line } from "react-chartjs-2";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  LineElement,
  PointElement,
  Filler
);

const Dashboard = () => {
  const [stats, setStats] = useState({
    totalUsers: 0,
    totalItems: 0,
    expiredItems: 0,
    activeItems: 0,
    nearExpiryItems: 0,
    noExpiryItems: 0,
    totalCategories: 0,
    totalAreas: 0,
  });

  const [monthlyNewUsers, setMonthlyNewUsers] = useState([]);
  const [typeStats, setTypeStats] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  // ...ลบ state ที่ไม่ได้ใช้แล้ว...

  // ดึงข้อมูลสถิติ item
  const fetchItemStats = async () => {
    try {
      const response = await fetch(API_CONFIG.ENDPOINTS.GET_ITEM_STATS);
      if (!response.ok) {
        console.warn("Item Stats API not available");
        return;
      }
      const data = await response.json();
      if (data.status === "success" && data.data) {
        // อัพเดท stats หลัก
        const statsData = data.data.total_stats || {};
        setStats((prev) => ({
          ...prev,
          totalItems: statsData.total_items || 0,
          activeItems: statsData.active_count || 0,
          expiredItems: statsData.expired_count || 0,
          usedUpItems: statsData.expired_status_count || 0,
          disposedItems: statsData.disposed_count || 0,
          nearExpiryItems: statsData.near_expiry_count || 0,
          noExpiryItems: statsData.no_expiry_count || 0,
        }));
        setTypeStats(data.data.type_stats || []);
        // รับข้อมูลรายการสิ่งของใหม่
        // ...ลบ setState ที่ไม่ได้ใช้แล้ว...
      }
    } catch (error) {
      console.warn("Item Stats API error:", error.message);
    }
  };

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError("");

        // ดึงข้อมูลผู้ใช้
        let usersArr = [];
        try {
          const usersResponse = await fetch(API_CONFIG.ENDPOINTS.GET_USERS);
          if (usersResponse.ok) {
            const usersData = await usersResponse.json();
            if (
              usersData.status === "success" &&
              Array.isArray(usersData.data)
            ) {
              usersArr = usersData.data;
              // นับจำนวนผู้ใช้แต่ละสถานะ
              let verified = 0,
                unverified = 0;
              usersArr.forEach((user) => {
                if (user.is_verified) verified++;
                else unverified++;
              });
              setStats((prev) => ({
                ...prev,
                totalUsers: usersData.total || 0,
                verifiedUsers: verified,
                unverifiedUsers: unverified,
              }));

              // สร้างข้อมูลเติบโตของผู้ใช้จาก registered_month (API)
              // 1. sort users ตาม registered_month, registered_at
              const sortedUsers = [...usersArr].sort((a, b) => {
                if (a.registered_month === b.registered_month) {
                  // ตรวจสอบว่า registered_at มีค่าและไม่เป็น null
                  if (!a.registered_at || !b.registered_at) {
                    return 0; // ถ้าไม่มีข้อมูลให้เรียงเท่ากัน
                  }
                  
                  // sort registered_at (dd/mm/yyyy hh:mm)
                  const [da, ma, ya] = a.registered_at.split(" ")[0].split("/");
                  const [db, mb, yb] = b.registered_at.split(" ")[0].split("/");
                  const dateA = new Date(`${ya}-${ma}-${da}`);
                  const dateB = new Date(`${yb}-${mb}-${db}`);
                  return dateA - dateB;
                }
                // ตรวจสอบว่า registered_month มีค่าและไม่เป็น null
                if (!a.registered_month || !b.registered_month) {
                  return 0;
                }
                return a.registered_month.localeCompare(b.registered_month);
              });
              // 2. สร้าง cumulative growth array
              const monthNames = [
                "มกราคม",
                "กุมภาพันธ์",
                "มีนาคม",
                "เมษายน",
                "พฤษภาคม",
                "มิถุนายน",
                "กรกฎาคม",
                "สิงหาคม",
                "กันยายน",
                "ตุลาคม",
                "พฤศจิกายน",
                "ธันวาคม",
              ];
              const monthMap = {};
              sortedUsers.forEach((user) => {
                if (user.registered_month) {
                  if (!monthMap[user.registered_month])
                    monthMap[user.registered_month] = 0;
                  monthMap[user.registered_month]++;
                }
              });
              // สร้าง array เรียงเดือน
              const monthsSorted = Object.keys(monthMap).sort();
              let cumulative = 0;
              const growthArr = monthsSorted.map((monthKey) => {
                cumulative += monthMap[monthKey];
                const [year, month] = monthKey.split("-");
                const monthName = monthNames[parseInt(month, 10) - 1] || month;
                return {
                  month_name: `${monthName} ${year}`,
                  cumulative,
                };
              });
              setMonthlyNewUsers(growthArr);
              
              // คำนวณการเติบโตจากเดือนที่แล้ว
              const currentDate = new Date();
              const currentMonth = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;
              const lastMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
              const lastMonthStr = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}`;
              
              const currentMonthUsers = monthMap[currentMonth] || 0;
              const lastMonthUsers = monthMap[lastMonthStr] || 0;
              const monthlyGrowth = currentMonthUsers - lastMonthUsers;
              
              setStats((prev) => ({
                ...prev,
                monthlyGrowth: monthlyGrowth,
                currentMonthUsers: currentMonthUsers,
                lastMonthUsers: lastMonthUsers
              }));
            }
          }
        } catch (error) {
          console.warn("Users API not available:", error.message);
        }

        try {
          const categoriesResponse = await fetch(
            API_CONFIG.ENDPOINTS.GET_CATEGORIES
          );
          if (categoriesResponse.ok) {
            const categoriesData = await categoriesResponse.json();
            setStats((prev) => ({
              ...prev,
              totalCategories: categoriesData.success
                ? categoriesData.categories.length
                : 0,
            }));
          }
        } catch (error) {
          console.warn("Categories API not available:", error.message);
        }

        // ดึงข้อมูลพื้นที่ (skip ถ้าไม่มี API)
        try {
          const areasResponse = await fetch(API_CONFIG.ENDPOINTS.GET_AREAS);
          if (areasResponse.ok) {
            const areasData = await areasResponse.json();
            setStats((prev) => ({
              ...prev,
              totalAreas: areasData.success ? areasData.areas.length : 0,
            }));
          }
        } catch (error) {
          console.warn("Areas API not available:", error.message);
        }

        // ดึงข้อมูลสถิติ item
        await fetchItemStats();
      } catch (error) {
        console.error("Error fetching dashboard data:", error);
        setError("เกิดข้อผิดพลาดในการโหลดข้อมูล");
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  const statusChartData = {
    labels: ["ใช้งานได้", "ใช้หมดแล้ว", "ทิ้งแล้ว"],
    datasets: [
      {
        data: [
          Number(stats.activeItems) || 0,
          Number(stats.usedUpItems) || 0,
          Number(stats.disposedItems) || 0
        ],
        backgroundColor: [
          "rgba(40, 167, 69, 0.8)",    // สีเขียวสำหรับ "ใช้งานได้"
          "rgba(220, 53, 69, 0.8)",    // สีแดงสำหรับ "ใช้หมดแล้ว" 
          "rgba(255, 193, 7, 0.8)",    // สีเหลืองสำหรับ "ทิ้งแล้ว"
        ],
        borderColor: [
          "rgba(40, 167, 69, 1)",      // สีเขียวสำหรับ "ใช้งานได้"
          "rgba(220, 53, 69, 1)",      // สีแดงสำหรับ "ใช้หมดแล้ว"
          "rgba(255, 193, 7, 1)",      // สีเหลืองสำหรับ "ทิ้งแล้ว"
        ],
        borderWidth: 2,
      },
    ],
  };

  const statusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "bottom",
        labels: {
          usePointStyle: true,
          padding: 20,
          font: {
            size: 12,
          },
        },
      },
      tooltip: {
        callbacks: {
          label: function (context) {
            // ใช้ข้อมูลจากกราฟโดยตรง
            const chartData = context.chart.data.datasets[0].data;
            const total = chartData.reduce((sum, value) => {
              const num = Number(value) || 0;
              return sum + num;
            }, 0);
            
            const currentValue = Number(context.parsed) || 0;
            const percentage = total > 0 ? ((currentValue * 100) / total).toFixed(1) : "0.0";
            
            return `${context.label}: ${currentValue} รายการ (${percentage}%)`;
          },
        },
      },
    },
  };

  // monthlyNewUsers: [{ month_name, cumulative }]
  const cumulativeArr = monthlyNewUsers.map((item) => item.cumulative);
  // ...ลบ allSame ที่ไม่ได้ใช้แล้ว...
  const monthlyUsersCumulativeChartData = {
    labels: monthlyNewUsers.map((item) => item.month_name),
    datasets: [
      {
        label: "การเติบโตของผู้ใช้",
        data: cumulativeArr,
        borderColor: "#2980b9",
        backgroundColor: "rgba(52, 152, 219, 0.1)",
        borderWidth: 4,
        fill: false,
        tension: 0.4,
        pointBackgroundColor: "#2980b9",
        pointBorderColor: "#fff",
        pointBorderWidth: 2,
        pointRadius: 8,
        pointHoverRadius: 12,
      },
    ],
  };

  const monthlyUsersChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        callbacks: {
          label: function (context) {
            return `${context.label}: ${context.parsed.y} คน`;
          },
        },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        min: 0,
        // max: Math.max(...monthlyNewUsers.map(item => item.cumulative), 10),
        ticks: {
          stepSize: 1,
        },
      },
    },
  };

  if (loading) {
    return (
      <div className="dashboard">
        <div className="loading-container">
          <div className="loading-spinner"></div>
          <p>กำลังโหลดข้อมูล...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="dashboard">
  

      {error && (
        <div className="error-message">
          <span className="error-icon">⚠️</span>
          <span>{error}</span>
        </div>
      )}

      {/* ส่วนข้อมูลผู้ใช้ พร้อมกราฟเปรียบเทียบผู้ใช้ใหม่รายเดือน */}
      <div className="section">
        <div className="section-header">
          <h3>👥 ข้อมูลผู้ใช้ระบบ</h3>
          <p>สถิติและข้อมูลผู้ใช้งานทั้งหมด</p>
        </div>
        <div className="stats-grid">
          <div className="stat-card">
            <div className="stat-icon users">👥</div>
            <div className="stat-content">
              <h3>{stats.totalUsers}</h3>
              <p>ผู้ใช้ทั้งหมด</p>
              {stats.monthlyGrowth !== undefined && (
                <div className="growth-indicator" style={{ 
                  fontSize: '12px', 
                  color: stats.monthlyGrowth >= 0 ? '#28a745' : '#dc3545',
                  marginTop: '4px',
                  fontWeight: 'bold'
                }}>
                  {stats.monthlyGrowth >= 0 ? '↗️' : '↘️'} 
                  {stats.monthlyGrowth >= 0 ? '+' : ''}{stats.monthlyGrowth} จากเดือนที่แล้ว
                </div>
              )}
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon verified">✅</div>
            <div className="stat-content">
              <h3>{stats.verifiedUsers || 0}</h3>
              <p>ยืนยันแล้ว</p>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon unverified">⏳</div>
            <div className="stat-content">
              <h3>{stats.unverifiedUsers || 0}</h3>
              <p>ยังไม่ยืนยัน</p>
            </div>
          </div>
        </div>
        <div
          className="chart-container"
          style={{ maxWidth: 600, margin: "32px auto 0" }}
        >
          <h3>การเติบโตของผู้ใช้</h3>
          <div className="chart-wrapper">
            {!loading &&
            monthlyNewUsers.length > 0 &&
            monthlyUsersCumulativeChartData.labels.length > 0 &&
            monthlyUsersCumulativeChartData.datasets[0].data.length > 0 ? (
              <Line
                data={monthlyUsersCumulativeChartData}
                options={monthlyUsersChartOptions}
              />
            ) : (
              <div className="chart-placeholder">
                <p>ไม่มีข้อมูลสำหรับแสดงกราฟ</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ส่วนข้อมูลสิ่งของและสิ่งของ พร้อมกราฟสถานะและประเภทสิ่งของยอดนิยม */}
      <div className="section">
        <div className="section-header">
          <h3>📦 ข้อมูลสิ่งของ</h3>
          <p>สถิติและสถานะของสิ่งของที่เก็บไว้ในระบบ</p>
        </div>
        <div className="stats-grid">
          <div className="stat-card">
            <div className="stat-icon items">📦</div>
            <div className="stat-content">
              <h3>{stats.totalItems}</h3>
              <p>สิ่งของทั้งหมด</p>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon active">✅</div>
            <div className="stat-content">
              <h3>{stats.activeItems || 0}</h3>
              <p>สิ่งของที่ยังไม่หมดอายุ</p>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon expired">⚠️</div>
            <div className="stat-content">
              <h3>{stats.usedUpItems || 0}</h3>
              <p>สิ่งของที่ใช้หมดแล้ว</p>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon disposed">🗑️</div>
            <div className="stat-content">
              <h3>{stats.disposedItems || 0}</h3>
              <p>สิ่งของที่ทิ้งแล้ว</p>
            </div>
          </div>
        </div>

        {/* กราฟสถานะสิ่งของและประเภทสิ่งของยอดนิยม */}
        <div className="charts-row">
          <div className="chart-container">
            <h3>สถานะสิ่งของ</h3>
            <div className="chart-wrapper">
              {!loading && stats.totalItems > 0 ? (
                <Doughnut data={statusChartData} options={statusChartOptions} />
              ) : (
                <div className="chart-placeholder">
                  <p>ไม่มีข้อมูลสำหรับแสดงกราฟ</p>
                </div>
              )}
            </div>
            {/* ตารางแยกหมวดหมู่แต่ละสถานะ (กรองข้อมูล 0 ออก) */}
            {!loading && typeStats.length > 0 && (
              <div style={{ marginTop: 24 }}>
                <h4 style={{ marginBottom: 16 }}>แยกตามหมวดหมู่</h4>
                {/* 3 กราฟวงกลมแถวเดียว: ปกติ, หมดอายุ, ใช้หมด */}
                <div style={{ display: "flex", gap: 16, flexWrap: "wrap" }}>
                  {/* กราฟสิ่งของปกติ (active) - กรองข้อมูลที่มีค่า > 0 */}
                  {(() => {
                    const activeTypeStats = typeStats.filter(
                      (type) => (type.active_count || 0) > 0
                    );
                    if (activeTypeStats.length === 0) return null;

                    return (
                      <div
                        style={{
                          flex: 1,
                          minWidth: 200,
                          background: "#f8f9fa",
                          borderRadius: 8,
                          padding: 12,
                        }}
                      >
                        <h5
                          style={{
                            textAlign: "center",
                            marginBottom: 8,
                            color: "#28a745",
                          }}
                        >
                          สิ่งของใช้งานได้
                        </h5>
                        <div style={{ height: 180 }}>
                          <Doughnut
                            data={{
                              labels: activeTypeStats.map(
                                (type) => type.type_name
                              ),
                              datasets: [
                                {
                                  data: activeTypeStats.map(
                                    (type) => Number(type.active_count) || 0
                                  ),
                                  backgroundColor: [
                                    "rgba(40, 167, 69, 0.8)",      // เขียวหลัก
                                    "rgba(46, 204, 113, 0.8)",     // เขียวอ่อน
                                    "rgba(22, 160, 133, 0.8)",     // เขียวเทอควอยซ์
                                    "rgba(39, 174, 96, 0.8)",      // เขียวแซฟไฟร์
                                    "rgba(26, 188, 156, 0.8)",     // เขียวมิ้นต์
                                    "rgba(85, 239, 196, 0.8)",     // เขียวนีออน
                                    "rgba(116, 185, 255, 0.8)",    // เขียวฟ้า
                                    "rgba(129, 236, 236, 0.8)",    // เขียวอะความารีน
                                  ],
                                  borderColor: [
                                    "rgba(40, 167, 69, 1)",        // เขียวหลัก
                                    "rgba(46, 204, 113, 1)",       // เขียวอ่อน
                                    "rgba(22, 160, 133, 1)",       // เขียวเทอควอยซ์
                                    "rgba(39, 174, 96, 1)",        // เขียวแซฟไฟร์
                                    "rgba(26, 188, 156, 1)",       // เขียวมิ้นต์
                                    "rgba(85, 239, 196, 1)",       // เขียวนีออน
                                    "rgba(116, 185, 255, 1)",      // เขียวฟ้า
                                    "rgba(129, 236, 236, 1)",      // เขียวอะความารีน
                                  ],
                                  borderWidth: 2,
                                },
                              ],
                            }}
                            options={{
                              responsive: true,
                              maintainAspectRatio: false,
                              plugins: {
                                legend: {
                                  position: "bottom",
                                  labels: {
                                    usePointStyle: true,
                                    padding: 8,
                                    font: { size: 9 },
                                  },
                                },
                                tooltip: {
                                  callbacks: {
                                    label: function (context) {
                                      // ใช้ข้อมูลที่แสดงในกราฟจริง (สิ่งของใช้งานได้)
                                      const chartData = context.chart.data.datasets[0].data;
                                      const total = chartData.reduce((sum, value) => {
                                        const num = Number(value) || 0;
                                        return sum + num;
                                      }, 0);
                                      const currentValue = Number(context.parsed) || 0;
                                      const percentage = total > 0 ? ((currentValue * 100) / total).toFixed(1) : "0.0";
                                      return `${context.label}: ${currentValue} รายการ (${percentage}%)`;
                                    },
                                  },
                                },
                              },
                            }}
                          />
                        </div>
                        {/* แสดงรายละเอียดด้านล่าง */}
                        <div
                          style={{
                            marginTop: 8,
                            fontSize: "11px",
                            color: "#666",
                          }}
                        >
                          {activeTypeStats.map((type, idx) => (
                            <div
                              key={idx}
                              style={{
                                display: "flex",
                                justifyContent: "space-between",
                                marginBottom: 2,
                              }}
                            >
                              <span>{type.type_name}</span>
                              <span>
                                <strong>{type.active_count}</strong>
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })()}

                  {/* กราฟสิ่งของหมดอายุ (expired) - กรองข้อมูลที่มีค่า > 0 */}
                  {(() => {
                    const expiredTypeStats = typeStats.filter(
                      (type) => (type.expired_count || 0) > 0
                    );
                    if (expiredTypeStats.length === 0) return null;

                    return (
                      <div
                        style={{
                          flex: 1,
                          minWidth: 200,
                          background: "#f8f9fa",
                          borderRadius: 8,
                          padding: 12,
                        }}
                      >
                        <h5
                          style={{
                            textAlign: "center",
                            marginBottom: 8,
                            color: "#dc3545",
                          }}
                        >
                          สิ่งของหมดอายุ
                        </h5>
                        <div style={{ height: 180 }}>
                          <Doughnut
                            data={{
                              labels: expiredTypeStats.map(
                                (type) => type.type_name
                              ),
                              datasets: [
                                {
                                  data: expiredTypeStats.map(
                                    (type) => Number(type.expired_count) || 0
                                  ),
                                  backgroundColor: [
                                    "rgba(220, 53, 69, 0.8)",      // แดงหลัก
                                    "rgba(231, 76, 60, 0.8)",      // แดงสด
                                    "rgba(192, 57, 43, 0.8)",      // แดงเข้ม
                                    "rgba(255, 99, 71, 0.8)",      // แดงส้ม
                                    "rgba(205, 92, 92, 0.8)",      // แดงอิฐ
                                    "rgba(178, 34, 34, 0.8)",      // แดงไฟ
                                    "rgba(139, 0, 0, 0.8)",        // แดงมาโรน
                                    "rgba(255, 69, 0, 0.8)",       // แดงส้มแก่
                                  ],
                                  borderColor: [
                                    "rgba(220, 53, 69, 1)",        // แดงหลัก
                                    "rgba(231, 76, 60, 1)",        // แดงสด
                                    "rgba(192, 57, 43, 1)",        // แดงเข้ม
                                    "rgba(255, 99, 71, 1)",        // แดงส้ม
                                    "rgba(205, 92, 92, 1)",        // แดงอิฐ
                                    "rgba(178, 34, 34, 1)",        // แดงไฟ
                                    "rgba(139, 0, 0, 1)",          // แดงมาโรน
                                    "rgba(255, 69, 0, 1)",         // แดงส้มแก่
                                  ],
                                  borderWidth: 2,
                                },
                              ],
                            }}
                            options={{
                              responsive: true,
                              maintainAspectRatio: false,
                              plugins: {
                                legend: {
                                  position: "bottom",
                                  labels: {
                                    usePointStyle: true,
                                    padding: 8,
                                    font: { size: 9 },
                                  },
                                },
                                tooltip: {
                                  callbacks: {
                                    label: function (context) {
                                      // ใช้ข้อมูลที่แสดงในกราฟจริง (สิ่งของหมดอายุ)
                                      const chartData = context.chart.data.datasets[0].data;
                                      const total = chartData.reduce((sum, value) => {
                                        const num = Number(value) || 0;
                                        return sum + num;
                                      }, 0);
                                      const currentValue = Number(context.parsed) || 0;
                                      const percentage = total > 0 ? ((currentValue * 100) / total).toFixed(1) : "0.0";
                                      return `${context.label}: ${currentValue} รายการ (${percentage}%)`;
                                    },
                                  },
                                },
                              },
                            }}
                          />
                        </div>
                        {/* แสดงรายละเอียดด้านล่าง */}
                        <div
                          style={{
                            marginTop: 8,
                            fontSize: "11px",
                            color: "#666",
                          }}
                        >
                          {expiredTypeStats.map((type, idx) => (
                            <div
                              key={idx}
                              style={{
                                display: "flex",
                                justifyContent: "space-between",
                                marginBottom: 2,
                              }}
                            >
                              <span>{type.type_name}</span>
                              <span>
                                <strong>{type.expired_count}</strong>
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })()}

                  {/* กราฟสิ่งของใช้หมด (disposed) - กรองข้อมูลที่มีค่า > 0 */}
                  {(() => {
                    const disposedTypeStats = typeStats.filter(
                      (type) => (type.disposed_count || 0) > 0
                    );
                    if (disposedTypeStats.length === 0) return null;

                    return (
                      <div
                        style={{
                          flex: 1,
                          minWidth: 200,
                          background: "#f8f9fa",
                          borderRadius: 8,
                          padding: 12,
                        }}
                      >
                        <h5
                          style={{
                            textAlign: "center",
                            marginBottom: 8,
                            color: "#ffc107",
                          }}
                        >
                          สิ่งของใช้หมด
                        </h5>
                        <div style={{ height: 180 }}>
                          <Doughnut
                            data={{
                              labels: disposedTypeStats.map(
                                (type) => type.type_name
                              ),
                              datasets: [
                                {
                                  data: disposedTypeStats.map(
                                    (type) => Number(type.disposed_count) || 0
                                  ),
                                  backgroundColor: [
                                    "rgba(255, 193, 7, 0.8)",      // เหลืองหลัก
                                    "rgba(241, 196, 15, 0.8)",     // เหลืองทอง
                                    "rgba(243, 156, 18, 0.8)",     // เหลืองส้ม
                                    "rgba(255, 215, 0, 0.8)",      // เหลืองทองคำ
                                    "rgba(255, 235, 59, 0.8)",     // เหลืองอ่อน
                                    "rgba(255, 152, 0, 0.8)",      // เหลืองส้มเข้ม
                                    "rgba(255, 193, 59, 0.8)",     // เหลืองผึ้ง
                                    "rgba(255, 183, 77, 0.8)",     // เหลืองครีม
                                  ],
                                  borderColor: [
                                    "rgba(255, 193, 7, 1)",        // เหลืองหลัก
                                    "rgba(241, 196, 15, 1)",       // เหลืองทอง
                                    "rgba(243, 156, 18, 1)",       // เหลืองส้ม
                                    "rgba(255, 215, 0, 1)",        // เหลืองทองคำ
                                    "rgba(255, 235, 59, 1)",       // เหลืองอ่อน
                                    "rgba(255, 152, 0, 1)",        // เหลืองส้มเข้ม
                                    "rgba(255, 193, 59, 1)",       // เหลืองผึ้ง
                                    "rgba(255, 183, 77, 1)",       // เหลืองครีม
                                  ],
                                  borderWidth: 2,
                                },
                              ],
                            }}
                            options={{
                              responsive: true,
                              maintainAspectRatio: false,
                              plugins: {
                                legend: {
                                  position: "bottom",
                                  labels: {
                                    usePointStyle: true,
                                    padding: 8,
                                    font: { size: 9 },
                                  },
                                },
                                tooltip: {
                                  callbacks: {
                                    label: function (context) {
                                      // ใช้ข้อมูลที่แสดงในกราฟจริง (สิ่งของใช้หมด)
                                      const chartData = context.chart.data.datasets[0].data;
                                      const total = chartData.reduce((sum, value) => {
                                        const num = Number(value) || 0;
                                        return sum + num;
                                      }, 0);
                                      const currentValue = Number(context.parsed) || 0;
                                      const percentage = total > 0 ? ((currentValue * 100) / total).toFixed(1) : "0.0";
                                      return `${context.label}: ${currentValue} รายการ (${percentage}%)`;
                                    },
                                  },
                                },
                              },
                            }}
                          />
                        </div>
                        {/* แสดงรายละเอียดด้านล่าง */}
                        <div
                          style={{
                            marginTop: 8,
                            fontSize: "11px",
                            color: "#666",
                          }}
                        >
                          {disposedTypeStats.map((type, idx) => (
                            <div
                              key={idx}
                              style={{
                                display: "flex",
                                justifyContent: "space-between",
                                marginBottom: 2,
                              }}
                            >
                              <span>{type.type_name}</span>
                              <span>
                                <strong>{type.disposed_count}</strong>
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })()}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* ...ลบส่วนแสดงรายการสิ่งของทั้งหมดออก... */}
      </div>
    </div>
  );
};

export default Dashboard;
