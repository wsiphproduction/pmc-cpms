<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Record | CPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg-light: #f1f5f9;
            --sidebar-white: #ffffff;
            --accent-blue: #2563eb;
            --border-color: #e2e8f0;
            --text-dark: #1e293b;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding: 20px; }
        
        .analytics-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .chart-box { height: 140px; position: relative; }
        
        .metric-label { 
            font-size: 0.7rem; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            margin-bottom: 10px;
            display: block;
        }

        /* Status Remark Labels */
        .status-pill {
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-ontime { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-delayed { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .status-advanced { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

        .progress-center-text {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 800;
            font-size: 1.4rem;
            color: #1e293b;
        }

        /* Sidebar & Layout */
        #sidebar { width: 260px; height: 100vh; position: fixed; background: var(--sidebar-white); border-right: 1px solid var(--border-color); z-index: 1000; }
        .sidebar-header { padding: 20px; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); color: var(--accent-blue); }
        .nav-link { color: #64748b; padding: 12px 20px; display: flex; align-items: center; font-weight: 500; border-radius: 8px; margin: 4px 12px; text-decoration: none; }
        .nav-link.active { background-color: #f8fafc; color: var(--accent-blue); }

        #main-content { margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; }
        .top-bar { background: #fff; padding: 12px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }

        /* Detail Styling */
        .record-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .record-header { padding: 25px 30px; border-bottom: 1px solid var(--border-color); background: #fff; }
        
        .info-group { margin-bottom: 24px; }
        .info-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: var(--text-dark); }
        
        /* Plan Badges */
        .plan-status { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; background: #f8fafc; }
        .plan-status.required { border-color: #93c5fd; background: #eff6ff; color: #1e40af; }
        .plan-status i { font-size: 1.2rem; margin-right: 12px; }

        .status-badge-lg { padding: 6px 16px; border-radius: 50px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }

        /* Operations Hub Specific Styles */
        .ops-sidebar .list-group-item {
            padding: 14px 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border-left: 4px solid transparent;
            color: #475569;
        }
        
        .ops-sidebar .list-group-item:hover {
            background-color: #f8fafc;
            color: var(--accent-blue);
        }
        
        .ops-sidebar .list-group-item.active {
            background-color: #eff6ff !important;
            border-color: #eff6ff; /* Reset default */
            border-left-color: var(--accent-blue);
            color: var(--accent-blue);
            font-weight: 600;
        }

        /* Fixed Height for Iframe Container */
        #iframe-display {
            min-height: 550px;
        }


        /* Main Page Status Pill (Integration Point) */

        .stat-planning { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; } /* Slate */
        .stat-review { background-color: #fefce8; color: #854d0e; border: 1px solid #fef08a; }   /* Yellow */
        .stat-approval { background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; } /* Sky */
        .stat-ongoing { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }  /* Green */
        .stat-hold { background-color: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }     /* Orange */
        .stat-closed { background-color: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; }   /* Purple */
        .stat-canceled { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; } /* Red */

        .status-pill-trigger {
            background-color: #f0fdf4; 
            color: #166534; 
            border: 1px solid #bbf7d0;
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .status-pill-trigger:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-color: #86efac;
        }
        .status-pill-trigger:active { transform: scale(0.98); }

        /* Modal Specifics */
        .reminder-box {
            background-color: #fffbeb;
            border: 1px solid #fef08a;
            border-radius: 8px;
            padding: 15px;
            font-size: 0.85rem;
            color: #854d0e;
            display: none; /* Hidden by default */
        }
        .reminder-box i { font-size: 1.1rem; }
        .reminder-title { font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        
        /* History Table Styling */
        .table-history { font-size: 0.8rem; border-collapse: separate; border-spacing: 0; }
        .table-history thead th {
            background-color: #f8fafc;
            text-transform: uppercase;
            color: #64748b;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #edf2f7;
        }
        .table-history td { vertical-align: top; border-bottom: 1px solid #edf2f7; }
        .log-date { font-weight: 700; color: #475569; }
        .log-user { font-weight: 600; color: #1e293b; }
        
        /* Minimalist Modal Form */
        .form-update { background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 15px; }

        /* Animation for new row insertion */
        @keyframes highlight {
            from { background-color: #fef9c3; }
            to { background-color: transparent; }
        }
        .new-row { animation: highlight 2s ease-out; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">

    <?php include("header.php");?>
    <br>
    <header class="top-bar">
        <div class="fw-semibold text-muted">Projects / View / <span class="text-dark">PRJ-2026-0882</span></div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Export PDF</button>
            <button class="btn btn-primary btn-sm px-4"><i class="bi bi-pencil me-1"></i> Edit Project</button>
        </div>
    </header>

    <div class="container-fluid px-4 py-4">

        <div class="record-card">
            <div class="record-header d-flex justify-content-between align-items-start">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <h3 class="fw-bold m-0">Modernization of Main Control Room</h3>
                        <!-- <span class="status-badge-lg bg-primary text-white">Ongoing</span> -->
                    </div>
                    <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i> Site: <strong>North Sector - Level 4</strong> | PM: <strong>Engr. Alex Rivera</strong></p>



                </div>
                <div class="text-end">

                    <div class="mb-4">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Current Lifecycle Status</label>

                        <div class="status-pill-trigger stat-planning" id="mainStatusBadge" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                            <i class="bi bi-flag-fill"></i> <span id="currentStatusText">For Planning</span>
                        </div>
                        <div class="small text-muted mt-2 px-1">
                            <i class="bi bi-info-circle me-1"></i> Awaiting field verification, scoping, or requirements gathering.
                        </div>
                    </div>

                    
                </div>
            </div>



            <div class="analytics-card">
                <div class="row align-items-center">
                    
                    <div class="col-md-3 border-end">
                        <span class="metric-label">Timeline (Days Remaining)</span>
                        <div class="chart-box">
                            <canvas id="timelineChart"></canvas>
                            <div class="progress-center-text" id="daysLeft">218</div>
                        </div>
                        <div class="text-center mt-2 small text-muted">
                            Deadline: <strong>Oct 15, 2026</strong>
                        </div>
                    </div>

                    <div class="col-md-3 border-end">
                        <span class="metric-label">Financial Utilization</span>
                        <div class="chart-box">
                            <canvas id="budgetChart"></canvas>
                        </div>
                        <div class="text-center mt-2 small">
                            Paid: <strong class="text-primary">PhP 450K</strong> / Total: <strong>PhP 5M</strong>
                        </div>
                    </div>

                    <div class="col-md-3 border-end">
                        <span class="metric-label">Physical Completion</span>
                        <div class="chart-box">
                            <canvas id="completionChart"></canvas>
                            <div class="progress-center-text">72%</div>
                        </div>
                        <div class="text-center mt-2 small text-muted">
                            Based on Weekly Report W4-SEP
                        </div>
                    </div>

                    <div class="col-md-3 text-center">
                        <span class="metric-label">Project Health</span>
                        <div class="py-3">
                            <div class="status-pill status-ontime">
                                <i class="bi bi-check-circle-fill"></i> ON-TIME
                            </div>
                        </div>
                        <p class="small text-muted px-3 mt-2">
                            Current progress aligns with the approved baseline schedule.
                        </p>
                    </div>

                </div>
            </div>

            <div class="row g-0">
                <div class="col-lg-4 border-end bg-light bg-opacity-50 p-4">
                    <div class="info-group">
                        <div class="info-label">Project Number</div>
                        <div class="info-value">PRJ-2026-0882</div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Asset ID</div>
                        <div class="info-value">AST-CR-9901</div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Cost Code</div>
                        <div class="info-value">7740-CAPEX-2026</div>
                    </div>
                    <hr>
                    <div class="info-group">
                        <div class="info-label">WR No. & Date</div>
                        <div class="info-value">WR-55421 <span class="text-muted ms-2">(Received: Jan 12, 2026)</span></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Priority Number</div>
                        <div class="info-value"><span class="badge bg-dark">Level 02</span></div>
                    </div>
                    <hr>
                    <div class="info-group">
                        <div class="info-label">Department Owner</div>
                        <div class="info-value">Operations & Automation</div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Owner's Email</div>
                        <div class="info-value text-primary">ops-lead@company.com</div>
                    </div>
                </div>

                <div class="col-lg-8 p-4">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="info-label">Class</div>
                            <div class="info-value">Infrastructure</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Category</div>
                            <div class="info-value">Major Capital Project</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Service Type</div>
                            <div class="info-value">Systems Upgrade</div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="info-label">Work Force</div>
                            <div class="info-value">Mixed (In-house + Contractor)</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">JIP</div>
                            <div class="info-value">JIP-2026-A1</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Structure Type</div>
                            <div class="info-value">Reinforced Steel/Concrete</div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <div class="info-label mb-3">Technical Plan Status</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="plan-status required">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <div class="small fw-bold">Civil Plans<br><span class="text-muted fw-normal">Required</span></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="plan-status required">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <div class="small fw-bold">Electrical Plans<br><span class="text-muted fw-normal">Required</span></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="plan-status">
                                    <i class="bi bi-dash-circle text-muted"></i>
                                    <div class="small fw-bold text-muted">Mechanical Plans<br><span class="text-muted fw-normal">Not Needed</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Administrative Notes</div>
                        <div class="p-3 border rounded bg-white small text-secondary" style="line-height: 1.6;">
                            Project initiated following the Q4 safety audit. Focus is on replacing the main server racks and upgrading the HVAC ducting specifically for the control room area. All work must be conducted during low-traffic night shifts.
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="mt-5">
            <h5 class="fw-bold mb-3"><i class="bi bi-gear-wide-connected me-2 text-primary"></i>Project Operations Hub</h5>
            
            <div class="row g-0 record-card shadow-sm" style="min-height: 600px;">
                <div class="col-md-3 border-end bg-white">
                    <div class="p-3 bg-light border-bottom">
                        <span class="info-label">Action Menu</span>
                    </div>
                    <div class="list-group list-group-flush ops-sidebar" id="opsMenu">
                        <a href="project-hub/rfq.html" class="list-group-item list-group-item-action active" data-target="iframe-display">
                            <i class="bi bi-file-earmark-text me-2"></i> Request for Quotations
                        </a>
                        <a href="project-hub/ntp.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-send-check me-2"></i> Notice to Proceed
                        </a>
                        <a href="project-hub/permits.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-shield-lock me-2"></i> Permits
                        </a>
                        <a href="project-hub/vof.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-plus-slash-minus me-2"></i> Variation Order Form
                        </a>
                        <a href="project-hub/qpp.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-clipboard-check me-2"></i> Quality Plan & Procedures
                        </a>
                        <a href="project-hub/mtr.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-filetype-docx me-2"></i> Materials Test Reports
                        </a>
                        <a href="project-hub/rfp.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-cash-stack me-2"></i> Request for Payment
                        </a>
                        <a href="project-hub/ioc.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-calculator me-2"></i> Input Other Cost
                        </a>
                        <a href="project-hub/acr.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-graph-up-arrow me-2"></i> Actual Cost Report
                        </a>
                        <a href="project-hub/psr.html" class="list-group-item list-group-item-action" data-target="iframe-display">
                            <i class="bi bi-pie-chart me-2"></i> Project Status Report
                        </a>
                        <a href="project-hub/at.html" class="list-group-item list-group-item-action border-bottom-0" data-target="iframe-display">
                            <i class="bi bi-chat-square-dots me-2"></i> Project Audit Trail
                        </a>
                    </div>
                </div>

                <div class="col-md-9 bg-white d-flex flex-column">
                    <div class="p-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <span id="current-menu-title" class="small fw-bold text-muted ps-2">REQUEST FOR QUOTATIONS</span>
                        <button class="btn btn-sm" onclick="document.getElementById('iframe-display').contentWindow.location.reload();">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="flex-grow-1">
                        <iframe id="iframe-display" name="iframe-display" 
                                src="project-hub/rfq.html" 
                                style="width: 100%; height: 100%; border: none;" 
                                title="Project Operations">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>


    </div>


    <div class="modal fade" id="statusUpdateModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white p-2">
                    <h6 class="modal-title fw-bold">Update Project Phase</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">I. Change Status Form</h6>
                    <form id="statusUpdateForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Select New Project Status</label>
                                <select id="newStatus" class="form-select form-select-sm" required>
                                    <option value="" selected disabled>Choose status...</option>
                                    <option value="PLANNING">For Planning</option>
                                    <option value="RFQ_SUBMITTED">RFQ/RFP Submitted</option>
                                    <option value="PROPOSAL_REVIEW">Proposal Under Review</option>
                                    <option value="DESIGN_REVIEW">Detailed Design Under Review</option>
                                    <option value="EXEC_ENDORSED">Endorsed for Executive Approval</option>
                                    <option value="NTP_PROCESSING">NTP & Contract Processing</option>
                                    <option value="SCHEDULING">For Scheduling</option>
                                    <option value="ONGOING">Ongoing</option>
                                    <option value="ON_HOLD">On Hold</option>
                                    <option value="COMPLETED">Completed</option>
                                    <option value="CLOSED">Closed</option>
                                    <option value="CANCELED">Canceled</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label small fw-bold">Modification Remarks</label>
                            <textarea id="updateRemarks" class="form-control form-control-sm" rows="2" placeholder="Explain the phase change..."></textarea>
                        </div>

                        <div class="mt-3">
                            <div class="reminder-box" id="statusReminder">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-info-circle-fill me-3 mt-1"></i>
                                    <div>
                                        <span class="fw-bold text-uppercase small d-block mb-1" id="remStatusTitle"></span>
                                        <span id="remStatusText"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <h6 class="text-secondary fw-bold mb-3 border-bottom pb-2 mt-5">II. Status Log & Audit Trail</h6>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-hover table-history align-middle mb-0" id="historyTable">
                            <thead>
                                <tr>
                                    <th width="160">Date & Time</th>
                                    <th width="160">Authorized User</th>
                                    <th>Status Changed To</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><div class="fw-bold">Mar 12, 2026</div><div class="small text-muted">08:00 AM</div></td>
                                    <td><div class="fw-bold">System Admin</div></td>
                                    <td><span class="badge stat-planning">For Planning</span></td>
                                    <td><div class="small text-muted">Initial project creation.</div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="statusUpdateForm" class="btn btn-primary btn-sm px-4 fw-bold">Update Phase</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Menu switch logic
        $('#opsMenu a').on('click', function(e) {
            // Prevent default only if using the iframe target
            if($(this).attr('target') === 'iframe-display') {
                e.preventDefault();
            }
            
            // Update Active Class
            $('#opsMenu a').removeClass('active');
            $(this).addClass('active');
            
            // Update Title Label
            const menuText = $(this).text().trim().toUpperCase();
            $('#current-menu-title').text(menuText);
            
            // Update Iframe Source (if not using target attribute)
            const targetUrl = $(this).attr('href');
            $('#iframe-display').attr('src', targetUrl);
            
            return false;
        });



        // Reminder Data Dictionary (Mirrors Attached Image)
        const statusMeta = {
            'PLANNING': { cls: 'stat-planning', txt: 'Project request received and logged. Awaiting field verification, scoping, requirements gathering, or initial PM review.' },
            'RFQ_SUBMITTED': { cls: 'stat-review', txt: 'PM Engineer submitted RFQ/RFP to contractor. Waiting for contractor quotation/proposal.' },
            'PROPOSAL_REVIEW': { cls: 'stat-review', txt: 'Contractor submitted proposal. PM Engineer evaluating cost and scope.' },
            'DESIGN_REVIEW': { cls: 'stat-review', txt: 'Contractor submitted detailed plan. PM Engineer evaluating for revision or endorsement.' },
            'EXEC_ENDORSED': { cls: 'stat-approval', txt: 'PMD review done. Submitted to ECS Division/Executive for final approval.' },
            'NTP_PROCESSING': { cls: 'stat-approval', txt: 'Proposal approved. Awaiting completion of signatures.' },
            'SCHEDULING': { cls: 'stat-ongoing', txt: 'Docs approved. Project cleared to start mobilization.' },
            'ONGOING': { cls: 'stat-ongoing', txt: 'Works actively being executed. Monitoring & QA/QC in progress.' },
            'ON_HOLD': { cls: 'stat-hold', txt: 'Project paused due to issues/constraints. Awaiting resolution.' },
            'COMPLETED': { cls: 'stat-ongoing', txt: 'Construction finished. Processing billing and documentation.' },
            'CLOSED': { cls: 'stat-closed', txt: 'Works and documentation finalized. Project officially closed.' },
            'CANCELED': { cls: 'stat-canceled', txt: 'Project has been canceled and nullified.' }
        };

        $('#newStatus').on('change', function() {
            const meta = statusMeta[$(this).val()];
            if(meta) {
                $('#remStatusTitle').text('Phase Guidelines: ' + $(this).find('option:selected').text());
                $('#remStatusText').text(meta.txt);
                $('#statusReminder').removeClass().addClass('reminder-box ' + meta.cls).fadeIn();
            }
        });

        $('#statusUpdateForm').on('submit', function(e) {
            e.preventDefault();
            const val = $('#newStatus').val();
            const meta = statusMeta[val];
            const statusText = $('#newStatus option:selected').text();
            const remarks = $('#updateRemarks').val() || "No remarks.";
            const now = new Date();

            // 1. Update Main Page Badge Class and Text
            $('#mainStatusBadge').removeClass().addClass('status-pill-trigger ' + meta.cls);
            $('#currentStatusText').text(statusText);
            
            // 2. Add Row to History Table
            const newRow = `
                <tr>
                    <td><div class="fw-bold">${now.toLocaleDateString()}</div><div class="small text-muted">${now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div></td>
                    <td><div class="fw-bold">Current User</div></td>
                    <td><span class="badge ${meta.cls}">${statusText}</span></td>
                    <td><div class="small text-muted">${remarks}</div></td>
                </tr>
            `;
            $('#historyTable tbody').prepend(newRow);
            $('#updateRemarks').val('');
        });
    });
</script>

<script>
    // 1. Timeline Chart (Doughnut)
    new Chart(document.getElementById('timelineChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [147, 218], // Days Elapsed vs Days Remaining
                backgroundColor: ['#e2e8f0', '#f59e0b'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
                cutout: '80%'
            }]
        },
        options: { plugins: { legend: { display: false } }, maintainAspectRatio: false }
    });

    // 2. Budget Chart (Bar)
    new Chart(document.getElementById('budgetChart'), {
        type: 'bar',
        data: {
            labels: ['Budget vs Paid'],
            datasets: [
                { label: 'Total Budget', data: [5000000], backgroundColor: '#e2e8f0', borderRadius: 5 },
                { label: 'Total Paid', data: [450000], backgroundColor: '#0d6efd', borderRadius: 5 }
            ]
        },
        options: { 
            indexAxis: 'y',
            scales: { x: { display: false }, y: { display: false } },
            plugins: { legend: { display: false } },
            maintainAspectRatio: false 
        }
    });

    // 3. Completion Chart (Doughnut)
    new Chart(document.getElementById('completionChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [72, 28],
                backgroundColor: ['#16a34a', '#f1f5f9'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: { plugins: { legend: { display: false } }, maintainAspectRatio: false }
    });
</script>

</body>
</html>