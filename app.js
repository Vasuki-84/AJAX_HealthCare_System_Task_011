document.addEventListener('DOMContentLoaded', () => {
    const appointmentForm = document.getElementById('appointmentForm');
    const appointmentTableBody = document.getElementById('appointmentTableBody');
    const submitBtn = document.getElementById('submitBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const refreshBtn = document.getElementById('refreshBtn');
    const logoutBtn = document.getElementById('logoutBtn');

    // Logout
    logoutBtn.addEventListener('click', async () => {
        if (confirm('Are you sure you want to logout?')) {
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    window.location.href = 'login.php';
                }
            } catch (error) {
                console.error('Logout failed:', error);
            }
        }
    });

    // Initial Load
    loadAppointments();

    // Form Submission
    appointmentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const formData = new FormData(appointmentForm);
        const data = Object.fromEntries(formData.entries());
        
        // Basic validation on frontend as well
        if (!validateForm(data)) return;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                alert(result.message);
                resetForm();
                loadAppointments();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
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
        } catch (error) {
            console.error('Error loading appointments:', error);
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
            tr.innerHTML = `
                <td>${app.id}</td>
                <td>${app.patient_name}</td>
                <td>${app.doctor_name || 'N/A'}</td>
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
                    <button class="btn btn-sm btn-info edit-btn" data-id="${app.id}" data-patient_name="${app.patient_name}" data-doctor_name="${app.doctor_name}" data-email="${app.email}" data-mobile="${app.mobile}" data-appointment_date="${app.appointment_date}" data-appointment_time="${app.appointment_time}">Edit</button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="${app.id}">Delete</button>
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
                const data = e.target.dataset;
                populateForm(data);
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
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
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id, status, status_only: true })
            });
            const result = await response.json();
            if (result.status !== 'success') {
                alert('Error updating status: ' + result.message);
                loadAppointments(); // Reload to reset dropdown to correct state
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
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            if (result.status === 'success') {
                loadAppointments();
            } else {
                alert('Error deleting appointment: ' + result.message);
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
        submitBtn.classList.replace('btn-primary', 'btn-success');
        cancelEditBtn.classList.remove('d-none');
    }

    function resetForm() {
        appointmentForm.reset();
        document.getElementById('appointmentId').value = '';
        submitBtn.textContent = 'Book Appointment';
        submitBtn.classList.replace('btn-success', 'btn-primary');
        cancelEditBtn.classList.add('d-none');
    }

    function validateForm(data) {
        if (!data.patient_name || !data.doctor_name || !data.email || !data.mobile || !data.appointment_date || !data.appointment_time) {
            alert('All fields are mandatory.');
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            alert('Please enter a valid email address.');
            return false;
        }
        if (!/^[0-9]{10,15}$/.test(data.mobile)) {
            alert('Mobile number must be between 10 and 15 digits.');
            return false;
        }
        const selectedDate = new Date(data.appointment_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            alert('Appointment date cannot be in the past.');
            return false;
        }

        // Time slot validation (09:00 - 18:00)
        const time = data.appointment_time;
        const hour = parseInt(time.split(':')[0]);
        if (hour < 9 || hour >= 18) {
            alert('Appointments are only available between 09:00 AM and 06:00 PM.');
            return false;
        }

        return true;
    }
});