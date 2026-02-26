<?php
  /**
   * CashCue - A web-based dashboard for monitoring and managing cryptocurrency trading bots.
   * 
   * This file is the footer template included at the end of each page.
   * It contains closing HTML tags and includes necessary JavaScript files.
   * 
   */

  // avoid direct access to this file
  // This file is meant to be included in other views, not accessed directly via URL
  if (!defined('CASHCUE_APP')) exit;
?>

    </div> <!-- end of .container-fluid -->
  </div> <!-- end of #page-content-wrapper -->
</div> <!-- end of #wrapper -->

<!-- Bootstrap and ECharts scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

<!-- Main app logic -->
<!-- <script src="/cashcue/assets/js/main.js"></script> -->

<!-- Footer scripts -->
<script src="/cashcue/assets/js/footer.js"></script>
</body>
</html>


