<?php
/**
 * Cash domain local navigation
 *
 * Expected variable (optional):
 *   $page = 'cash' | 'manage_cash'
 */

// Defensive default
$page = $page ?? '';
?>

<div class="mb-4">
  <ul class="nav nav-pills">
    <li class="nav-item">
      <a class="nav-link <?= $page === 'cash' ? 'active' : '' ?>"
         href="/cashcue/views/cash.php">
        <i class="bi bi-graph-up me-1"></i>
        Overview
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $page === 'manage_cash' ? 'active' : '' ?>"
         href="/cashcue/views/admin/manage_cash.php">
        <i class="bi bi-list-check me-1"></i>
        Movements
      </a>
    </li>
  </ul>
</div>
