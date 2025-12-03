/*
* Version: 1.0.0
* Template: Qompac-Ui - Responsive Bootstrap 5 Admin Dashboard Template
* Author: iqonic.design
* Author URL: https://iqonic.design/
* Design and Developed by: iqonic.design
* Description: This file contains the script for initialize & listener Template.
*/

(function(){
    "use strict";
    
    const sidebarInit = () => {
        const sidebarResponsive = document.querySelector('[data-sidebar="responsive"]')
        if (window.innerWidth < 1025) {
            if (sidebarResponsive !== null) {
                if (!sidebarResponsive.classList.contains('sidebar-mini')) {
                    sidebarResponsive.classList.add('sidebar-mini')
                }
            } else {
                if (sidebarResponsive !== null) {
                    if (sidebarResponsive.classList.contains('sidebar-mini')) {
                        sidebarResponsive.classList.remove('sidebar-mini')
                    }
                }
            }
        }
    }
    sidebarInit()
    window.addEventListener('resize', function (event) {
        sidebarInit()
    });

    /*-------------Sidebar Toggle Start-----------------*/
   
    const sidebarToggle = (elem) => {
        elem.addEventListener('click', (e) => {
        const sidebar = document.querySelector('.sidebar')
        if (sidebar.classList.contains('sidebar-mini')) {
            sidebar.classList.remove('sidebar-mini')
           
        } else {
            sidebar.classList.add('sidebar-mini')
            
        }
        })
    }
    const sidebarToggleBtn = document.querySelectorAll('[data-toggle="sidebar"]')
    const sidebar = document.querySelector('[data-toggle="main-sidebar"]')
    if (sidebar !== null) {
        const sidebarActiveItem = sidebar.querySelectorAll('.active')
        Array.from(sidebarActiveItem, (elem) => {
            elem.classList.add('active')
            if (!elem.closest('ul').classList.contains('iq-main-menu')) {
                const childMenu = elem.closest('ul')
                const parentMenu = childMenu.closest('li').querySelector('.nav-link')
                parentMenu.classList.add('active')
                new bootstrap.Collapse(childMenu, {
                toggle: true
                });
            }
        })
        const collapseElementList = [].slice.call(sidebar.querySelectorAll('.collapse'))
        const collapseList = collapseElementList.map(function (collapseEl) {
            collapseEl.addEventListener('show.bs.collapse', function (elem) {
                collapseEl.closest('li').classList.add('active')
            })
            collapseEl.addEventListener('hidden.bs.collapse', function (elem) {
                collapseEl.closest('li').classList.remove('active')
            })
        })

        const active = sidebar.querySelector('.active')
        if (active !== null) {
            active.closest('li').classList.add('active')
        }
    }
    Array.from(sidebarToggleBtn, (sidebarBtn) => {
        sidebarToggle(sidebarBtn)
    })
    /*-------------Sidebar Toggle End-----------------*/
})()
