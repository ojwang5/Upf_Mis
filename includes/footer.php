<?php $user = $user ?? current_user(); ?>
<?php if ($user): ?>
  </main>
</div>
<?php endif; ?>
<footer class="footer">
  &copy; <?= date('Y') ?> <?= e(APP_ORG) ?> — <?= e(APP_NAME) ?>
</footer>
</body>
</html>
