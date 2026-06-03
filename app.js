document.addEventListener('DOMContentLoaded', () => {
    const appointmentForm = document.getElementById('appointmentForm');
    const appointmentTableBody = document.getElementById('appointmentTableBody');
    const submitBtn = document.getElementById('submitBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const refreshBtn = document.getElementById('refreshBtn');
    const messageContainer = document.getElementById('messageContainer');
    let lastUpdatedId = null;

    // Initial Load
    loadAppointments();

    // Form Submission
    appointmentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(appointmentForm);
        const data = Object.fromEntries(formData.entries());
        
        // Basic validation on frontend as well
        if (!validateForm(data)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                showMessage(result.message, 'success');
                lastUpdatedId = data.id || result.id || null; // Use data.id (update) or result.id (create)
                resetForm();
                loadAppointments();
            } else {
                showMessage(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('An unexpected error occurred.', 'danger');
        }
    });

    // Refresh Button
    refreshBtn.addEventListener('click', loadAppointments);

    // Cancel Edit
    cancelEditBtn.addEventListener('click', resetForm);

    async function loadAppointments() {
        try {
            const response = await fetch('api.php');
            const appointments = await response.json();
            renderTable(appointments);
            if (lastUpdatedId) {
                highlightRow(lastUpdatedId);
                lastUpdatedId = null;
            }
        } catch (error) {
            console.error('Error loading appointments:', error);
            showMessage('Failed to load appointments.', 'danger');
        }
    }

    function renderTable(appointments) {
        appointmentTableBody.innerHTML = '';
        if (appointments.length === 0) {
            appointmentTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No appointments found.</td></tr>';
            return;
        }

        appointments.forEach(app => {
            const tr = document.createElement('tr');
            tr.id = `row-${app.id}`;
            tr.innerHTML = `
                <td><strong>#${app.id}</strong></td>
                <td>${app.patient_name}</td>
                <td><span class="badge bg-light text-dark border">${app.doctor_name || 'N/A'}</span></td>
                <td>${app.email}</td>
                <td>${app.mobile}</td>
                <td>${app.appointment_date}</td>
                <td>${app.appointment_time}</td>
                <td>
                    <select class="form-select form-select-sm status-dropdown" data-id="${app.id}">
                        <option value="Pending" ${app.status === 'Pending' ? 'selected' : ''}>Pending</option>
                        <option value="Confirmed" ${app.status === 'Confirmed' ? 'selected' : ''}>Confirmed</option>
                        <option value="Cancelled" ${app.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                    </select>
                </td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="${app.id}" data-patient_name="${app.patient_name}" data-doctor_name="${app.doctor_name}" data-email="${app.email}" data-mobile="${app.mobile}" data-appointment_date="${app.appointment_date}" data-appointment_time="${app.appointment_time}">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${app.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            appointmentTableBody.appendChild(tr);
        });

        // Add event listeners for dynamic elements
        document.querySelectorAll('.status-dropdown').forEach(select => {
            select.addEventListener('change', async (e) => {
                const id = e.target.dataset.id;
                const status = e.target.value;
                updateStatus(id, status);
            });
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const data = e.currentTarget.dataset;
                populateForm(data);
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                if (confirm('Are you sure you want to delete this appointment?')) {
                    deleteAppointment(id);
                }
            });
        });
    }

    async function updateStatus(id, status) {
        try {
            const response = await fetch('api.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id, status, status_only: true })
            });
            const result = await response.json();
            if (result.status === 'success') {
                highlightRow(id);
            } else {
                showMessage('Error updating status: ' + result.message, 'danger');
                loadAppointments(); 
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    async function deleteAppointment(id) {
        try {
            const response = await fetch('api.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            if (result.status === 'success') {
                showMessage('Appointment deleted successfully', 'success');
                loadAppointments();
            } else {
                showMessage('Error deleting appointment: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function populateForm(data) {
        document.getElementById('appointmentId').value = data.id;
        document.getElementById('patientName').value = data.patient_name;
        document.getElementById('doctorName').value = data.doctor_name || '';
        document.getElementById('email').value = data.email;
        document.getElementById('mobile').value = data.mobile;
        document.getElementById('appointmentDate').value = data.appointment_date;
        document.getElementById('appointmentTime').value = data.appointment_time;
        
        submitBtn.textContent = 'Update Appointment';
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-success');
        cancelEditBtn.classList.remove('d-none');
    }

    function resetForm() {
        appointmentForm.reset();
        document.getElementById('appointmentId').value = '';
        submitBtn.textContent = 'Book Appointment';
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-primary');
        cancelEditBtn.classList.add('d-none');
    }

    function validateForm(data) {
        if (!data.patient_name || !data.doctor_name || !data.email || !data.mobile || !data.appointment_date || !data.appointment_time) {
            showMessage('All fields are mandatory.', 'warning');
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            showMessage('Please enter a valid email address.', 'warning');
            return false;
        }
        if (!/^[0-9]{10}$/.test(data.mobile)) {
            showMessage('Mobile number must be exactly 10 digits.', 'warning');
            return false;
        }
        const selectedDate = new Date(data.appointment_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            showMessage('Appointment date cannot be in the past.', 'warning');
            return false;
        }

        const time = data.appointment_time;
        const hour = parseInt(time.split(':')[0]);
        if (hour < 9 || hour >= 18) {
            showMessage('Appointments are only available between 09:00 AM and 06:00 PM.', 'warning');
            return false;
        }

        return true;
    }

    function showMessage(message, type) {
        messageContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(messageContainer.firstElementChild);
            if (alert) alert.close();
        }, 5000);
    }

    function highlightRow(id) {
        const row = document.getElementById(`row-${id}`);
        if (row) {
            row.classList.add('highlight-row');
            setTimeout(() => row.classList.remove('highlight-row'), 3000);
        }
    }
});