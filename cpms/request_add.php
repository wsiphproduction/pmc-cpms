<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Request | CPMS</title>
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

        .form-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; }
        
        .upload-row { background: #fcfcfd; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 15px; margin-bottom: 10px; position: relative; }
        .btn-remove { position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; border: none; }
        
        .section-title { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 20px; }
        .budget-box { background: #f1f5f9; padding: 15px; border-radius: 10px; height: 100%; display: flex; align-items: center; justify-content: space-around; }
    </style>
</head>
<body>

<?php include("sidebar.php");?>

<main id="main-content">
    <?php include("header.php");?>


    <div class="container-fluid px-4 py-4">
        <div class="mb-4">
            <h4 class="fw-bold m-0">Project Request Form</h4>
            <p class="text-muted small">Submit a new project requirement for review and approval.</p>
        </div>

        <form id="requestForm" class="form-card shadow-sm">
            <div class="section-title">General Information</div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Job Type</label>
                    <select class="form-select" id="jobTypeSelect" required>
                        <option value="" selected disabled>Select Job Type...</option>
                        <option>Construction</option>
                        <option>Design</option>
                        <option>Installation</option>
                        <option>Study/Report</option>
                        <option>Modification</option>
                        <option>Estimate</option>
                        <option>Demolition/Removal</option>
                        <option>Retrofitting</option>
                        <option value="OTHERS">Others</option>
                    </select>
                    <div id="otherJobTypeContainer" class="mt-2" style="display: none;">
                        <input type="text" class="form-control border-primary" placeholder="Please specify job type...">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Job Location</label>
                    <input type="text" class="form-control" placeholder="Enter specific location/site area" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Project Description</label>
                    <textarea class="form-control" rows="4" placeholder="Detailed scope of works..." required></textarea>
                </div>
            </div>

            <div class="section-title">Financials & Budgeting</div>
            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <label class="form-label">Cost Code</label>
                    <input type="text" class="form-control" placeholder="Enter assigned cost code">
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block text-muted small mb-2">Funding Classification</label>
                    <div class="budget-box border">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="opex">
                            <label class="form-check-label fw-semibold" for="opex">OPEX</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="capex">
                            <label class="form-check-label fw-semibold" for="capex">CAPEX</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="budgeting">
                            <label class="form-check-label fw-semibold" for="budgeting">For Budgeting</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-title">Supporting Documents & Media</div>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0"><i class="bi bi-camera me-2"></i>Picture Attachments</label>
                        <button type="button" class="btn btn-sm btn-outline-primary add-upload" data-type="picture"><i class="bi bi-plus"></i></button>
                    </div>
                    <div id="picture-container">
                        <div class="upload-row">
                            <input type="file" class="form-control form-control-sm mb-2" accept="image/*">
                            <input type="text" class="form-control form-control-sm" placeholder="Image description">
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0"><i class="bi bi-pencil-ruler me-2"></i>Draft Drawings</label>
                        <button type="button" class="btn btn-sm btn-outline-primary add-upload" data-type="drawing"><i class="bi bi-plus"></i></button>
                    </div>
                    <div id="drawing-container">
                        <div class="upload-row">
                            <input type="file" class="form-control form-control-sm mb-2" accept=".pdf,.dwg,.jpg">
                            <input type="text" class="form-control form-control-sm" placeholder="Drawing reference">
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports Attachments</label>
                        <button type="button" class="btn btn-sm btn-outline-primary add-upload" data-type="report"><i class="bi bi-plus"></i></button>
                    </div>
                    <div id="report-container">
                        <div class="upload-row">
                            <input type="file" class="form-control form-control-sm mb-2" accept=".pdf,.doc,.docx">
                            <input type="text" class="form-control form-control-sm" placeholder="Report summary">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-top text-end">
                <button type="button" class="btn btn-light px-4 me-2">Save Draft</button>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">Submit Request</button>
            </div>
        </form>
    </div>
</main>

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

        // Show/Hide Other Job Type
        $('#jobTypeSelect').on('change', function() {
            if($(this).val() === 'OTHERS') {
                $('#otherJobTypeContainer').slideDown();
            } else {
                $('#otherJobTypeContainer').slideUp();
            }
        });

        // Add Dynamic Upload Rows
        $('.add-upload').on('click', function() {
            const type = $(this).data('type');
            const container = $(`#${type}-container`);
            const placeholder = type === 'picture' ? 'Image description' : (type === 'drawing' ? 'Drawing reference' : 'Report summary');
            
            const html = `
                <div class="upload-row mt-2 animate-fade">
                    <button type="button" class="btn-remove remove-upload"><i class="bi bi-x"></i></button>
                    <input type="file" class="form-control form-control-sm mb-2">
                    <input type="text" class="form-control form-control-sm" placeholder="${placeholder}">
                </div>
            `;
            container.append(html);
        });

        // Remove Dynamic Upload Rows
        $(document).on('click', '.remove-upload', function() {
            $(this).closest('.upload-row').fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Form Submit
        $('#requestForm').on('submit', function(e) {
            e.preventDefault();
            alert('Project Request Submitted Successfully!');
        });
    });
</script>

<style>
    .rotated { transform: rotate(180deg); }
    .animate-fade { animation: fadeIn 0.4s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

</body>
</html>