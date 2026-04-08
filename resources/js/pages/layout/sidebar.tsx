import React from 'react'

export const Sidebar = () => {
  return (
    <nav id="sidebar">
        <a href="#" class="navbar-brand-custom text-center">
            <i class="bi bi-cone-striped me-2"></i> CPMS ADMIN
        </a>
        
        <div class="mt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="#" class="nav-link active"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link has-submenu" data-target="#projectsSub">
                        <i class="bi bi-buildings me-2"></i> Projects 
                        <i class="bi bi-chevron-down float-end"></i>
                    </a>
                    <ul class="submenu" id="projectsSub">
                        <li><a href="#" class="nav-link">Request</a></li>
                        <li><a href="#" class="nav-link">Add New</a></li>
                        <li><a href="#" class="nav-link">View All</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-clock-history me-2"></i> Man-hours Form</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-file-earmark-pdf me-2"></i> Documents</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-bar-chart-line me-2"></i> Reports</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-people me-2"></i> Users</a>
                </li>
                <hr class="text-white-50">
                <li class="nav-item">
                    <a href="#" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </li>
            </ul>
        </div>
    </nav>
  )
}

