</main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <!-- Footer Columns -->
                <div class="footer-grid">
                    <!-- Company Info -->
                    <div class="footer-column">
                        <div class="footer-logo">
                            <div class="logo-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <span class="logo-text">Trusted Midman</span>
                        </div>
                        <p class="footer-description">
                            Your trusted partner for secure online transactions. We ensure safe and reliable exchanges between buyers and sellers.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" aria-label="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="footer-column">
                        <h3 class="footer-title">Quick Links</h3>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="products.php">Marketplace</a></li>
                            <li><a href="how-it-works.php">How It Works</a></li>
                            <li><a href="about.php">About Us</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>

                    <!-- Services -->
                    <div class="footer-column">
                        <h3 class="footer-title">Services</h3>
                        <ul class="footer-links">
                            <li><a href="buyer-protection.php">Buyer Protection</a></li>
                            <li><a href="seller-protection.php">Seller Protection</a></li>
                            <li><a href="midman-services.php">Midman Services</a></li>
                            <li><a href="dispute-resolution.php">Dispute Resolution</a></li>
                            <li><a href="verification.php">Identity Verification</a></li>
                        </ul>
                    </div>

                    <!-- Support -->
                    <div class="footer-column">
                        <h3 class="footer-title">Support</h3>
                        <ul class="footer-links">
                            <li><a href="help-center.php">Help Center</a></li>
                            <li><a href="faq.php">FAQ</a></li>
                            <li><a href="terms-of-service.php">Terms of Service</a></li>
                            <li><a href="privacy-policy.php">Privacy Policy</a></li>
                            <li><a href="refund-policy.php">Refund Policy</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Newsletter -->
                <div class="footer-newsletter">
                    <div class="newsletter-content">
                        <h3 class="newsletter-title">Stay Updated</h3>
                        <p class="newsletter-description">
                            Get the latest updates and exclusive offers delivered to your inbox.
                        </p>
                        <form class="newsletter-form" action="newsletter.php" method="POST">
                            <div class="newsletter-input-group">
                                <input type="email" name="email" placeholder="Enter your email" required>
                                <button type="submit" class="newsletter-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Footer Bottom -->
                <div class="footer-bottom">
                    <div class="footer-bottom-content">
                        <div class="footer-copyright">
                            <p>&copy; <?php echo date('Y'); ?> Trusted Midman. All rights reserved.</p>
                        </div>
                        <div class="footer-bottom-links">
                            <a href="terms-of-service.php">Terms</a>
                            <a href="privacy-policy.php">Privacy</a>
                            <a href="cookies.php">Cookies</a>
                            <a href="sitemap.php">Sitemap</a>
                        </div>
                        <div class="footer-payment">
                            <span class="payment-text">We accept:</span>
                            <div class="payment-icons">
                                <i class="fab fa-cc-visa" title="Visa"></i>
                                <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                                <i class="fab fa-cc-paypal" title="PayPal"></i>
                                <i class="fab fa-cc-stripe" title="Stripe"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- JavaScript -->
    <script src="js/header.js"></script>
    <script>
        // Back to Top Button
        const backToTopBtn = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
        
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter Form
        document.querySelector('.newsletter-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            // Add your newsletter submission logic here
            alert('Thank you for subscribing! You will receive a confirmation email shortly.');
            this.reset();
        });
    </script>
</body>
</html>
