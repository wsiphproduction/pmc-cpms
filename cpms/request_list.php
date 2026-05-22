<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests | CPMS</title>
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
        
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }

        /* Comment Modal Styles */
        .comment-history { max-height: 250px; overflow-y: auto; background: #f8fafc; border-radius: 8px; padding: 15px; border: 1px solid var(--border-color); }
        .comment-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #edf2f7; }
        .comment-meta { font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .comment-text { font-size: 0.85rem; color: #334155; }
        
        .rotated { transform: rotate(180deg); }

        /* Notification Badge Styling */
        .comment-btn-wrapper {
            position: relative;
            display: inline-block;
        }

        /* The Red Number Badge */
        .unread-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #ef4444; /* Alert Red */
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff; /* White border to make it pop against the button */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 10;
        }

        /* Highlight the icon itself if there are unread comments */
        .btn-unread-highlight {
            border-color: #f87171 !important; /* Soft red border */
            background-color: #fef2f2 !important; /* Very light red tint */
            color: #ef4444 !important;
        }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">
    <?php include("header.php");?>

    <div class="container-fluid px-4 py-4">
        <h4 class="fw-bold mb-4">Project Requests Registry</h4>

        <div class="content-card">
            <div class="search-area d-flex justify-content-between align-items-center">
                <div class="input-group w-50">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0" placeholder="Quick search...">
                </div>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#advanceSearchModal">
                    <i class="bi bi-sliders me-2"></i>Advanced Search
                </button>
                <a href="request_add.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus me-2"></i>Add Request
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Project Title</th>
                            <th>Department</th>
                            <th>Job Type</th>
                            <th>Status</th>
                            <th>Project No.</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>REQ-2026-001</td>
                            <td><div class="fw-bold">Warehouse HVAC Retrofit</div></td>
                            <td><span class="badge bg-light text-dark border">Facilities</span></td>
                            <td>Retrofitting</td>
                            <td><span class="status-pill status-approved">Approved</span></td>
                            <td><a href="project_view.php" class="fw-bold text-primary text-decoration-none">PRJ-2603-010</a></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <a href="request_view.php" class="btn btn-light btn-sm border" title="View"><i class="bi bi-eye"></i></a>
                                    <button class="btn btn-light btn-sm border" title="Print"><i class="bi bi-printer"></i></button>
                                    
                                    <div class="comment-btn-wrapper ms-1">
                                        <span class="unread-badge">1</span>
                                        <button class="btn btn-light btn-sm border btn-unread-highlight" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#commentModal" 
                                            data-id="REQ-2026-001">
                                            <i class="bi bi-chat-text"></i>
                                        </button>
                                    </div>
                                    
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>REQ-2026-005</td>
                            <td><div class="fw-bold">Main Office Lobby Design</div></td>
                            <td><span class="badge bg-light text-dark border">Admin</span></td>
                            <td>Design</td>
                            <td><span class="status-pill status-approved">Approved</span></td>
                            <td><span class="text-muted small"><i>Not Created</i></span></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <a href="request_view.php" class="btn btn-light btn-sm border" title="View"><i class="bi bi-eye"></i></a>
                                    <a href="project_addnew.php?title=Main Office Lobby Design&dept=Admin" class="btn btn-light btn-sm border text-primary" title="Add New Project"><i class="bi bi-plus-circle-fill"></i></a>
                                    <button class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#commentModal" data-id="REQ-2026-005"><i class="bi bi-chat-text"></i></button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>REQ-2026-042</td>
                            <td><div class="fw-bold">South Gate Reinforcement</div></td>
                            <td><span class="badge bg-light text-dark border">Engineering</span></td>
                            <td>Others</td>
                            <td><span class="status-pill status-pending">Pending</span></td>
                            <td>—</td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button class="btn btn-light btn-sm border text-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    <button class="btn btn-light btn-sm border text-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                    
                                    <div class="comment-btn-wrapper ms-1">
                                        <span class="unread-badge">1</span>
                                        <button class="btn btn-light btn-sm border btn-unread-highlight" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#commentModal" 
                                            data-id="REQ-2026-042">
                                            <i class="bi bi-chat-text"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>REQ-2026-012</td>
                            <td><div class="fw-bold">Solar Panel Installation</div></td>
                            <td><span class="badge bg-light text-dark border">Maintenance</span></td>
                            <td>Installation</td>
                            <td><span class="status-pill bg-info text-white" style="font-size: 0.7rem;">Ongoing</span></td>
                            <td><a href="project_view.php" class="fw-bold text-primary text-decoration-none">PRJ-2601-004</a></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <a href="request_view.php" class="btn btn-light btn-sm border" title="View"><i class="bi bi-eye"></i></a>
                                    <button class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#commentModal" data-id="REQ-2026-012"><i class="bi bi-chat-text"></i></button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>REQ-2025-088</td>
                            <td><div class="fw-bold">Cafeteria Extension Study</div></td>
                            <td><span class="badge bg-light text-dark border">HR</span></td>
                            <td>Study/Report</td>
                            <td><span class="status-pill bg-danger text-white" style="font-size: 0.7rem;">Rejected</span></td>
                            <td>—</td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button class="btn btn-light btn-sm border"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#commentModal" data-id="REQ-2025-088"><i class="bi bi-chat-text"></i></button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>REQ-2025-015</td>
                            <td><div class="fw-bold">East Perimeter Fence Repair</div></td>
                            <td><span class="badge bg-light text-dark border">Security</span></td>
                            <td>Construction</td>
                            <td><span class="status-pill bg-secondary text-white" style="font-size: 0.7rem;">Completed</span></td>
                            <td><a href="project_view.php" class="fw-bold text-primary text-decoration-none">PRJ-2508-099</a></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button class="btn btn-light btn-sm border"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-light btn-sm border"><i class="bi bi-archive"></i></button>
                                    <button class="btn btn-light btn-sm border" data-bs-toggle="modal" data-bs-target="#commentModal" data-id="REQ-2025-015"><i class="bi bi-chat-text"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="advanceSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Advanced Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="advSearchForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Job Type</label>
                            <select class="form-select" id="advJobType">
                                <option value="">All Types</option>
                                <option>Construction</option>
                                <option>Design</option>
                                <option>Retrofitting</option>
                                <option value="OTHERS">Others</option>
                            </select>
                            <input type="text" id="advOtherType" class="form-control mt-2 border-primary" style="display:none;" placeholder="Specify other type...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Department</label>
                            <select class="form-select">
                                <option value="">All Departments</option>
                                <option>Engineering</option>
                                <option>Facilities</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Job Location</label>
                            <select class="form-select">
                                <option value="">All Locations</option>
                                <option>North Site</option>
                                <option>Main HQ</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Cost Code</label>
                            <input type="text" class="form-control" placeholder="Enter code">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Status</label>
                            <div class="d-flex gap-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">Approved</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">Rejected</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">Ongoing</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox"><label class="form-check-label">Completed</label></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-4">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-bold">Comment History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="comment-history mb-3">
                    <div class="comment-item">
                        <div class="comment-meta"><strong>Engr. Alex</strong> <span>Mar 06, 14:30</span></div>
                        <div class="comment-text">Uploaded initial foundation assessment.</div>
                    </div>
                </div>
                <label class="form-label small fw-bold">Add Comment</label>
                <textarea class="form-control mb-3" rows="3" placeholder="Write a note..."></textarea>
                <button class="btn btn-primary btn-sm w-100">Post Comment</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Sidebar Submenu
        $('.menu-toggle').on('click', function() {
            const target = $(this).data('target');
            $(target).slideToggle(300);
            $(this).find('.bi-chevron-down').toggleClass('rotated');
        });

        // Others Toggle in Advance Search
        $('#advJobType').on('change', function() {
            if($(this).val() === 'OTHERS') {
                $('#advOtherType').slideDown();
            } else {
                $('#advOtherType').slideUp();
            }
        });

        // Dynamic Comment Modal Title
        $('#commentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var reqId = button.data('id');
            $(this).find('.modal-title').text('Comments: ' + reqId);
        });


    });

    $(document).on('click', '.btn-unread-highlight', function() {
            // In a real app, you would send an AJAX call here to mark as read
            $(this).removeClass('btn-unread-highlight');
            $(this).siblings('.unread-badge').fadeOut();
        });
</script>

</body>
</html>