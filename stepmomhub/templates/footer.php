<?php
// File: templates/footer.php
?>
    </main> <!-- Menutup .container dari header -->

    <footer class="site-footer">
        <div class="container footer-container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('site_title') ?? 'Situs Video Anda'); ?>. Seluruh Hak Cipta.</p>
            <p>Didesain ulang dengan <i class="ph-fill ph-heart" style="color:var(--primary-accent); vertical-align: middle;"></i> oleh Gemini.</p>
        </div>
    </footer>
</body>
</html>
