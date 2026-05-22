<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data | CPMS</title>
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

        /* Sidebar Styles */
        #sidebar { width: 260px; height: 100vh; position: fixed; background: var(--sidebar-white); border-right: 1px solid var(--border-color); z-index: 1000; }
        .sidebar-header { padding: 20px; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); color: var(--accent-blue); }
        .nav-link { color: #64748b; padding: 12px 20px; display: flex; align-items: center; font-weight: 500; border-radius: 8px; margin: 4px 12px; text-decoration: none; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background-color: #f1f5f9; color: var(--accent-blue); }
        .submenu { list-style: none; padding-left: 0; display: none; background: #fafafa; }
        .submenu .nav-link { padding-left: 50px; font-size: 0.85rem; }

        /* Content Styles */
        #main-content { margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; }
        .top-bar { background: #fff; padding: 12px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }

        /* Tab Styles */
        .nav-tabs { border-bottom: 1px solid var(--border-color); gap: 5px; }
        .nav-tabs .nav-link { margin: 0; border: none; color: #64748b; font-size: 0.9rem; padding: 10px 15px; }
        .nav-tabs .nav-link.active { color: var(--accent-blue); background: #fff; border-bottom: 2px solid var(--accent-blue); border-radius: 0; }

        .data-card { background: #fff; border: 1px solid var(--border-color); border-radius: 0 0 12px 12px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-top: none; }
        .btn-action { padding: 4px 8px; font-size: 0.85rem; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">
    <?php include("header.php");?>

    <div class="container-fluid px-4 py-4">
        <div class="mb-4">
            <h4 class="fw-bold m-0">Dropdown Master Lists</h4>
            <p class="text-muted small">Manage the global options used across the Project Management System forms.</p>
        </div>

        <ul class="nav nav-tabs" id="masterTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-sites">Sites</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-class">Class</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-priority">Priority</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-status">Status</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dept">Departments</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cat">Categories</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-service">Service Types</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-workforce">Work Forces</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-structure">Structures</button></li>
        </ul>

        <div class="tab-content data-card">
            <div class="tab-pane fade show active" id="tab-sites">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0">Manage Sites</h6>
                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addRecordModal" data-title="Site">
                        <i class="bi bi-plus-lg me-1"></i> Add New Site
                    </button>
                </div>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Site Name</th>
                            <th>Location Details</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>101</td><td class="fw-medium">North Industrial Hub</td><td>Zone A, Block 12</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                        <tr><td>102</td><td class="fw-medium">Coastal Development</td><td>Sector 4, Wharf Road</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                        <tr><td>103</td><td class="fw-medium">Central Business District</td><td>Tower 1, HQ</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="tab-class">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0">Manage Project Classes</h6>
                    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addRecordModal" data-title="Class">
                        <i class="bi bi-plus-lg me-1"></i> Add New Class
                    </button>
                </div>
                <table class="table table-hover align-middle">
                    <thead><tr><th style="width: 80px;">ID</th><th>Class Label</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr><td>01</td><td class="fw-medium">Major Capital Project</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                        <tr><td>02</td><td class="fw-medium">Sustaining Works</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                        <tr><td>03</td><td class="fw-medium">Operational Expense (OPEX)</td><td class="text-end"><button class="btn btn-outline-secondary btn-action me-1"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger btn-action"><i class="bi bi-trash"></i></button></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="tab-priority">
                 <div class="p-5 text-center text-muted">Tab Content for Priority (Matches Table Structure Above)</div>
            </div>
            <div class="tab-pane fade" id="tab-status">
                 <div class="p-5 text-center text-muted">Tab Content for Project Status (Matches Table Structure Above)</div>
            </div>
            </div>
    </div>
</main>

<div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0">
                <h6 class="modal-title fw-bold" id="modalLabel">Add New Record</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="masterDataForm">
                <div class="modal-body py-0">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Value Name</label>
                        <input type="text" class="form-control" placeholder="Enter name/label" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description (Optional)</label>
                        <textarea class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Toggle Sidebar Submenus
        $('.menu-toggle').on('click', function() {
            const target = $(this).data('target');
            $(target).slideToggle(300);
            $(this).find('.bi-chevron-down').toggleClass('rotated');
        });

        // Dynamic Modal Title
        $('#addRecordModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var title = button.data('title');
            $(this).find('.modal-title').text('Add New ' + title);
        });

        // Form Submit
        $('#masterDataForm').on('submit', function(e) {
            e.preventDefault();
            alert('New master data entry recorded successfully!');
            $('#addRecordModal').modal('hide');
        });
    });
</script>

</body>
</html>