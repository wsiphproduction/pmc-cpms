import React from 'react'

export const Sidebar = () => {
  return (
    <nav id="sidebar">
        <a href="#" className="navbar-brand-custom text-center">
            <i className="bi bi-cone-striped me-2"></i> CPMS ADMIN
        </a>
        
        <div className="mt-3">
            <ul className="nav flex-column">
                <li className="nav-item">
                    <a href="#" className="nav-link active"><i className="bi bi-speedometer2 me-2"></i> Dashboard</a>
                </li>
                
                <li className="nav-item">
                    <a href="#" className="nav-link has-submenu" data-target="#projectsSub">
                        <i className="bi bi-buildings me-2"></i> Projects 
                        <i className="bi bi-chevron-down float-end"></i>
                    </a>
                    <ul className="submenu" id="projectsSub">
                        <li><a href="#" className="nav-link">Request</a></li>
                        <li><a href="#" className="nav-link">Add New</a></li>
                        <li><a href="#" className="nav-link">View All</a></li>
                    </ul>
                </li>

                <li className="nav-item">
                    <a href="#" className="nav-link"><i className="bi bi-clock-history me-2"></i> Man-hours Form</a>
                </li>
                <li className="nav-item">
                    <a href="#" className="nav-link"><i className="bi bi-file-earmark-pdf me-2"></i> Documents</a>
                </li>
                <li className="nav-item">
                    <a href="#" className="nav-link"><i className="bi bi-bar-chart-line me-2"></i> Reports</a>
                </li>
                <li className="nav-item">
                    <a href="#" className="nav-link"><i className="bi bi-people me-2"></i> Users</a>
                </li>
                <hr className="text-white-50"/>
                <li className="nav-item">
                    <a href="#" className="nav-link text-danger"><i className="bi bi-box-arrow-right me-2"></i> Logout</a>
                </li>
            </ul>
        </div>
    </nav>
  )
}

