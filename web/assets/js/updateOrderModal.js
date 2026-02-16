/**
 * updateOrderModal.js
 * ------------------------------------------------------------
 * CashCue – Order Update Modal Logic
 *
 * Business Rules for "settled":
 * ------------------------------------------------------------
 * - settled = 0 → Order not yet executed / not finalized
 * - settled = 1 → Order executed and financially effective
 *
 * Settlement Transition Policy:
 * ------------------------------------------------------------
 * ✔ 0 → 1  : Allowed (execution confirmation)
 * ✖ 1 → 0  : NOT allowed (cannot revert executed order)
 *
 * Rationale:
 * Reverting an executed order would break accounting integrity
 * and cash consistency. Corrections must be handled via
 * financial adjustment mechanisms, not state reversal.
 *
 * Supported update modes (aligned with backend):
 * ------------------------------------------------------------
 * 1) COMMENT-ONLY        → Only comment changed
 * 2) SETTLED-ONLY        → Only settled changed (0 → 1 only)
 * 3) FINANCIAL UPDATE J0 → Quantity/Price/Fees changed
 *
 * UX Principles:
 * - Strict validation before submit
 * - Clear confirmation dialogs
 * - Only modified fields are logically considered
 * - Modal closes after successful update
 *
 * Dependencies:
 * - Bootstrap 5 modal
 * - fetch API
 * - Global helpers: showAlert(type,msg)
 */

let updateOrderModal;
let updateOrderForm;
let originalOrderData = null;

// ------------------------------------------------------------
// Initialize modal & bind events
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  updateOrderModal = new bootstrap.Modal(
    document.getElementById('updateOrderModal')
  );

  updateOrderForm = document.getElementById('updateOrderForm');

  const saveBtn = document.getElementById('saveOrderUpdateBtn');
  if (!saveBtn) {
    console.error('saveOrderUpdateBtn not found');
    return;
  }

  saveBtn.addEventListener('click', onSubmitUpdateOrder);
});

// ------------------------------------------------------------
// Open modal & populate fields
// ------------------------------------------------------------
function openUpdateOrderModal(order) {
  originalOrderData = { ...order };

  updateOrderForm.elements.orderId.value = order.id;
  updateOrderForm.elements.orderQuantity.value = order.quantity;
  updateOrderForm.elements.orderPrice.value = order.price;
  updateOrderForm.elements.orderFees.value = order.fees ?? 0;
  updateOrderForm.elements.orderComment.value = order.comment ?? '';
  updateOrderForm.elements.orderSettled.checked = !!order.settled;

  updateOrderModal.show();
}

// ------------------------------------------------------------
// Detect changes compared to original
// ------------------------------------------------------------
function detectChanges(payload, original) {
  const changes = {};

  if (Number(payload.quantity) !== Number(original.quantity)) changes.quantity = payload.quantity;
  if (Number(payload.price) !== Number(original.price))       changes.price = payload.price;
  if (Number(payload.fees) !== Number(original.fees ?? 0))   changes.fees = payload.fees;
  if ((payload.comment ?? '') !== (original.comment ?? ''))  changes.comment = payload.comment;
  if (Number(payload.settled) !== Number(original.settled ?? 0)) changes.settled = payload.settled;

  return changes;
}

// ------------------------------------------------------------
// Handle save click
// ------------------------------------------------------------
function onSubmitUpdateOrder(e) {
  e.preventDefault();

  const formData = new FormData(updateOrderForm);

  const payload = {
    order_id: Number(formData.get('order_id')),
    quantity: Number(formData.get('quantity')),
    price: Number(formData.get('price')),
    fees: Number(formData.get('fees')),
    comment: (formData.get('comment') ?? '').trim(),
    settled: updateOrderForm.elements.orderSettled.checked ? 1 : 0
  };

  const changes = detectChanges(payload, originalOrderData);

  if (Object.keys(changes).length === 0) {
    showAlert('info', 'No changes detected');
    return;
  }

  // ------------------------------------------------------------
  // ENFORCE SETTLEMENT BUSINESS RULE
  // ------------------------------------------------------------
  if ('settled' in changes) {
    const oldValue = Number(originalOrderData.settled ?? 0);
    const newValue = Number(payload.settled);

    if (oldValue === 1 && newValue === 0) {
      showAlert(
        'danger',
        'Reverting an executed order (settled = 1) is not allowed.'
      );

      // Restore checkbox visually
      updateOrderForm.elements.orderSettled.checked = true;
      return;
    }
  }

  // ------------------------------------------------------------
  // Determine update mode
  // ------------------------------------------------------------
  let mode = 'financial_update';

  if ('comment' in changes && Object.keys(changes).length === 1) {
    mode = 'comment_only';
  } else if ('settled' in changes && Object.keys(changes).length === 1) {
    mode = 'settled_only';
  }

  // ------------------------------------------------------------
  // Confirmation per mode
  // ------------------------------------------------------------
  let confirmMsg = '';

  switch(mode) {
    case 'comment_only':
      confirmMsg = 'Confirm comment update? (no financial impact)';
      break;

    case 'settled_only':
      confirmMsg = 'Confirm settlement validation?';
      break;

    case 'financial_update':
      confirmMsg = 'Confirm order update? This will recalculate cash balance.';
      break;
  }

  if (!confirm(confirmMsg)) return;

  payload.comment_only = (mode === 'comment_only') ? 1 : 0;

  submitUpdate(payload, mode);
}

// ------------------------------------------------------------
// Submit to backend
// ------------------------------------------------------------
function submitUpdate(payload, mode) {

  console.log('Submitting update with payload:', payload, 'and mode:', mode);

  fetch('/cashcue/api/updateOrder.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) throw new Error(data.error || 'Update failed');

    updateOrderModal.hide();

    document.dispatchEvent(new Event('cashcue:order-updated'));

    let msg = '';

    switch(mode) {
      case 'comment_only':
        msg = 'Comment updated successfully';
        break;

      case 'settled_only':
        msg = 'Order marked as executed';
        break;

      case 'financial_update':
        msg = 'Order updated and cash recalculated';
        break;
    }

    showAlert('success', msg);
  })
  .catch(err => {
    console.error(err);
    showAlert('danger', err.message);
  });
}

// ------------------------------------------------------------
// Expose globally
// ------------------------------------------------------------
window.CashCue = window.CashCue || {};
window.CashCue.openUpdateOrderModal = openUpdateOrderModal;
