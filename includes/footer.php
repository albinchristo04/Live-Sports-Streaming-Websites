<?php
// footer.php — closes <main>, renders site footer, Adsterra script, and share.js
?>
  </main>
  <footer class="site-footer">
    <p>&copy; <?= date('Y') ?> Live Sports Streaming. For entertainment purposes only.</p>
  </footer>
  <!-- Adsterra popup -->
  <?= getAdsterraScript() ?>
  <script src="/assets/js/share.js"></script>
</body>
</html>
