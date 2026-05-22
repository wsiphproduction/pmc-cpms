<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-light: #f8fafc;
            --sidebar-white: #ffffff;
            --accent-blue: #2563eb;
            --border-color: #e2e8f0;
            --text-dark: #1e293b;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--text-dark); }

        /* Sidebar & Layout */
        #sidebar { width: 260px; height: 100vh; position: fixed; background: var(--sidebar-white); border-right: 1px solid var(--border-color); z-index: 1000; }
        .sidebar-header { padding: 20px; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); color: var(--accent-blue); }
        .nav-link { color: #64748b; padding: 12px 20px; display: flex; align-items: center; font-weight: 500; border-radius: 8px; margin: 4px 12px; text-decoration: none; cursor: pointer; }
        .nav-link.active { background-color: #f1f5f9; color: var(--accent-blue); }
        .submenu { list-style: none; padding-left: 0; display: none; background: #fafafa; }
        .submenu .nav-link { padding-left: 50px; font-size: 0.85rem; }

        #main-content { margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; }
        .top-bar { background: #fff; padding: 12px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; position: relative; overflow: hidden; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .stat-icon { position: absolute; right: 15px; top: 15px; font-size: 2rem; opacity: 0.1; color: var(--cpms-primary); }
        .stat-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-bottom: 0; }
        .stat-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        .quick-link-btn { background: white; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 15px; text-align: center; color: #475569; text-decoration: none; transition: 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .quick-link-btn:hover { background: #f1f5f9; border-color: var(--cpms-primary); color: var(--cpms-primary); }
        .quick-link-btn i { font-size: 1.5rem; margin-bottom: 8px; }

        .card-header-cpms { background: white; border-bottom: 1px solid #f1f5f9; padding: 15px 20px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; }
        
        /* Status Badges */
        .badge-critical { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .badge-delayed { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        
        .progress-micro { height: 6px; border-radius: 10px; background-color: #f1f5f9; margin-top: 5px; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">
    <?php include("header.php");?>

    <div class="container-fluid px-4 py-4">
        <h4 class="fw-bold mb-4">Dashboard</h4>

        <h6 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Launchpad</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <a href="request_add.php" class="quick-link-btn">
                        <i class="bi bi-plus-circle-dotted"></i>
                        <span class="small fw-bold">New Request</span>
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="project_addnew.php" class="quick-link-btn">
                        <i class="bi bi-folder-plus"></i>
                        <span class="small fw-bold">Create Project</span>
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="#" class="quick-link-btn">
                        <i class="bi bi-file-earmark-pdf"></i>
                        <span class="small fw-bold">Export Report</span>
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="#" class="quick-link-btn">
                        <i class="bi bi-people"></i>
                        <span class="small fw-bold">Assign PM</span>
                    </a>
                </div>
                <div class="col-md-4">
                    <div class="stat-card bg-warning text-white border-0">
                        <div class="stat-icon text-black"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-label text-black-50">Project Completion KPI</div>
                        <div class="stat-value text-white">90%</div>
                        <div class=" mt-1 text-white-50 ">Actual: <span class="text-danger text-bold">68%</span></div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-briefcase"></i></div>
                        <div class="stat-label">Active Projects</div>
                        <div class="stat-value">24</div>
                        <div class="small text-success fw-bold"><i class="bi bi-arrow-up"></i> 12% vs last month</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-label">Critical / Delayed</div>
                        <div class="stat-value text-danger">07</div>
                        <div class="small text-muted fw-bold">Requires Attention</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                        <div class="stat-label">About to Lapse</div>
                        <div class="stat-value">03</div>
                        <div class="small text-warning fw-bold">Due within 7 days</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-envelope-paper"></i></div>
                        <div class="stat-label">Pending Requests</div>
                        <div class="stat-value">12</div>
                        <div class="small text-primary fw-bold">In Review Pipeline</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-header-cpms">
                            <span>Project Status Distribution</span>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button"><i class="bi bi-three-dots"></i></button>
                            </div>
                        </div>
                        <div class="card-body" style="height: 300px;">
                            <canvas id="projectDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header-cpms">
                            <span>Latest Project Requests</span>
                            <a href="#" class="btn btn-link btn-sm text-decoration-none fw-bold">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Ref #</th>
                                        <th>Project Title</th>
                                        <th>Requested By</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-bold">REQ-4421</td>
                                        <td>Warehouse Cold Storage Extension</td>
                                        <td>Logistics Dept</td>
                                        <td>Mar 10, 2026</td>
                                        <td><button class="btn btn-sm btn-outline-primary py-0">Review</button></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">REQ-4419</td>
                                        <td>Server Room Fire Suppressor</td>
                                        <td>IT Operations</td>
                                        <td>Mar 08, 2026</td>
                                        <td><button class="btn btn-sm btn-outline-primary py-0">Review</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-header-cpms">
                            <span>Critical Projects (Delay Alerts)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-bold">Main Gate Automation</span>
                                    <span class="badge badge-critical">Critical</span>
                                </div>
                                <div class="small text-muted">Delayed by 14 days - Material Shortage</div>
                                <div class="progress-micro"><div class="progress-bar bg-danger" style="width: 85%"></div></div>
                            </div>
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-bold">Site Fence Repair</span>
                                    <span class="badge badge-delayed">Delayed</span>
                                </div>
                                <div class="small text-muted">Awaiting Contractor Response</div>
                                <div class="progress-micro"><div class="progress-bar bg-warning" style="width: 40%"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 bg-dark text-white p-4 text-center">
                        <h2 class="fw-800 mb-0">94%</h2>
                        <div class="small text-white-50">Project Success Rate</div>
                        <div class="mt-3">
                            <div class="small mb-1 d-flex justify-content-between"><span>Annual Goal</span><span>₱ 10M</span></div>
                            <div class="progress" style="height: 4px; background: #334155;"><div class="progress-bar bg-primary" style="width: 62%"></div></div>
                        </div>
                    </div>
                </div>
            </div>
    </div>
</main>


<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('projectDistributionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Planning', 'Review', 'Approval', 'NTP', 'Ongoing', 'Hold'],
            datasets: [{
                label: 'Number of Projects',
                data: [12, 19, 8, 5, 24, 7],
                backgroundColor: ['#f1f5f9', '#fefce8', '#f0f9ff', '#bae6fd', '#f0fdf4', '#fff7ed'],
                borderColor: ['#cbd5e1', '#fef08a', '#bae6fd', '#7dd3fc', '#bbf7d0', '#ffedd5'],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
        }
    });
</script>

</body>
</html>