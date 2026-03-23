// CloudKart Landing Site - Main JavaScript

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');
    const navCTA = document.getElementById('navCTA');

    if (mobileMenuToggle && navMenu) {
        mobileMenuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            mobileMenuToggle.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                navMenu.classList.remove('active');
                mobileMenuToggle.classList.remove('active');
            }
        });

        // Close menu when clicking on a link (except dropdown toggles)
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                // Don't close if it's a dropdown toggle
                if (link.getAttribute('onclick') === 'return false;') {
                    return;
                }
                navMenu.classList.remove('active');
                mobileMenuToggle.classList.remove('active');
            });
        });
    }

    // Mobile Dropdown Toggle
    const dropdowns = document.querySelectorAll('.nav-menu .dropdown');
    dropdowns.forEach(dropdown => {
        const dropdownToggle = dropdown.querySelector('a[onclick="return false;"]');
        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', (e) => {
                e.preventDefault();
                // On mobile, toggle the dropdown
                if (window.innerWidth <= 768) {
                    dropdown.classList.toggle('active');
                }
            });
        }
    });

    // Show/hide CTA button based on screen size
    function handleResize() {
        if (navCTA) {
            if (window.innerWidth <= 768) {
                navCTA.style.display = 'none';
            } else {
                navCTA.style.display = 'inline-flex';
            }
        }
    }
    handleResize();
    window.addEventListener('resize', handleResize);

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    });

    // Check for active session
    const dashboardWrapper = document.getElementById('navDashboardWrapper');
    const loginLink = document.getElementById('navLogin');

    if (localStorage.getItem('cloudkart_user')) {
        if (dashboardWrapper) dashboardWrapper.style.display = 'block';
        if (loginLink) loginLink.style.display = 'none';
        if (navCTA) navCTA.textContent = 'Launch Another';
    }
});
