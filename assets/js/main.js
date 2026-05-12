// NexusERP — Main JS

// Modal helpers
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => alert.style.opacity = '0', 3500);
  setTimeout(() => alert.remove(), 4000);
});

// Confirm delete
function confirmDelete(url, msg) {
  if (confirm(msg || 'Are you sure you want to delete this record?')) {
    window.location.href = url;
  }
}

// AJAX form submit helper
async function submitForm(formId, url, successCb) {
  const form = document.getElementById(formId);
  if (!form) return;
  const data = new FormData(form);
  const res = await fetch(url, { method: 'POST', body: data });
  const json = await res.json();
  if (json.success) {
    successCb && successCb(json);
  } else {
    showAlert(json.error || 'An error occurred', 'error');
  }
}

function showAlert(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `alert alert-${type}`;
  const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
  el.innerHTML = `<i class="fa ${icons[type] || icons.info}"></i> ${msg}`;
  const content = document.querySelector('.page-content');
  if (content) content.prepend(el);
  setTimeout(() => el.style.opacity = '0', 3500);
  setTimeout(() => el.remove(), 4000);
}

// Number formatting
function formatMoney(n) {
  return '$' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

// Search filter
function filterTable(inputId, tableId) {
  const q = document.getElementById(inputId)?.value?.toLowerCase() || '';
  document.querySelectorAll(`#${tableId} tbody tr`).forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
