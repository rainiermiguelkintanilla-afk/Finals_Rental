const API_BASE = window.location.origin;

const storage = {
    getToken: () => localStorage.getItem('jwt'),
    setToken: (t) => localStorage.setItem('jwt', t),
    getUser: () => {
        try {
            const raw = localStorage.getItem('customerUser');
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    },
    setUser: (user) => {
        if (user) {
            localStorage.setItem('customerUser', JSON.stringify(user));
        } else {
            localStorage.removeItem('customerUser');
        }
    },
    clear: () => {
        localStorage.removeItem('jwt');
        localStorage.removeItem('customerUser');
    },
};

function displayNameFromUser(user) {
    if (!user) {
        return 'Customer';
    }
    const display = (user.displayName || user.fullName || '').trim();
    if (display !== '') {
        return display;
    }
    return user.email || 'Customer';
}

function updateCustomerBar(user) {
    const bar = document.getElementById('customer-bar');
    const nameEl = document.getElementById('customer-display-name');
    const emailEl = document.getElementById('customer-display-email');
    if (!bar || !nameEl || !emailEl) {
        return;
    }

    if (!user) {
        bar.hidden = true;
        return;
    }

    nameEl.textContent = displayNameFromUser(user);
    emailEl.textContent = user.email || '';
    bar.hidden = false;
}

function saveSession(token, user) {
    storage.setToken(token);
    storage.setUser(user);
    updateCustomerBar(user);
}

function clearSession() {
    storage.clear();
    updateCustomerBar(null);
    mainScreen.hidden = true;
    authScreen.hidden = false;
}

async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {}),
    };
    const token = storage.getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
    const body = await res.json().catch(() => ({}));

    if (res.status === 401 || (res.status === 403 && storage.getToken())) {
        clearSession();
        showError(authError, body.message || 'Session expired. Please sign in again.');
        throw new Error(body.message || 'Session expired.');
    }

    if (!res.ok) {
        const msg = body.message || `Request failed (${res.status})`;
        throw new Error(msg);
    }

    return body;
}

function showToast(message) {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.hidden = false;
    setTimeout(() => { el.hidden = true; }, 3000);
}

function showError(el, message) {
    el.textContent = message;
    el.hidden = !message;
}

// Auth
let registerMode = false;
const authScreen = document.getElementById('auth-screen');
const mainScreen = document.getElementById('main-screen');
const authForm = document.getElementById('auth-form');
const authError = document.getElementById('auth-error');
const authTitle = document.getElementById('auth-title');
const authSubmit = document.getElementById('auth-submit');
const toggleAuth = document.getElementById('toggle-auth-mode');
const fullnameField = document.getElementById('fullname-field');
const phoneField = document.getElementById('phone-field');

toggleAuth.addEventListener('click', () => {
    registerMode = !registerMode;
    authTitle.textContent = registerMode ? 'Create account' : 'Sign in';
    authSubmit.textContent = registerMode ? 'Register' : 'Sign in';
    toggleAuth.textContent = registerMode ? 'Already have an account? Sign in' : 'Create an account';
    fullnameField.hidden = !registerMode;
    phoneField.hidden = !registerMode;
    showError(authError, '');
});

authForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    showError(authError, '');
    const fd = new FormData(authForm);

    try {
        if (registerMode) {
            await api('/api/register', {
                method: 'POST',
                body: JSON.stringify({
                    email: fd.get('email'),
                    password: fd.get('password'),
                    fullName: fd.get('fullName') || '',
                    phone: fd.get('phone') || '',
                    accountType: 'customer',
                }),
            });
            showToast('Account created. Signing you in…');
        }

        const login = await api('/api/login', {
            method: 'POST',
            body: JSON.stringify({
                email: fd.get('email'),
                password: fd.get('password'),
            }),
        });

        const user = login.data.user;
        if (!user || !Array.isArray(user.roles) || !user.roles.includes('ROLE_CUSTOMER')) {
            clearSession();
            throw new Error('This app is for customer accounts only.');
        }

        saveSession(login.data.token, user);
        await showApp();
        showToast(`Welcome, ${displayNameFromUser(user)}!`);
    } catch (err) {
        showError(authError, err.message);
    }
});

document.getElementById('logout-btn').addEventListener('click', () => {
    clearSession();
    showToast('Signed out.');
});

// Tabs
const tabs = document.querySelectorAll('.tab');
const panels = {
    apartments: document.getElementById('tab-apartments'),
    bookings: document.getElementById('tab-bookings'),
    payments: document.getElementById('tab-payments'),
    profile: document.getElementById('tab-profile'),
};

tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        tabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        Object.values(panels).forEach((p) => { p.hidden = true; });
        panels[tab.dataset.tab].hidden = false;
        loadTab(tab.dataset.tab);
    });
});

async function refreshCurrentCustomer() {
    const res = await api('/api/customer/profile');
    const user = res.data.user;
    storage.setUser(user);
    updateCustomerBar(user);
    return { user, tenant: res.data.tenant };
}

async function loadApartments() {
    const list = document.getElementById('apartments-list');
    list.innerHTML = '<p class="empty">Loading…</p>';
    try {
        const res = await api('/api/customer/apartments');
        const items = res.data.items || [];
        if (!items.length) {
            list.innerHTML = '<p class="empty">No apartments available.</p>';
            return;
        }
        list.innerHTML = items.map((a) => `
            <article class="list-item">
                <h3>${escapeHtml(a.name)}</h3>
                <p class="meta">${escapeHtml(a.address)} · ${a.bedrooms} bed · ${a.bathrooms} bath</p>
                <p class="price">${formatMoney(a.rentAmount)}/mo</p>
                <button type="button" class="btn btn-primary book-btn" data-id="${a.id}" data-name="${escapeHtml(a.name)}">Book</button>
            </article>
        `).join('');

        list.querySelectorAll('.book-btn').forEach((btn) => {
            btn.addEventListener('click', () => openBookingForm(btn.dataset.id, btn.dataset.name));
        });
    } catch (err) {
        list.innerHTML = `<p class="empty">${escapeHtml(err.message)}</p>`;
    }
}

function openBookingForm(id, name) {
    document.querySelector('.tab[data-tab="bookings"]').click();
    document.getElementById('booking-form-wrap').hidden = false;
    document.getElementById('booking-apartment-id').value = id;
    document.getElementById('booking-apartment-name').textContent = `Apartment: ${name}`;
}

document.getElementById('cancel-booking').addEventListener('click', () => {
    document.getElementById('booking-form-wrap').hidden = true;
});

document.getElementById('booking-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        await api('/api/customer/bookings', {
            method: 'POST',
            body: JSON.stringify({
                apartmentId: Number(fd.get('apartmentId') || document.getElementById('booking-apartment-id').value),
                checkInDate: fd.get('checkInDate'),
                checkOutDate: fd.get('checkOutDate'),
                guests: Number(fd.get('guests')),
            }),
        });
        showToast('Booking submitted — visible in staff dashboard.');
        document.getElementById('booking-form-wrap').hidden = true;
        e.target.reset();
        loadBookings();
    } catch (err) {
        showToast(err.message);
    }
});

async function loadBookings() {
    const list = document.getElementById('bookings-list');
    list.innerHTML = '<p class="empty">Loading…</p>';
    try {
        const res = await api('/api/customer/bookings');
        const items = res.data.items || [];
        if (!items.length) {
            list.innerHTML = '<p class="empty">No bookings yet.</p>';
            return;
        }
        list.innerHTML = items.map((b) => `
            <article class="list-item">
                <h3>${escapeHtml(b.apartment)}</h3>
                <p class="meta">${b.checkInDate} → ${b.checkOutDate} · ${b.guests} guests</p>
                <span class="badge">${escapeHtml(b.status)}</span>
            </article>
        `).join('');
    } catch (err) {
        list.innerHTML = `<p class="empty">${escapeHtml(err.message)}</p>`;
    }
}

let paymongoEnabled = false;

function formatMoney(amount) {
    const value = Number(amount);
    return `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function startPayMongoCheckout(paymentId, button) {
    if (button) {
        button.disabled = true;
        button.textContent = 'Opening PayMongo…';
    }
    try {
        const res = await api(`/api/customer/payments/${paymentId}/checkout`, { method: 'POST' });
        const url = res.data?.checkoutUrl;
        if (!url) {
            throw new Error('No checkout URL returned.');
        }
        window.location.href = url;
    } catch (err) {
        showToast(err.message);
        if (button) {
            button.disabled = false;
            button.textContent = 'Pay with PayMongo';
        }
    }
}

async function syncPendingPaymongoPayments(items) {
    const pending = (items || []).filter(
        (p) => p.paymongoLinkId && p.status !== 'paid',
    );
    await Promise.all(
        pending.map((p) => api(`/api/customer/payments/${p.id}/sync`, { method: 'POST' }).catch(() => null)),
    );
}

async function handlePaymentReturn() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('payment') !== 'success') {
        return;
    }

    window.history.replaceState({}, '', `${window.location.pathname}`);
    document.querySelector('.tab[data-tab="payments"]')?.click();
    showToast('Checking payment status…');

    try {
        const res = await api('/api/customer/payments');
        await syncPendingPaymongoPayments(res.data?.items || []);
        await loadPayments();
        showToast('Payment list updated.');
    } catch (err) {
        showToast(err.message);
    }
}

async function loadPayments() {
    const list = document.getElementById('payments-list');
    list.innerHTML = '<p class="empty">Loading…</p>';
    try {
        const res = await api('/api/customer/payments');
        const items = res.data.items || [];
        paymongoEnabled = Boolean(res.data.paymongo?.enabled);

        if (!items.length) {
            list.innerHTML = '<p class="empty">No payments on file.</p>';
            return;
        }

        const intro = paymongoEnabled
            ? '<p class="payments-hint">Pay rent online via PayMongo (GCash, card, Maya, GrabPay).</p>'
            : '';

        list.innerHTML = intro + items.map((p) => {
            const payButton = p.canPayOnline
                ? `<button type="button" class="btn btn-paymongo pay-btn" data-id="${p.id}">Pay with PayMongo</button>`
                : '';
            const method = p.paymentMethod === 'paymongo' ? ' · PayMongo' : '';
            return `
            <article class="list-item">
                <h3>${formatMoney(p.amount)}</h3>
                <p class="meta">${escapeHtml(p.apartment || '')} · Due ${p.dueDate || '—'}${method}</p>
                <span class="badge badge-${escapeHtml(p.status)}">${escapeHtml(p.status)}</span>
                ${payButton}
            </article>
        `;
        }).join('');

        list.querySelectorAll('.pay-btn').forEach((btn) => {
            btn.addEventListener('click', () => startPayMongoCheckout(Number(btn.dataset.id), btn));
        });
    } catch (err) {
        list.innerHTML = `<p class="empty">${escapeHtml(err.message)}</p>`;
    }
}

async function loadProfile() {
    const el = document.getElementById('profile-content');
    try {
        const { user, tenant } = await refreshCurrentCustomer();
        const tenantName = tenant
            ? `${tenant.firstName || ''} ${tenant.lastName || ''}`.trim()
            : '';
        el.innerHTML = `
            <h3>Your account</h3>
            <p><strong>${escapeHtml(displayNameFromUser(user))}</strong></p>
            <p class="meta">${escapeHtml(user.email)}</p>
            <p class="meta">Account type: Customer</p>
            ${tenant ? `
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0">
                <p class="meta">Tenant: ${escapeHtml(tenantName)}</p>
                <p class="meta">Phone: ${escapeHtml(tenant.phone || '—')}</p>
                <p class="meta">Lease unit: ${escapeHtml(tenant.currentApartment || '—')}</p>
            ` : '<p class="meta">No tenant profile linked yet.</p>'}
        `;
    } catch (err) {
        el.innerHTML = `<p class="empty">${escapeHtml(err.message)}</p>`;
    }
}

function loadTab(name) {
    if (name === 'apartments') loadApartments();
    if (name === 'bookings') loadBookings();
    if (name === 'payments') loadPayments();
    if (name === 'profile') loadProfile();
}

async function showApp() {
    authScreen.hidden = true;
    mainScreen.hidden = false;

    const cached = storage.getUser();
    updateCustomerBar(cached);

    await refreshCurrentCustomer();
    loadApartments();
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

(async function bootstrap() {
    if (!storage.getToken()) {
        return;
    }

    updateCustomerBar(storage.getUser());

    try {
        await showApp();
        await handlePaymentReturn();
        startRealtimePolling();
    } catch {
        clearSession();
    }
})();

/** In-app alerts when backend publishes booking/payment events (browser/PWA). APK uses Expo push. */
function startRealtimePolling() {
    let since = 0;
    let bootstrapped = false;

    async function poll() {
        if (!storage.getToken()) {
            return;
        }
        try {
            const res = await api(`/api/customer/realtime/events?since=${since}`);
            const events = res.data?.events || [];
            if (!bootstrapped) {
                events.forEach((e) => { since = Math.max(since, e.id || 0); });
                bootstrapped = true;
                return;
            }
            for (const event of events) {
                since = Math.max(since, event.id || 0);
                const label = (event.type || 'update').replace(/\./g, ' ');
                showToast(`🔔 ${label}`);
                if (event.type?.startsWith('booking.')) {
                    loadBookings();
                }
                if (event.type?.startsWith('payment.')) {
                    loadPayments();
                }
            }
        } catch {
            /* offline */
        }
    }

    setInterval(poll, 4000);
    poll();
}
