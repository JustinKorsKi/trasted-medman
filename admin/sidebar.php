<a href="verifications.php" class="nav-link">
    <i class="fas fa-id-card"></i> Verifications
    <?php if($pending_verifications > 0): ?>
        <span class="badge"><?php echo $pending_verifications; ?></span>
    <?php endif; ?>
</a>