</div><!-- /.main-content -->
    </div><!-- /.container -->

    <script>
    // Common JavaScript functions
    
    // Function to show confirmation dialog
    function confirmDelete(message = 'Are you sure you want to delete this item?') {
        return confirm(message);
    }

    // Function to format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES'
        }).format(amount);
    }

    // Function to format dates
    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-KE', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        // Add mobile menu toggle button if screen width is small
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.header');
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '☰';
            toggleBtn.className = 'menu-toggle';
            toggleBtn.style.cssText = `
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0.5rem;
            `;
            
            header.insertBefore(toggleBtn, header.firstChild);
            
            // Initially hide sidebar on mobile
            sidebar.style.display = 'none';
            mainContent.style.marginLeft = '0';
            
            toggleBtn.addEventListener('click', function() {
                if (sidebar.style.display === 'none') {
                    sidebar.style.display = 'flex';
                    mainContent.style.marginLeft = '250px';
                } else {
                    sidebar.style.display = 'none';
                    mainContent.style.marginLeft = '0';
                }
            });
        }
    });

    // Form validation
    function validateForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return true;

        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = 'red';
                
                // Add error message if it doesn't exist
                if (!field.nextElementSibling?.classList.contains('error-message')) {
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message';
                    errorMsg.style.color = 'red';
                    errorMsg.style.fontSize = '0.8rem';
                    errorMsg.style.marginTop = '0.25rem';
                    errorMsg.textContent = 'This field is required';
                    field.parentNode.insertBefore(errorMsg, field.nextSibling);
                }
            } else {
                field.style.borderColor = '';
                const errorMsg = field.nextElementSibling;
                if (errorMsg?.classList.contains('error-message')) {
                    errorMsg.remove();
                }
            }
        });

        return isValid;
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });

    // Add loading indicator for form submissions
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Processing...';
                    
                    // Store original text to restore it if form submission fails
                    submitBtn.dataset.originalText = originalText;
                }
            });
        });
    });
    </script>
</body>
</html>
