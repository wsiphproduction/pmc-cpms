<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project | CPMS</title>
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

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* --- Sidebar & Submenu Logic --- */
        #sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            background: var(--sidebar-white);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--accent-blue);
        }

        .nav-link {
            color: #64748b;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            font-weight: 500;
            border-radius: 8px;
            margin: 4px 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background-color: #f1f5f9;
            color: var(--accent-blue);
        }

        .submenu {
            list-style: none;
            padding-left: 0;
            display: none; /* Controlled by jQuery */
            background: #fafafa;
        }

        .submenu .nav-link {
            padding-left: 50px;
            font-size: 0.85rem;
        }

        .rotate-icon {
            transition: transform 0.3s;
        }

        .rotated {
            transform: rotate(180deg);
        }

        /* --- Main Content --- */
        #main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
        }

        .top-bar {
            background: #fff;
            padding: 12px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* --- Form Styling --- */
        .form-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border-color);
            margin-left: 15px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .input-readonly {
            background-color: #f8fafc !important;
            font-weight: 600;
            color: #475569;
        }

        .spec-box {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 20px;
        }

        .btn-submit {
            background-color: var(--accent-blue);
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
        }

        .required { color: #ef4444; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">    

    <?php include("header.php");?>

    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold m-0">Create New Project</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb m-0 small">
                    <li class="breadcrumb-item"><a href="#">Projects</a></li>
                    <li class="breadcrumb-item active">Add New</li>
                </ol>
            </nav>
        </div>

        <form id="projectForm" class="form-card">
            
            <div class="section-label">01. Project Identification</div>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="form-label text-muted">Project No</label>
                    <input type="text" class="form-control input-readonly" value="PRJ-2603-123" readonly>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Project Title <span class="required">*</span></label>
                    <input type="text" class="form-control" placeholder="Enter complete project title" required <?php if(isset($_GET['title'])) echo 'value="'.$_GET['title'].'"'; ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project Manager <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option value="" selected disabled>Select Manager</option>
                        <option>Engr. Michael Chen</option>
                        <option>Engr. Sarah Jenkins</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Site <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option value="" selected disabled>Select Site Location</option>
                        <option>North Industrial Zone</option>
                        <option>East Coast Terminal</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Asset ID <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option value="" selected disabled>Select Asset</option>
                        <option>STR-990 (Main Bridge)</option>
                        <option>BLD-201 (Warehouse)</option>
                    </select>
                </div>
            </div>

            <div class="section-label">02. Work Request & Classification</div>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Class <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>Tier 1 - Major</option>
                        <option>Tier 2 - Minor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority No. <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>P1 - Urgent</option>
                        <option>P2 - High</option>
                        <option>P3 - Medium</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project Status <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>Planning</option>
                        <option>Under Review</option>
                        <option>Execution</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Work Force <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>In-house Team</option>
                        <option>Contractor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">WR No. <span class="required">*</span></label>
                    <input type="number" class="form-control" placeholder="0000" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">WR Date Received <span class="required">*</span></label>
                    <input type="date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department Owner <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>Engineering</option>
                        <option>Facilities</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cost Code <span class="required">*</span></label>
                    <input type="text" class="form-control" placeholder="Enter CC" required>
                </div>
            </div>

            <div class="section-label">03. Timeline & Specifications</div>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Category <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>Renovation</option>
                        <option>Greenfield</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Service Type <span class="required">*</span></label>
                    <select class="form-select" required>
                        <option>Design-Build</option>
                        <option>Execution Only</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline <span class="required">*</span></label>
                    <input type="date" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Project Owner's Email</label>
                    <input type="email" class="form-control" placeholder="email@construction.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Structure Type</label>
                    <select class="form-select">
                        <option>Steel Frame</option>
                        <option>Reinforced Concrete</option>
                    </select>
                </div>

                <div class="col-12 mt-4">
                    <div class="spec-box shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-md-3 border-end">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="jip">
                                    <label class="form-check-label fw-bold" for="jip">JIP</label>
                                </div>
                            </div>
                            <div class="col-md-9 d-flex justify-content-around">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="civil">
                                    <label class="form-check-label" for="civil">Civil Works Plans</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="elec">
                                    <label class="form-check-label" for="elec">Electrical Plans</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="mech">
                                    <label class="form-check-label" for="mech">Mechanical Plans</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" rows="3" placeholder="Additional details..."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end pt-4 border-top">
                <button type="button" class="btn btn-light border me-2 px-4">Cancel</button>
                <button type="submit" class="btn btn-submit shadow-sm">Register Project</button>
            </div>
        </form>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Toggle Submenus
        $('.menu-toggle').on('click', function() {
            const target = $(this).data('target');
            $(target).slideToggle(300);
            $(this).find('.rotate-icon').toggleClass('rotated');
        });

        // Form Submit
        $('#projectForm').on('submit', function(e) {
            e.preventDefault();
            alert('New Project ' + $('.input-readonly').val() + ' has been saved.');
        });
    });
</script>

</body>
</html>