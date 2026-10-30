<?php
// marketplace/includes/footer.php
?>
</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

<?php
// Page-specific JavaScript (if any)
if (isset($pageJS)) {
    echo '<script src="' . SITE_URL . '/assets/js/' . $pageJS . '"></script>';
}
?>
</body>
</html>