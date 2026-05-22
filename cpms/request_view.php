<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request | CPMS</title>
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
            --status-pending: #f59e0b;
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

        /* Detail Styling */
        .detail-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .detail-header { background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 20px 30px; }
        .detail-body { padding: 30px; }
        
        .info-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: var(--text-dark); margin-bottom: 20px; }
        
        .description-box { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; font-size: 0.9rem; line-height: 1.6; }
        
        /* Attachment Thumbnails */
        .attachment-item { border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; display: flex; align-items: center; margin-bottom: 10px; transition: 0.2s; text-decoration: none; color: inherit; }
        .attachment-item:hover { background: #f1f5f9; border-color: var(--accent-blue); }
        .file-icon { width: 40px; height: 40px; border-radius: 6px; background: #eff6ff; color: var(--accent-blue); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 15px; }
        
        .badge-finance { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-right: 5px; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">

    <?php include("header.php");?>

    <br> 
    <header class="top-bar">
        <div class="fw-semibold text-muted">Projects / View / <span class="text-dark">REQ-2026-0042</span></div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Print</button>
            <button class="btn btn-info btn-sm text-white fw-bold" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                <i class="bi bi-chat-left-dots me-1"></i> Add Feedback
            </button>
            <button class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> Edit Request</button>
        </div>
    </header>

    <div class="container-fluid px-4 py-4">
        <div class="detail-card">
            <div class="detail-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Modernization of South Gate Security Hub</h4>
                    <span class="text-muted small">Submitted on March 06, 2026 by <strong>Engr. Alex Rivera</strong></span>
                </div>
                <div class="text-end">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i> Pending Review</span>
                </div>
            </div>

            <div class="detail-body">
                <div class="row">
                    <div class="col-lg-7 border-end pe-lg-5">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-label">Job Type</div>
                                <div class="info-value">Retrofitting <span class="text-muted">(Others: Seismic Strengthening)</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Job Location</div>
                                <div class="info-value">South Gate, Main Access Road</div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Cost Code</div>
                                <div class="info-value">7740-CC-RETR</div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Financial Allocation</div>
                                <div class="info-value">
                                    <span class="badge-finance">CAPEX</span>
                                    <span class="badge-finance">FOR BUDGETING</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="info-label">Project Description</div>
                            <div class="description-box">
                                The project involves the seismic retrofitting and architectural modernization of the existing South Gate Security Hub. 
                                Scope includes reinforcement of the primary concrete shell, installation of upgraded thermal insulation, 
                                and new access control hardware integration. Total estimated duration is 45 days.
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 ps-lg-5">
                        <h6 class="fw-bold mb-3"><i class="bi bi-paperclip me-2"></i>Attached Files</h6>
                        
                        <div class="mb-4">
                            <div class="info-label mb-2">Pictures (2)</div>
                            <a href="#" class="attachment-item">
                                <div class="file-icon"><i class="bi bi-image"></i></div>
                                <div>
                                    <div class="fw-semibold small">site_survey_north.jpg</div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem;">Initial site condition photo</div>
                                </div>
                                <i class="bi bi-download ms-auto text-muted"></i>
                            </a>
                            <a href="#" class="attachment-item">
                                <div class="file-icon"><i class="bi bi-image"></i></div>
                                <div>
                                    <div class="fw-semibold small">foundation_crack_det.png</div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem;">Detail of structural wear</div>
                                </div>
                                <i class="bi bi-download ms-auto text-muted"></i>
                            </a>
                        </div>

                        <div class="mb-4">
                            <div class="info-label mb-2">Draft Drawings (1)</div>
                            <a href="#" class="attachment-item">
                                <div class="file-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                                <div>
                                    <div class="fw-semibold small">S-GATE-PLAN-V2.pdf</div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem;">Architectural floor plan</div>
                                </div>
                                <i class="bi bi-download ms-auto text-muted"></i>
                            </a>
                        </div>

                        <div class="mb-2">
                            <div class="info-label mb-2">Reports (1)</div>
                            <a href="#" class="attachment-item">
                                <div class="file-icon"><i class="bi bi-file-earmark-word"></i></div>
                                <div>
                                    <div class="fw-semibold small">structural_integrity_rep.docx</div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem;">Preliminary assessment report</div>
                                </div>
                                <i class="bi bi-download ms-auto text-muted"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-top d-flex justify-content-between align-items-center">
                    <button class="btn btn-light border text-danger btn-sm px-3"><i class="bi bi-trash me-1"></i> Cancel Request</button>
                    <div>
                        <button class="btn btn-outline-danger btn-sm me-2 px-4">Reject</button>
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm">Approve Request</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-check me-2"></i>Technical Feedback Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="feedbackForm">
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Involved Disciplines</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="form-check"><input class="form-check-input disc-check" type="checkbox" value="Civil"> <label class="form-check-label">Civil</label></div>
                                    <div class="form-check"><input class="form-check-input disc-check" type="checkbox" value="Architectural"> <label class="form-check-label">Architectural</label></div>
                                    <div class="form-check"><input class="form-check-input disc-check" type="checkbox" value="Electrical"> <label class="form-check-label">Electrical/Automation</label></div>
                                    <div class="form-check"><input class="form-check-input disc-check" type="checkbox" value="Mechanical"> <label class="form-check-label">Mechanical</label></div>
                                    <div class="form-check"><input class="form-check-input disc-check" type="checkbox" value="Fire"> <label class="form-check-label">Fire Protection</label></div>
                                    <div class="form-check">
                                        <input class="form-check-input disc-check" type="checkbox" value="OTHERS" id="discOtherCheck"> 
                                        <label class="form-check-label">Others</label>
                                    </div>
                                    <input type="text" id="discOtherInput" class="form-control form-control-sm mt-2" style="display:none;" placeholder="Specify discipline...">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Required Permits</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="form-check"><input class="form-check-input permit-check" type="checkbox" value="Building"> <label class="form-check-label">Building Permit</label></div>
                                    <div class="form-check"><input class="form-check-input permit-check" type="checkbox" value="Safety"> <label class="form-check-label">Safety Permit</label></div>
                                    <div class="form-check"><input class="form-check-input permit-check" type="checkbox" value="Environmental"> <label class="form-check-label">Environmental Permit</label></div>
                                    <div class="form-check"><input class="form-check-input permit-check" type="checkbox" value="None"> <label class="form-check-label">No Permits Needed</label></div>
                                    <div class="form-check">
                                        <input class="form-check-input permit-check" type="checkbox" value="OTHERS" id="permitOtherCheck"> 
                                        <label class="form-check-label">Others</label>
                                    </div>
                                    <input type="text" id="permitOtherInput" class="form-control form-control-sm mt-2" style="display:none;" placeholder="Specify permit type...">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Priority Level</label>
                                <select class="form-select border-info" required>
                                    <option value="" disabled selected>Select priority...</option>
                                    <option class="text-danger fw-bold">Critical</option>
                                    <option class="text-warning fw-bold">High</option>
                                    <option class="text-primary">Medium</option>
                                    <option class="text-success">Low</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Technical Remarks/Comments</label>
                                <textarea class="form-control" rows="4" placeholder="Enter detailed feedback or technical requirements..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-info btn-sm px-4 text-white fw-bold">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        $('.menu-toggle').on('click', function() {
            const target = $(this).data('target');
            $(target).slideToggle(300);
            $(this).find('.bi-chevron-down').toggleClass('rotated');
        });

        // Logic for Discipline "Others"
        $('#discOtherCheck').on('change', function() {
            if($(this).is(':checked')) {
                $('#discOtherInput').slideDown().focus();
            } else {
                $('#discOtherInput').slideUp();
            }
        });

        // Logic for Permits "Others"
        $('#permitOtherCheck').on('change', function() {
            if($(this).is(':checked')) {
                $('#permitOtherInput').slideDown().focus();
            } else {
                $('#permitOtherInput').slideUp();
            }
        });

        // Form Submission
        $('#feedbackForm').on('submit', function(e) {
            e.preventDefault();
            alert('Technical feedback has been recorded successfully.');
            $('#feedbackModal').modal('hide');
        });
        
    });
</script>

<style>
    .rotated { transform: rotate(180deg); }
    .extra-small { line-height: 1.2; }
</style>

</body>
</html>