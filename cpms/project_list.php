<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects | CPMS</title>
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

        /* Table & Controls */
        .content-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .search-area { background: #fff; padding: 20px; border-bottom: 1px solid var(--border-color); }
        
        .table thead th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-top: none; padding: 15px; }
        .table tbody td { padding: 15px; vertical-align: middle; font-size: 0.875rem; }
        
        /* Status Colors from */
        .status-pill { padding: 4px 12px; border-radius: 50px; font-weight: 700; font-size: 0.72rem; display: inline-block; }
        .stat-ongoing { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .stat-planning { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .stat-review { background-color: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
        .stat-hold { background-color: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }
        
        /* Type Labels */
        .type-label { font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block; }
        .major-label { background: #e0e7ff; color: #3730a3; }
        .minor-label { background: #f1f5f9; color: #475569; }

        .btn-cpms-sm { font-size: 0.75rem; font-weight: 600; padding: 5px 10px; }
        .filter-label { font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 4px; }

        /* Mini Progress Graph */
        .mini-progress-container { width: 100px; }
        .progress-micro { height: 6px; border-radius: 10px; background-color: #e2e8f0; margin-bottom: 4px; overflow: hidden; }
        .progress-micro-bar { height: 100%; border-radius: 10px; transition: width 0.6s ease; }
        .percent-text { font-size: 0.75rem; font-weight: 800; color: #334155; }

        .btn-cpms-sm { font-size: 0.75rem; font-weight: 600; padding: 5px 10px; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">
    <?php include("header.php");?>

    <div class="container-fluid px-4 py-4">
        <h4 class="fw-bold mb-4">Project Management</h4>

        <div class="content-card">
            <div class="search-area d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2" style="width: 50%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="simpleSearch" class="form-control border-start-0" placeholder="Search projects...">
                    </div>
                    <button class="btn btn-outline-secondary btn-sm border fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#advanceFilterModal">
                        <i class="bi bi-sliders me-1"></i> Advanced Filter
                    </button>
                </div>
                <a href="project_addnew.php" class="btn btn-primary btn-sm fw-bold px-3"><i class="bi bi-plus-lg me-1"></i> Add New Project</a>
            </div>



            <div class="card-cpms shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="projectTable">
                        <thead>
                            <tr>
                                <th width="150">Project #</th>
                                <th>Project Title</th>
                                <th>Completion (%)</th>
                                <th>Project Manager</th>
                                <th>Dept Owner</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light d-flex justify-content-between align-items-center border-top">
                    <div class="small text-muted">Showing <b>1</b> to <b>10</b> of 50 entries</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="advanceFilterModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">Advanced Filter Search</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="advFilterForm" class="row g-3">
                    <div class="col-md-3"><label class="filter-label">Project No</label><input type="text" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><label class="filter-label">Project Manager</label><select class="form-select form-select-sm"><option>All Managers</option></select></div>
                    <div class="col-md-4"><label class="filter-label">Project Title</label><input type="text" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="filter-label">Project Type</label><select class="form-select form-select-sm"><option>All</option><option>Major</option><option>Minor</option></select></div>
                    <div class="col-md-3"><label class="filter-label">Site</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-3"><label class="filter-label">Asset ID</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">Class</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">Priority No.</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">Project Status</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">WR No.</label><input type="number" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><label class="filter-label">WR Date Received</label><input type="date" class="form-control form-control-sm"></div>
                    <div class="col-md-4"><label class="filter-label">Department Owner</label><select class="form-select form-select-sm"><option>All Departments</option></select></div>
                    <div class="col-md-3"><label class="filter-label">Cost Code</label><input type="text" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="filter-label">Category</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">Service Type</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-2"><label class="filter-label">Work Force</label><select class="form-select form-select-sm"><option>In-House</option><option>Contracted</option></select></div>
                    <div class="col-md-3"><label class="filter-label">Structure Type</label><select class="form-select form-select-sm"><option>All</option></select></div>
                    <div class="col-md-12 mt-3 d-flex flex-wrap gap-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label small fw-bold">JIP</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label small fw-bold">Need Civil Works Plans</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label small fw-bold">Need Electrical Plans</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label small fw-bold">Need Mechanical Plans</label></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('advFilterForm').reset()">Reset</button>
                <button type="button" class="btn btn-primary btn-sm px-4">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function(){
        const data = [
            { id: "PRJ-2026-0882", title: "Modernization of Main Control Room", type: "Major", progress: 75, pm: "Engr. Alex Rivera", dept: "Civil Works", status: "Ongoing", cls: "stat-ongoing" },
            { id: "PRJ-2603-009", title: "HVAC System Maintenance", type: "Minor", progress: 15, pm: "M. Santos", dept: "Facilities", status: "For Planning", cls: "stat-planning" },
            { id: "PRJ-2603-008", title: "Warehouse Roof Repair", type: "Minor", progress: 0, pm: "J. Reyes", dept: "Logistics", status: "On Hold", cls: "stat-hold" },
            { id: "PRJ-2603-007", title: "CCTV Fiber Network", type: "Major", progress: 45, pm: "Engr. Rivera", dept: "IT Security", status: "Proposal Under Review", cls: "stat-review" },
            { id: "PRJ-2603-006", title: "Main Gate Automation", type: "Minor", progress: 90, pm: "A. Cruz", dept: "Security", status: "Ongoing", cls: "stat-ongoing" },
            { id: "PRJ-2603-005", title: "Admin Office Refurbish", type: "Major", progress: 5, pm: "M. Santos", dept: "Admin", status: "For Planning", cls: "stat-planning" },
            { id: "PRJ-2603-004", title: "Septic Tank Desludging", type: "Minor", progress: 100, pm: "J. Reyes", dept: "Sanitation", status: "Ongoing", cls: "stat-ongoing" },
            { id: "PRJ-2603-003", title: "Structural Integrity Audit", type: "Major", progress: 30, pm: "Engr. Rivera", dept: "Civil Works", status: "On Hold", cls: "stat-hold" }, 
            { id: "PRJ-2603-002", title: "Electrical Panel Upgrade", type: "Major", progress: 60, pm: "A. Cruz", dept: "Electrical", status: "Proposal Under Review", cls: "stat-review" },
            { id: "PRJ-2603-001", title: "Water Supply Pipeline", type: "Major", progress: 82, pm: "Engr. Rivera", dept: "Facilities", status: "Ongoing", cls: "stat-ongoing" }
        ];

        let html = "";
        data.forEach(item => {
            let barColor = item.progress < 30 ? '#ef4444' : (item.progress < 70 ? '#f59e0b' : '#22c55e');
            html += `
                <tr>
                    <td class="fw-bold text-secondary small">${item.id}</td>
                    <td>
                        <div class="fw-bold text-dark small">${item.title}</div>
                        <span class="type-label ${item.type === 'Major' ? 'major-label' : 'minor-label'}">${item.type}</span>
                    </td>
                    <td>
                        <div class="mini-progress-container">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="percent-text">${item.progress}%</span>
                            </div>
                            <div class="progress-micro">
                                <div class="progress-micro-bar" style="width: ${item.progress}%; background-color: ${barColor};"></div>
                            </div>
                        </div>
                    </td>
                    <td class="small fw-semibold">${item.pm}</td>
                    <td class="small text-muted">${item.dept}</td>
                    <td><span class="status-pill ${item.cls}">${item.status}</span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="project_view.php" class="btn btn-cpms-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <button class="btn btn-cpms-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-cpms-sm btn-dark"><i class="bi bi-arrow-repeat"></i></button>
                        </div>
                    </td>
                </tr>`;
        });
        $('#tableBody').html(html);

        $("#simpleSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#projectTable tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>

</body>
</html>