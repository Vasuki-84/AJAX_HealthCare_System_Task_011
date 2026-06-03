/**
 * app.js — AJAX Patient Appointment Management
 * Strategy: keep a local `appointments[]` array in sync with the DB.
 * Every CRUD operation updates the array instantly → re-renders the table
 * immediately with NO extra GET round-trip.
 */

'use strict';

const API_URL = 'api.php';

// ── In-memory store ───────────────────────────────────────────────────
let appointments = [];   // single source of truth for the UI

// ── DOM references ────────────────────────────────────────────────────
const form             = document.getElementById('appointmentForm');
const appointmentId    = document.getElementById('appointmentId');
const patientName      = document.getElementById('patientName');
const emailInput       = document.getElementById('email');
const mobileInput      = document.getElementById('mobile');
const appointmentDate  = document.getElementById('appointmentDate');
const appointmentTime  = document.getElementById('appointmentTime');
const submitBtn        = document.getElementById('submitBtn');
const submitBtnText    = document.getElementById('submitBtnText');
const formTitle        = document.getElementById('formTitle');
const cancelEditBtn    = document.getElementById('cancelEditBtn');
const appointmentsBody = document.getElementById('appointmentsBody');
const tableWrapper     = document.getElementById('tableWrapper');
const tableLoader      = document.getElementById('tableLoader');
const emptyState       = document.getElementById('emptyState');
const totalCount       = document.getElementById('totalCount');

// ── Minimum date = today ──────────────────────────────────────────────
appointmentDate.min = new Date().toISOString().split('T')[0];

// ── On page load: fetch once from server ─────────────────────────────
document.addEventListener('DOMContentLoaded', loadAppointments);

// ═════════════════════════════════════════════════════════════════════
// READ — initial load from server (GET)
// ═════════════════════════════════════════════════════════════════════
async function loadAppointments() {
  showLoader(true);
  try {
    const res = await apiRequest('GET');
    if (res.success) {
      appointments = res.data || [];
      renderTable();
    } else {
      showAlert('danger', 'Database error: ' + res.message);
    }
  } catch (err) {
    showAlert('danger', 'API failure: ' + err.message);
  } finally {
    showLoader(false);
  }
}

// ═════════════════════════════════════════════════════════════════════
// CREATE / UPDATE — form submit
// ═════════════════════════════════════════════════════════════════════
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  clearValidation();

  const data         = collectFormData();
  const clientErrors = validateClient(data);
  if (clientErrors.length > 0) { showClientErrors(clientErrors); return; }

  const isUpdate = appointmentId.value !== '';
  setBtnLoading(true, isUpdate ? 'Updating…' : 'Booking…');

  try {
    if (isUpdate) {
      // ── UPDATE (PUT) ──────────────────────────────────────────────
      const id        = parseInt(appointmentId.value);
      const statusSel = document.querySelector(`#row-${id} select`);
      data.id         = id;
      data.status     = statusSel ? statusSel.value : 'Pending';

      const res = await apiRequest('PUT', data);
      if (res.success) {
        // Instant DOM update: replace the record in local array
        const idx = appointments.findIndex(a => a.id === id);
        if (idx !== -1) {
          appointments[idx] = {
            ...appointments[idx],   // keep created_at
            patient_name     : data.patient_name,
            email            : data.email,
            mobile           : data.mobile,
            appointment_date : data.appointment_date,
            appointment_time : data.appointment_time,
            status           : data.status
          };
        }
        renderTable();
        highlightRow(id);
        showAlert('success', 'Appointment updated successfully!');
        resetForm();
      } else {
        showAlert('danger', res.message || 'Update failed.');
      }

    } else {
      // ── CREATE (POST) ─────────────────────────────────────────────
      const res = await apiRequest('POST', data);
      if (res.success) {
        // Build the new record object with the DB-assigned id
        const newAppt = {
          id               : res.data.id,
          patient_name     : data.patient_name,
          email            : data.email,
          mobile           : data.mobile,
          appointment_date : data.appointment_date,
          appointment_time : data.appointment_time,
          status           : 'Pending',
          created_at       : new Date().toISOString()
        };
        // Instant DOM update: push to local array
        appointments.push(newAppt);
        renderTable();
        highlightRow(newAppt.id);
        showAlert('success', 'Appointment booked successfully!');
        resetForm();
      } else {
        showAlert('danger', res.message || 'Booking failed.');
      }
    }
  } catch (err) {
    showAlert('danger', 'API failure: ' + err.message);
  } finally {
    setBtnLoading(false, isUpdate ? 'Update Appointment' : 'Book Appointment');
  }
});

// ═════════════════════════════════════════════════════════════════════
// DELETE (DELETE)
// ═════════════════════════════════════════════════════════════════════
async function deleteAppointment(id) {
  if (!confirm('Are you sure you want to delete this appointment?')) return;

  // Instant DOM update: remove from array immediately (optimistic)
  const backup = [...appointments];
  appointments  = appointments.filter(a => a.id !== id);
  renderTable();

  try {
    const res = await apiRequest('DELETE', { id: parseInt(id) });
    if (res.success) {
      showAlert('success', 'Appointment deleted successfully!');
    } else {
      // Rollback on failure
      appointments = backup;
      renderTable();
      showAlert('danger', res.message || 'Delete failed.');
    }
  } catch (err) {
    appointments = backup;
    renderTable();
    showAlert('danger', 'API failure: ' + err.message);
  }
}

// ═════════════════════════════════════════════════════════════════════
// UPDATE STATUS (PATCH)
// ═════════════════════════════════════════════════════════════════════
async function updateStatus(id, newStatus) {
  // Instant DOM update: update local array immediately (optimistic)
  const appt = appointments.find(a => a.id === id);
  const prevStatus = appt ? appt.status : null;
  if (appt) appt.status = newStatus;

  // Re-style the select immediately — no re-render needed
  const sel = document.querySelector(`#row-${id} select`);
  if (sel) applyStatusClass(sel, newStatus);

  try {
    const res = await apiRequest('PATCH', { id: parseInt(id), status: newStatus });
    if (res.success) {
      showAlert('success', `Status updated to "${newStatus}"`);
    } else {
      // Rollback
      if (appt) appt.status = prevStatus;
      if (sel)  applyStatusClass(sel, prevStatus);
      showAlert('danger', res.message || 'Status update failed.');
    }
  } catch (err) {
    if (appt) appt.status = prevStatus;
    if (sel)  applyStatusClass(sel, prevStatus);
    showAlert('danger', 'API failure: ' + err.message);
  }
}

// ═════════════════════════════════════════════════════════════════════
// EDIT — populate form
// ═════════════════════════════════════════════════════════════════════
function editAppointment(appt) {
  appointmentId.value   = appt.id;
  patientName.value     = appt.patient_name;
  emailInput.value      = appt.email;
  mobileInput.value     = appt.mobile;
  appointmentDate.value = appt.appointment_date;
  appointmentTime.value = appt.appointment_time;

  formTitle.textContent     = 'Edit Appointment';
  submitBtnText.textContent = 'Update Appointment';
  cancelEditBtn.classList.remove('d-none');
  submitBtn.classList.remove('btn-primary');
  submitBtn.classList.add('btn-warning');

  document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
}

// ═════════════════════════════════════════════════════════════════════
// CANCEL EDIT
// ═════════════════════════════════════════════════════════════════════
function cancelEdit() {
  resetForm();
  showAlert('info', 'Edit cancelled.');
}

// ═════════════════════════════════════════════════════════════════════
// RENDER TABLE — reads from local `appointments[]`
// ═════════════════════════════════════════════════════════════════════
function renderTable() {
  totalCount.textContent = appointments.length;

  if (appointments.length === 0) {
    tableWrapper.classList.add('d-none');
    emptyState.classList.remove('d-none');
    return;
  }

  emptyState.classList.add('d-none');
  tableWrapper.classList.remove('d-none');

  appointmentsBody.innerHTML = appointments.map((appt, index) => `
    <tr id="row-${appt.id}">
      <td class="fw-semibold text-muted">${index + 1}</td>
      <td class="fw-semibold">${escHtml(appt.patient_name)}</td>
      <td>
        <a href="mailto:${escHtml(appt.email)}" class="text-decoration-none">
          ${escHtml(appt.email)}
        </a>
      </td>
      <td>${escHtml(appt.mobile)}</td>
      <td>
        <span class="badge bg-secondary">
          <i class="bi bi-calendar3 me-1"></i>${formatDate(appt.appointment_date)}
        </span>
      </td>
      <td>
        <span class="badge bg-info text-dark">
          <i class="bi bi-clock me-1"></i>${formatTime(appt.appointment_time)}
        </span>
      </td>
      <td>
        <select class="form-select form-select-sm ${statusClass(appt.status)}"
                onchange="updateStatus(${appt.id}, this.value)">
          <option value="Pending"   ${appt.status === 'Pending'   ? 'selected' : ''}>⏳ Pending</option>
          <option value="Confirmed" ${appt.status === 'Confirmed' ? 'selected' : ''}>✅ Confirmed</option>
          <option value="Cancelled" ${appt.status === 'Cancelled' ? 'selected' : ''}>❌ Cancelled</option>
        </select>
      </td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-warning me-1"
                onclick='editAppointment(${JSON.stringify(appt)})'
                title="Edit">
          <i class="bi bi-pencil-fill"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger"
                onclick="deleteAppointment(${appt.id})"
                title="Delete">
          <i class="bi bi-trash-fill"></i>
        </button>
      </td>
    </tr>
  `).join('');
}

// ═════════════════════════════════════════════════════════════════════
// CORE API REQUEST
// ═════════════════════════════════════════════════════════════════════
async function apiRequest(method, body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  if (body !== null && method !== 'GET') {
    options.body = JSON.stringify(body);
  }
  const res  = await fetch(API_URL, options);
  const json = await res.json();
  if (res.status >= 500) throw new Error(json.message || `Server error ${res.status}`);
  return json;
}

// ═════════════════════════════════════════════════════════════════════
// VALIDATION
// ═════════════════════════════════════════════════════════════════════
function validateClient(data) {
  const errors = [];

  if (!data.patient_name.trim()) {
    errors.push({ field: 'patientName', errId: 'nameError', msg: 'Patient name is required.' });
  } else if (!/^[a-zA-Z\s]{2,100}$/.test(data.patient_name.trim())) {
    errors.push({ field: 'patientName', errId: 'nameError', msg: 'Name must be 2–100 alphabetic characters.' });
  }

  if (!data.email.trim()) {
    errors.push({ field: 'email', errId: 'emailError', msg: 'Email is required.' });
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
    errors.push({ field: 'email', errId: 'emailError', msg: 'Enter a valid email address.' });
  }

  if (!data.mobile.trim()) {
    errors.push({ field: 'mobile', errId: 'mobileError', msg: 'Mobile number is required.' });
  } else if (!/^\+?[0-9]{10,15}$/.test(data.mobile.trim())) {
    errors.push({ field: 'mobile', errId: 'mobileError', msg: 'Mobile must be 10–15 digits.' });
  }

  if (!data.appointment_date) {
    errors.push({ field: 'appointmentDate', errId: 'dateError', msg: 'Appointment date is required.' });
  } else if (data.appointment_date < new Date().toISOString().split('T')[0]) {
    errors.push({ field: 'appointmentDate', errId: 'dateError', msg: 'Date cannot be in the past.' });
  }

  if (!data.appointment_time) {
    errors.push({ field: 'appointmentTime', errId: 'timeError', msg: 'Appointment time is required.' });
  }

  return errors;
}

function showClientErrors(errors) {
  errors.forEach(({ field, errId, msg }) => {
    const input = document.getElementById(field);
    const errEl = document.getElementById(errId);
    if (input) input.classList.add('is-invalid');
    if (errEl) errEl.textContent = msg;
  });
}

function clearValidation() {
  ['patientName', 'email', 'mobile', 'appointmentDate', 'appointmentTime'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('is-invalid', 'is-valid');
  });
  ['nameError', 'emailError', 'mobileError', 'dateError', 'timeError'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
  });
}

// ═════════════════════════════════════════════════════════════════════
// HELPERS
// ═════════════════════════════════════════════════════════════════════
function collectFormData() {
  return {
    patient_name     : patientName.value,
    email            : emailInput.value,
    mobile           : mobileInput.value,
    appointment_date : appointmentDate.value,
    appointment_time : appointmentTime.value
  };
}

function resetForm() {
  form.reset();
  appointmentId.value   = '';
  clearValidation();
  formTitle.textContent     = 'Book New Appointment';
  submitBtnText.textContent = 'Book Appointment';
  cancelEditBtn.classList.add('d-none');
  submitBtn.classList.remove('btn-warning');
  submitBtn.classList.add('btn-primary');
}

function setBtnLoading(loading, text) {
  submitBtn.disabled        = loading;
  submitBtnText.textContent = text;
  const icon = submitBtn.querySelector('i');
  if (icon) icon.className  = loading ? 'bi bi-hourglass-split me-2' : 'bi bi-check-circle me-2';
}

function showLoader(show) {
  tableLoader.classList.toggle('d-none', !show);
  if (show) {
    tableWrapper.classList.add('d-none');
    emptyState.classList.add('d-none');
  }
}

function showAlert(type, message) {
  const alertBox  = document.getElementById('alertBox');
  const alertMsg  = document.getElementById('alertMsg');
  const alertIcon = document.getElementById('alertIcon');
  const iconMap   = {
    success : 'bi-check-circle-fill',
    danger  : 'bi-exclamation-triangle-fill',
    info    : 'bi-info-circle-fill',
    warning : 'bi-exclamation-circle-fill'
  };
  alertBox.className      = `alert alert-${type} alert-dismissible fade show mb-4`;
  alertIcon.className     = `bi ${iconMap[type] || 'bi-info-circle-fill'} me-2`;
  alertMsg.textContent    = message;
  alertBox.classList.remove('d-none');
  setTimeout(() => {
    try { bootstrap.Alert.getOrCreateInstance(alertBox).close(); } catch (_) {}
  }, 4000);
}

function highlightRow(id) {
  // Wait one tick for the row to appear in the DOM after renderTable()
  setTimeout(() => {
    const row = document.getElementById(`row-${id}`);
    if (!row) return;
    row.classList.add('table-success');
    setTimeout(() => row.classList.remove('table-success'), 1800);
  }, 50);
}

function statusClass(status) {
  if (status === 'Confirmed') return 'text-success border-success';
  if (status === 'Cancelled') return 'text-danger border-danger';
  return 'text-warning border-warning';
}

function applyStatusClass(sel, status) {
  sel.className = `form-select form-select-sm ${statusClass(status)}`;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr + 'T00:00:00')
    .toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(timeStr) {
  if (!timeStr) return '—';
  const [h, m] = timeStr.split(':');
  const hour   = parseInt(h, 10);
  return `${hour % 12 || 12}:${m} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function escHtml(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(String(str)));
  return div.innerHTML;
}