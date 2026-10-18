<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php"; // MySQLi connection for legacy tables

if (!isset($pdo)) {
    die("Database connection failed.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
 
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .header h1 {
            color: var(--primary);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* KPI Cards */
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        
        .kpi-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .kpi-icon.patients {
            background: var(--success);
        }
        
        .kpi-icon.appointments {
            background: var(--secondary);
        }
        
        .kpi-icon.admissions {
            background: var(--warning);
        }
        
        .kpi-icon.revenue {
            background: var(--danger);
        }
        
        .kpi-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .kpi-info p {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Charts and Tables */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .chart-container, .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .chart-container h2, .table-container h2 {
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status.scheduled {
            background: #e3f2fd;
            color: var(--secondary);
        }
        
        .status.completed {
            background: #e8f5e9;
            color: var(--success);
        }
        
        .status.cancelled {
            background: #ffebee;
            color: var(--danger);
        }
        
        .status.active {
            background: #fff3e0;
            color: var(--warning);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2, .sidebar-menu span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar-header {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .kpi-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <!-- <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-hospital-alt fa-2x"></i>
            <h2>MediCare HMS</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li class="active"><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="#"><i class="fas fa-user-injured"></i> <span>Patients</span></a></li>
                <li><a href="#"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a></li>
                <li><a href="#"><i class="fas fa-procedures"></i> <span>Admissions</span></a></li>
                <li><a href="#"><i class="fas fa-file-invoice-dollar"></i> <span>Billing</span></a></li>
                <li><a href="#"><i class="fas fa-flask"></i> <span>Laboratory</span></a></li>
                <li><a href="#"><i class="fas fa-pills"></i> <span>Pharmacy</span></a></li>
                <li><a href="#"><i class="fas fa-user-md"></i> <span>Staff</span></a></li>
                <li><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
        </div>
    </div> -->

    <!-- Main Content -->
    <div class="main-content" style="margin-left: 250px;">
        <div class="header">
            <h1>Hospital Management Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar">VC</div>
                <div>
                    <p>Vivian Chepchumba</p>
                    <small>Super Admin</small>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon patients">
                    <i class="fas fa-user-injured"></i>
                </div>
                <div class="kpi-info">
                    <h3 id="totalPatients">12</h3>
                    <p>Total Patients</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="kpi-info">
                    <h3 id="todayAppointments">5</h3>
                    <p>Today's Appointments</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon admissions">
                    <i class="fas fa-procedures"></i>
                </div>
                <div class="kpi-info">
                    <h3 id="currentAdmissions">1</h3>
                    <p>Current Admissions</p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="kpi-info">
                    <h3 id="monthlyRevenue">KSh 23,300</h3>
                    <p>Monthly Revenue</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Patient Demographics Chart -->
                <!-- <div class="chart-container">
                    <h2>Patient Demographics</h2>
                    <canvas id="patientDemographicsChart"></canvas>
                </div> -->

                <!-- Department Performance -->
                <div class="chart-container">
                    <h2>Department Performance</h2>
                    <canvas id="departmentChart"></canvas>
                </div>

                <!-- Recent Appointments -->
                <div class="table-container">
                    <h2>Today's Appointments</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTable">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Revenue by Service Type -->
                <div class="chart-container">
                    <h2>Revenue by Service Type</h2>
                    <canvas id="revenueChart"></canvas>
                </div>

                <!-- Current Admissions -->
                <div class="table-container">
                    <h2>Current Admissions</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Ward</th>
                                <th>Bed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="admissionsTable">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Recent Lab Results -->
                <div class="table-container">
                    <h2>Recent Lab Results</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="labResultsTable">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sample data based on your database
        const dashboardData = {
            totalPatients: 12,
            todayAppointments: 5,
            currentAdmissions: 1,
            monthlyRevenue: 23300,
            
            patientDemographics: {
                gender: {
                    labels: ['Female', 'Male'],
                    data: [8, 4] // Based on your patient data
                },
                ageGroups: {
                    labels: ['Pediatric (0-17)', 'Young Adult (18-35)', 'Adult (36-55)', 'Senior (55+)'],
                    data: [2, 5, 4, 1] // Estimated from your data
                }
            },
            
            departmentPerformance: {
                labels: ['Cardiology', 'Emergency', 'Neurology', 'Pediatrics', 'Orthopedics', 'General Care'],
                appointments: [3, 2, 4, 2, 2, 3],
                completed: [2, 1, 3, 2, 1, 3]
            },
            
            revenueByService: {
                labels: ['Consultation', 'Lab Tests', 'Bed Charges', 'Procedures', 'Other'],
                data: [4500, 10450, 7000, 15000, 500] // Based on your billing data
            },
            
            todaysAppointments: [
                { time: '10:15', patient: 'Alpha Mwirigi', doctor: 'Dr. Cate', type: 'Consultation', status: 'scheduled' },
                { time: '11:30', patient: 'Alpha Mwirigi', doctor: 'Dr. Job', type: 'Procedure', status: 'confirmed' },
                { time: '14:00', patient: 'Phoebe Ndalo', doctor: 'Dr. Dana', type: 'Consultation', status: 'completed' },
                { time: '15:30', patient: 'Susan Chemutai', doctor: 'Dr. Collins', type: 'Consultation', status: 'completed' },
                { time: '17:45', patient: 'Valery Odeku', doctor: 'Dr. Collins', type: 'Consultation', status: 'completed' }
            ],
            
            currentAdmissions: [
                { patient: 'Mary Njeri', ward: 'VIP Ward', bed: 'BED-001', status: 'active' }
            ],
            
            recentLabResults: [
                { patient: 'Mary Njeri', test: 'Blood Test', status: 'Completed', date: '2025-11-02' },
                { patient: 'Phoebe Ndalo', test: 'Blood Test', status: 'Completed', date: '2025-11-02' },
                { patient: 'Christopher Mwasi', test: 'Uric Acid Test', status: 'Completed', date: '2025-10-31' },
                { patient: 'Susan Chemutai', test: 'Urine Analysis', status: 'Completed', date: '2025-10-30' }
            ]
        };

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Patient Demographics Chart
            // const demographicsCtx = document.getElementById('patientDemographicsChart').getContext('2d');
            // new Chart(demographicsCtx, {
            //     type: 'doughnut',
            //     data: {
            //         labels: dashboardData.patientDemographics.gender.labels,
            //         datasets: [{
            //             data: dashboardData.patientDemographics.gender.data,
            //             backgroundColor: ['#3498db', '#2ecc71']
            //         }]
            //     },
            //     options: {
            //         responsive: true,
            //         plugins: {
            //             legend: {
            //                 position: 'bottom'
            //             }
            //         }
            //     }
            // });

            // Department Performance Chart
            const departmentCtx = document.getElementById('departmentChart').getContext('2d');
            new Chart(departmentCtx, {
                type: 'bar',
                data: {
                    labels: dashboardData.departmentPerformance.labels,
                    datasets: [
                        {
                            label: 'Total Appointments',
                            data: dashboardData.departmentPerformance.appointments,
                            backgroundColor: '#3498db'
                        },
                        {
                            label: 'Completed',
                            data: dashboardData.departmentPerformance.completed,
                            backgroundColor: '#2ecc71'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'pie',
                data: {
                    labels: dashboardData.revenueByService.labels,
                    datasets: [{
                        data: dashboardData.revenueByService.data,
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Populate Tables
            populateTable('appointmentsTable', dashboardData.todaysAppointments, 
                item => `<tr>
                    <td>${item.time}</td>
                    <td>${item.patient}</td>
                    <td>${item.doctor}</td>
                    <td>${item.type}</td>
                    <td><span class="status ${item.status}">${item.status}</span></td>
                </tr>`
            );

            populateTable('admissionsTable', dashboardData.currentAdmissions,
                item => `<tr>
                    <td>${item.patient}</td>
                    <td>${item.ward}</td>
                    <td>${item.bed}</td>
                    <td><span class="status ${item.status}">${item.status}</span></td>
                </tr>`
            );

            populateTable('labResultsTable', dashboardData.recentLabResults,
                item => `<tr>
                    <td>${item.patient}</td>
                    <td>${item.test}</td>
                    <td><span class="status completed">${item.status}</span></td>
                    <td>${item.date}</td>
                </tr>`
            );

            // Update KPI values
            document.getElementById('totalPatients').textContent = dashboardData.totalPatients;
            document.getElementById('todayAppointments').textContent = dashboardData.todayAppointments;
            document.getElementById('currentAdmissions').textContent = dashboardData.currentAdmissions;
            document.getElementById('monthlyRevenue').textContent = `KSh ${dashboardData.monthlyRevenue.toLocaleString()}`;
        });

        // Helper function to populate tables
        function populateTable(tableId, data, rowTemplate) {
            const tableBody = document.getElementById(tableId);
            tableBody.innerHTML = data.map(item => rowTemplate(item)).join('');
        }

        // Simulate real-time updates
        function simulateDataUpdates() {
            // This would be replaced with actual API calls in a real application
            setInterval(() => {
                // Randomly update patient count (simulating new registrations)
                const newPatientCount = dashboardData.totalPatients + Math.floor(Math.random() * 2);
                if (newPatientCount !== dashboardData.totalPatients) {
                    dashboardData.totalPatients = newPatientCount;
                    document.getElementById('totalPatients').textContent = newPatientCount;
                }

                // Randomly update revenue (simulating new billing)
                const revenueChange = Math.floor(Math.random() * 1000) - 200;
                const newRevenue = Math.max(0, dashboardData.monthlyRevenue + revenueChange);
                if (newRevenue !== dashboardData.monthlyRevenue) {
                    dashboardData.monthlyRevenue = newRevenue;
                    document.getElementById('monthlyRevenue').textContent = `KSh ${newRevenue.toLocaleString()}`;
                }
            }, 10000); // Update every 10 seconds
        }

        // Start simulating data updates
        simulateDataUpdates();
    </script>
</body>
</html>