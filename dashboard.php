<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

$stats = [];
try {
    if ($user_role === 'admin') {
        $stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
        $stats['total_doctors'] = $db->query("SELECT COUNT(*) as count FROM doctors")->fetch()['count'];
        $stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch()['count'];
        $stats['total_revenue'] = $db->query("SELECT SUM(total_amount) as revenue FROM bills WHERE DATE(created_at) = CURDATE()")->fetch()['revenue'] ?? 0;
    }
} catch (Exception $e) {
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cliniva Admin Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #f5f7fa; }
    .dashboard-container { display: flex; min-height: 100vh; }

    .sidebar {
      width: 250px;
      background: #004685;
      color: white;
      padding: 20px 0;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
    }

    .sidebar-header {
      padding: 0 20px 20px;
      border-bottom: 1px solid #0066cc;
      margin-bottom: 20px;
    }

    .sidebar-header h2 { font-size: 20px; margin-bottom: 5px; }
    .sidebar-header p { font-size: 12px; opacity:  0.8; }

    .sidebar-menu { list-style: none; }
    .sidebar-menu li { margin-bottom: 5px; }
    .sidebar-menu a {
      display: block;
      padding: 12px 20px;
      color: white;
      text-decoration: none;
      transition: background 0.3s;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
      background: #0066cc;
    }

    .main-content {
      margin-left: 250px;
      flex: 1;
      padding: 20px;
    }

    .dashboard-header {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .logout-btn {
      background: #dc3545;
      color: white;
      padding: 8px 16px;
      border: none;
      border-radius: 5px;
      text-decoration: none;
      font-size: 14px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
    }

    .stat-card h3 {
      font-size: 32px;
      color: #004685;
      margin-bottom: 10px;
    }

    .quick-actions {
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    #chartRange {
      padding: 8px 12px;
      font-size: 14px;
      border-radius: 6px;
      border: 1px solid #ccc;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .sidebar { width: 100%; height: auto; position: relative; }
      .main-content { margin-left: 0; }
      .dashboard-header { flex-direction: column; gap: 15px; text-align: center; }
    }
  </style>
</head>
<body>
<div class="dashboard-container">
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>Hospital CRM</h2>
      <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
      <li><a href="patients.php">👥 Patients</a></li>
      <li><a href="doctors.php">👨‍⚕️ Doctors</a></li>
      <li><a href="appointments.php">📅 Appointments</a></li>
      <li><a href="billing.php">💰 Billing</a></li>
      <li><a href="reports.php">📊 Reports</a></li>
      <li><a href="settings.php">⚙️ Settings</a></li>
    </ul>
  </aside>
  <main class="main-content">
    <div class="dashboard-header">
      <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
      <div class="user-info">
        <span><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <h3><?php echo number_format($stats['total_patients'] ?? 0); ?></h3>
        <p>Total Patients</p>
      </div>
      <div class="stat-card">
        <h3><?php echo number_format($stats['total_doctors'] ?? 0); ?></h3>
        <p>Total Doctors</p>
      </div>
      <div class="stat-card">
        <h3><?php echo number_format($stats['total_appointments'] ?? 0); ?></h3>
        <p>Today's Appointments</p>
      </div>
      <div class="stat-card">
        <h3>₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
        <p>Today's Revenue</p>
      </div>
    </div>

    <div class="quick-actions">
      <h2>Quick Stats</h2>
      <select id="chartRange">
        <option value="7">Last 7 Days</option>
        <option value="30">Last 30 Days</option>
        <option value="90">Last 90 Days</option>
      </select>
      <canvas id="dashboardChart" height="100"></canvas>
    </div>
  </main>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById("dashboardChart").getContext("2d");
    let chartInstance;

    function fetchChartData(range = 7) {
      fetch(`ajax/fetch_chart_data.php?range=${range}`)
        .then(response => response.json())
        .then(data => {
          const labels = data.labels;
          const appointments = data.appointments;
          const revenue = data.revenue;

          if (chartInstance) chartInstance.destroy();

          chartInstance = new Chart(ctx, {
            type: "line",
            data: {
              labels: labels,
              datasets: [
                {
                  label: "Appointments",
                  data: appointments,
                  borderColor: "#004685",
                  backgroundColor: "rgba(0,70,133,0.1)",
                  fill: true
                },
                {
                  label: "Revenue (₹)",
                  data: revenue,
                  borderColor: "#28a745",
                  backgroundColor: "rgba(40,167,69,0.1)",
                  fill: true
                }
              ]
            },
            options: {
              responsive: true,
              plugins: {
                legend: {
                  display: true,
                  position: 'top'
                }
              },
              scales: {
                y: {
                  beginAtZero: true
                }
              }
            }
          });
        });
    }

    document.getElementById("chartRange").addEventListener("change", function () {
      fetchChartData(this.value);
    });

    fetchChartData(7);
  });
</script>

</body>
</html>
