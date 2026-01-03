<div class="modal fade" id="cashModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="cashModalTitle">Cash Movement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="cashForm">
          <input type="hidden" id="cash_id">

          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="datetime-local" id="cash_date" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Type</label>
            <select id="cash_type" class="form-select" required>
              <option value="BUY">Buy (computed)</option>
              <option value="SELL">Sell (computed)</option>
              <option value="DEPOSIT">Deposit</option>
              <option value="WITHDRAWAL">Withdrawal</option>
              <option value="FEES">Fees</option>
              <option value="ADJUSTMENT">Adjustment</option>
              <option value="DIVIDEND">Dividend</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Amount (€)</label>
            <input type="number" step="0.01" id="cash_amount" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Comment</label>
            <input type="text" id="cash_comment" class="form-control">
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="cashSaveBtn">Save</button>
      </div>

    </div>
  </div>
</div>
