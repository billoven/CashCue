/**
 * admin_users.js
 * --------------------------------------------------
 * CashCue – Super Admin Users & API Token Management
 * --------------------------------------------------
 * Responsibilities:
 *   - Load and display users
 *   - Update stats cards
 *   - Toggle user status (active/suspended)
 *   - Delete users
 *   - Select user to manage API tokens
 *   - Load and display API tokens
 *   - Revoke API tokens
 *   - Create new API tokens with name input and copyable key
 *   - Add new user modal (link to existing Add User modal)
 * --------------------------------------------------
 */

let selectedUserId = null;    // Currently selected user for token operations
let selectedUserEmail = '';   // Used to display in modal

/* =====================================================
   INITIAL LOAD
===================================================== */
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    setupAddUserButton();
    setupCreateTokenModal();
});

/* =====================================================
   LOAD USERS
   Fetch users from API and populate table + stats
===================================================== */
function loadUsers() {
    fetch('/cashcue/api/getUsers.php')
        .then(res => res.json())
        .then(json => {
            console.log('Users loaded:', json);

            if (!json || !json.data) return;

            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '';

            json.data.forEach(user => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';

                tr.innerHTML = `
                    <td>${user.id}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${escapeHtml(user.username ?? '-')}</td>
                    <td>
                        ${user.is_super_admin == 1
                            ? '<span class="badge bg-warning text-dark">Yes</span>'
                            : '<span class="badge bg-secondary">No</span>'}
                    </td>
                    <td>
                        ${user.is_active == 1
                            ? '<span class="badge bg-success">Active</span>'
                            : '<span class="badge bg-danger">Suspended</span>'}
                    </td>
                    <td>${user.created_at}</td>
                    <td>
                        <button data-id="${user.id}" 
                                class="btn btn-sm ${user.is_active == 1 ? 'btn-outline-success' : 'btn-outline-secondary'} toggleActiveBtn">
                            ${user.is_active == 1 ? 'Active' : 'Suspended'}
                        </button>

                        <button data-id="${user.id}" 
                                class="btn btn-sm ${user.is_super_admin == 1 ? 'btn-warning' : 'btn-outline-dark'} toggleSuperAdminBtn">
                            ${user.is_super_admin == 1 ? 'SuperAdmin' : 'User'}
                        </button>

                        <button data-id="${user.id}" 
                                class="btn btn-sm btn-outline-danger deleteBtn">
                            Delete
                        </button>
                    </td>
                `;

                // Click row → select user to manage tokens
                tr.addEventListener('click', () => {
                    selectUser(user.id, user.email);
                });

                tbody.appendChild(tr);
            });

            attachUserButtons();
            updateStats(json.data);
        })
        .catch(err => console.error('Users fetch error:', err));
}

/* =====================================================
   UPDATE STAT CARDS
===================================================== */
function updateStats(users) {
    const total = users.length;
    const active = users.filter(u => u.is_active == 1).length;
    const suspended = total - active;
    const superAdmins = users.filter(u => u.is_super_admin == 1).length;

    document.getElementById('statTotalUsers').innerText = total;
    document.getElementById('statActiveUsers').innerText = active;
    document.getElementById('statSuspendedUsers').innerText = suspended;
    document.getElementById('statSuperAdmins').innerText = superAdmins;
}

/* =====================================================
   ATTACH USER ACTION BUTTONS
   - Handles Active toggle, SuperAdmin toggle, Delete
   - Stops propagation to prevent row selection
===================================================== */
function attachUserButtons() {

    // ------------------------
    // Toggle Active Status
    // ------------------------
    document.querySelectorAll('.toggleActiveBtn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();

            const userId = btn.dataset.id;

            fetch('/cashcue/api/toggleUserStatus.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: userId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    loadUsers();
                } else {
                    alert(data.message || 'Error toggling user status');
                }
            })
            .catch(err => console.error('Toggle Active error:', err));
        });
    });

    // ------------------------
    // Toggle SuperAdmin Status
    // ------------------------
    document.querySelectorAll('.toggleSuperAdminBtn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();

            const userId = btn.dataset.id;

            fetch('/cashcue/api/toggleSuperAdmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ user_id: userId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    loadUsers();
                } else {
                    alert(data.message || 'Error toggling SuperAdmin');
                }
            })
            .catch(err => console.error('Toggle SuperAdmin error:', err));
        });
    });

    // ------------------------
    // Delete User
    // ------------------------
    document.querySelectorAll('.deleteBtn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();

            const userId = btn.dataset.id;

            if (!confirm('Are you sure you want to permanently delete this user?')) return;

            fetch('/cashcue/api/deleteUser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: userId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    loadUsers();
                } else {
                    alert(data.message || 'Error deleting user');
                }
            })
            .catch(err => console.error('Delete error:', err));
        });
    });
}

/* =====================================================
   TOGGLE USER STATUS
===================================================== */
function toggleUser(id) {
    fetch('/cashcue/api/toggleUserStatus.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}`
    })
    .then(res => res.json())
    .then(() => loadUsers())
    .catch(err => console.error('Toggle error:', err));
}

/* =====================================================
   DELETE USER
===================================================== */
function deleteUser(id) {
    if (!confirm('Are you sure you want to permanently delete this user?')) return;

    fetch('/cashcue/api/deleteUser.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}`
    })
    .then(res => res.json())
    .then(() => {
        // If the deleted user was selected, reset token area
        if (selectedUserId == id) {
            selectedUserId = null;
            document.getElementById('selectedUserInfo').innerText =
                'Select a user to manage their API tokens';
            document.getElementById('btnAddToken').disabled = true;
            document.querySelector('#tokensTable tbody').innerHTML = '';
        }
        loadUsers();
    })
    .catch(err => console.error('Delete error:', err));
}

/* =====================================================
   SELECT USER
===================================================== */
function selectUser(id, email) {
    selectedUserId = id;
    selectedUserEmail = email;

    document.getElementById('selectedUserInfo').innerHTML =
        `<strong>Managing tokens for:</strong> ${escapeHtml(email)}`;

    document.getElementById('btnAddToken').disabled = false;

    loadTokens();
}

/* =====================================================
   LOAD TOKENS
===================================================== */
function loadTokens() {
    if (!selectedUserId) return;

    fetch(`/cashcue/api/getUserTokens.php?user_id=${selectedUserId}`)
        .then(res => res.json())
        .then(json => {
            if (!json || !json.data) return;

            const tbody = document.querySelector('#tokensTable tbody');
            tbody.innerHTML = '';

            json.data.forEach(token => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(token.name)}</td>
                    <td>${token.expires_at ?? '-'}</td>
                    <td>${token.last_used_at ?? '-'}</td>
                    <td>
                        ${token.is_revoked == 1
                            ? '<span class="badge bg-danger">Revoked</span>'
                            : '<span class="badge bg-success">Active</span>'}
                    </td>
                    <td>
                        ${token.is_revoked == 0
                            ? `<button data-id="${token.id}" class="btn btn-sm btn-outline-danger revokeBtn">Revoke</button>`
                            : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });

            attachTokenButtons();
        })
        .catch(err => console.error('Token fetch error:', err));
}

/* =====================================================
   ATTACH TOKEN REVOKE BUTTONS
===================================================== */
function attachTokenButtons() {
    document.querySelectorAll('.revokeBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            revokeToken(btn.dataset.id);
        });
    });
}

/* =====================================================
   REVOKE TOKEN
===================================================== */
function revokeToken(id) {
    if (!confirm('Are you sure you want to revoke this API token?')) return;

    fetch('/cashcue/api/createUserToken.php', { // <-- API endpoint pour revoke
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}&revoke=1`
    })
    .then(res => res.json())
    .then(() => loadTokens())
    .catch(err => console.error('Revoke error:', err));
}

/* =====================================================
   SETUP ADD USER BUTTON
===================================================== */
function setupAddUserButton() {
    const btnAdd = document.getElementById('btnAddUser');
    if (!btnAdd) return;

    btnAdd.addEventListener('click', () => {
        const modalEl = document.getElementById('addUserModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        const form = modalEl.querySelector('form');
        form.reset();

        modalEl.querySelector('.modal-title').textContent = '➕ Add User';
        modal.show();
    });
}

/* =====================================================
   SETUP CREATE TOKEN MODAL
===================================================== */
function setupCreateTokenModal() {
    const btnCreateToken = document.getElementById('btnAddToken');
    if (!btnCreateToken) return;

    const modalEl = document.getElementById('createTokenModal');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const tokenNameInput = document.getElementById('newTokenName');
    const tokenValueInput = document.getElementById('newTokenValue');
    const btnConfirm = document.getElementById('btnConfirmCreateToken');

    btnCreateToken.addEventListener('click', () => {
        if (!selectedUserId) return;
        tokenNameInput.value = '';
        tokenValueInput.value = '';
        modal.show();
    });

    btnConfirm.addEventListener('click', () => {
        const name = tokenNameInput.value.trim();
        if (!name) {
            alert('Token name is required.');
            return;
        }

        fetch('/cashcue/api/createUserToken.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${encodeURIComponent(selectedUserId)}&name=${encodeURIComponent(name)}`
        })
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') {
                tokenValueInput.value = json.token;
                loadTokens();  // Refresh tokens table
            } else {
                alert(json.message || 'Error creating token.');
            }
        })
        .catch(err => {
            console.error('Create token error:', err);
            alert('Error creating token.');
        });
    });
}

/* =====================================================
   SETUP ADD USER MODAL
   - Handles opening the modal
   - Handles form submission
   - Supports Super Admin checkbox
   - Shows error messages without closing modal
===================================================== */
/* =====================================================
   ADD USER – FINAL CLEAN VERSION
===================================================== */
function setupAddUserModal() {

    const btnAddUser = document.getElementById('btnAddUser');
    const modalEl = document.getElementById('addUserModal');
    const form = document.getElementById('addUserForm');
    const btnConfirm = document.getElementById('btnConfirmAddUser');
    const alertDiv = document.getElementById('addUserAlert');

    if (!btnAddUser || !modalEl || !form || !btnConfirm) {
        console.warn("AddUser modal elements missing");
        return;
    }

    const modal = new bootstrap.Modal(modalEl);

    /* ---------- OPEN MODAL ---------- */
    btnAddUser.addEventListener('click', () => {
        form.reset();
        document.getElementById('newIsActive').checked = true; // default active
        alertDiv.classList.add('d-none');
        modal.show();
    });

    /* ---------- SAVE USER ---------- */
    btnConfirm.addEventListener('click', () => {

        const username = document.getElementById('newUsername').value.trim();
        const email = document.getElementById('newEmail').value.trim();
        const password = document.getElementById('newPassword').value.trim();
        const isSuperAdmin = document.getElementById('newSuperAdmin').checked ? 1 : 0;
        const isActive = document.getElementById('newIsActive').checked ? 1 : 0;

        if (!username || !email || !password) {
            alertDiv.textContent = "All fields are required.";
            alertDiv.classList.remove('d-none');
            return;
        }

        fetch('/cashcue/api/createUser.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                username: username,
                email: email,
                password: password,
                newSuperAdmin: isSuperAdmin,
                is_active: isActive
            })
        })
        .then(res => res.json())
        .then(json => {

            if (json.status === 'success') {

                modal.hide();

                // Remove leftover backdrop (prevents blur freeze)
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');

                loadUsers();

            } else {

                alertDiv.textContent = json.message || "Error creating user.";
                alertDiv.classList.remove('d-none');
            }
        })
        .catch(err => {
            console.error(err);
            alertDiv.textContent = "Server error.";
            alertDiv.classList.remove('d-none');
        });
    });
}

/* Call on DOM ready */
document.addEventListener('DOMContentLoaded', () => {
    setupAddUserModal();
});

/* =====================================================
   SIMPLE HTML ESCAPER
===================================================== */
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[m]));
}