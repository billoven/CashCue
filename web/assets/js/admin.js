document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("btnSubmitOrder");
  const instrumentSelect = document.getElementById("instrument");
  const newInstrument = document.getElementById("newInstrument");
  const newFields = document.getElementById("newInstrumentFields");
  const chartDiv = document.getElementById("instrumentChart");
  const tableBody = document.getElementById("ordersTable");
  let chart = echarts.init(chartDiv);

  loadInstruments();
  fetchOrders();

  // Toggle new instrument fields
  newInstrument.addEventListener("input", () => {
    newFields.classList.toggle("d-none", newInstrument.value.trim() === "");
  });

  instrumentSelect.addEventListener("change", () => {
    if (instrumentSelect.value) renderChart(instrumentSelect.value);
  });

  btn.addEventListener("click", async () => {
    if (!validateForm()) return;

    const payload = {
      instrument_id: instrumentSelect.value,
      new_symbol: newInstrument.value.trim(),
      new_isin: document.getElementById("newIsin").value,
      new_label: document.getElementById("newLabel").value,
      order_type: document.getElementById("orderType").value,
      quantity: document.getElementById("quantity").value,
      price: document.getElementById("price").value,
      fees: document.getElementById("fees").value,
      trade_date: document.getElementById("tradeDate").value
    };

    const res = await fetch("/cashcue/api/postOrder.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.status === "success") {
      alert("✅ " + data.message);
      fetchOrders();
      loadInstruments();
      newInstrument.value = "";
      newFields.classList.add("d-none");
    } else {
      alert("❌ " + data.message);
    }
  });

  async function loadInstruments() {
    const res = await fetch("/cashcue/api/getInstruments.php");
    const instruments = await res.json();
    instrumentSelect.innerHTML = '<option value="">Select existing instrument</option>';
    instruments.forEach(i => {
      instrumentSelect.insertAdjacentHTML("beforeend", `<option value="${i.id}">${i.symbol}</option>`);
    });
  }

  async function fetchOrders() {
    const res = await fetch("/cashcue/api/getOrders.php");
    const orders = await res.json();
    tableBody.innerHTML = "";
    if (!orders.length) {
      tableBody.innerHTML = "<tr><td colspan='6' class='text-center'>No data found.</td></tr>";
      return;
    }
    for (const o of orders) {
      const row = `<tr>
        <td>${o.id}</td>
        <td>${o.symbol}</td>
        <td>${o.order_type}</td>
        <td>${o.quantity}</td>
        <td>${o.price}</td>
        <td>${o.trade_date}</td>
      </tr>`;
      tableBody.insertAdjacentHTML("beforeend", row);
    }
  }

  async function renderChart(instrumentId) {
    const res = await fetch(`/cashcue/api/getInstrumentHistory.php?id=${instrumentId}&days=60`);
    const response = await res.json();
    if (response.status !== "success" || !response.data.length) {
      chart.clear();
      chart.setOption({ title: { text: "No data available", left: "center" } });
      return;
    }
    const data = response.data.reverse();
    chart.setOption({
      title: { text: "Price Evolution", left: "center" },
      tooltip: { trigger: "axis" },
      xAxis: { type: "category", data: data.map(d => d.date) },
      yAxis: { type: "value" },
      series: [{ data: data.map(d => parseFloat(d.close_price)), type: "line", smooth: true, areaStyle: {} }]
    });
  }

  function validateForm() {
    const required = ["quantity", "price", "tradeDate"];
    for (const id of required) {
      if (!document.getElementById(id).value.trim()) {
        alert(`Please fill ${id}`);
        return false;
      }
    }
    if (!instrumentSelect.value && !newInstrument.value.trim()) {
      alert("Select or enter an instrument.");
      return false;
    }
    return true;
  }
});
