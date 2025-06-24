    </div> <!-- Sluit container van header -->
    <footer class="footer-main mt-5 pt-5 pb-3">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-hdd-network fs-2 me-2 text-turquoise"></i>
                        <span class="fs-3 fw-bold text-white">Cloudmarkplaats</span>
                    </div>
                    <p class="text-light mb-3" style="max-width: 340px;">
                        A marketplace for IT experts, nerds and datacenter specialists to trade hardware without unnecessary intervention.
                    </p>
                    <div class="d-flex gap-3 mb-3">
                        <a href="#" class="footer-social"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="footer-social"><i class="bi bi-x"></i></a>
                        <a href="#" class="footer-social"><i class="bi bi-github"></i></a>
                        <a href="#" class="footer-social"><i class="bi bi-discord"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Marketplace</h5>
                    <ul class="footer-links">
                        <li><a href="/products">Browse All</a></li>
                        <li><a href="/products?category=Servers">Servers</a></li>
                        <li><a href="/products?category=Networking">Networking</a></li>
                        <li><a href="/products?category=Storage">Storage</a></li>
                        <li><a href="/product/add">Sell Hardware</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Community</h5>
                    <ul class="footer-links">
                        <li><a href="/forum">Forum</a></li>
                        <li><a href="#">Server Discussions</a></li>
                        <li><a href="#">Marketplace Talk</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Events</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Help & Info</h5>
                    <ul class="footer-links">
                        <li><a href="/about">About Us</a></li>
                        <li><a href="/faq">FAQ</a></li>
                        <li><a href="/contact">Contact</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Account</h5>
                    <ul class="footer-links">
                        <li><a href="/auth/register">Register</a></li>
                        <li><a href="/auth/login">Sign In</a></li>
                        <li><a href="/profile">My Profile</a></li>
                        <li><a href="#">My Listings</a></li>
                        <li><a href="/messages">Messages</a></li>
                    </ul>
                </div>
            </div>
            <hr class="custom-hr my-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="text-light small mb-2 mb-md-0">
                    &copy; <?= date('Y') ?> Cloudmarkplaats. All rights reserved.
                </div>
                <div class="footer-bottom-links">
                    <a href="#" class="me-3">Terms</a>
                    <a href="#" class="me-3">Privacy</a>
                    <a href="#">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activeer tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Activeer popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });

        // Bevestigingsdialogen
        document.querySelectorAll('[data-confirm]').forEach(function(element) {
            element.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html> 