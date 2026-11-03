function updateTotal(input) {
    const total = document.getElementById('total');
    if (total) {
        total.textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', maximumFractionDigits: 0
        }).format(Number(input.dataset.price));
    }
}
document.querySelectorAll('input[name="product_id"]').forEach((input) => {
    input.addEventListener('change', () => updateTotal(input));
    if (input.checked) updateTotal(input);
});

document.querySelectorAll('[data-print-invoice]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

const countdown = document.querySelector('[data-payment-countdown]');
if (countdown) {
    const deadline = Date.parse(countdown.dataset.paymentCountdown);
    if (Number.isFinite(deadline)) {
        const tick = () => {
            const remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
            const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
            const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            countdown.textContent = remaining ? `${h}:${m}:${s}` : 'Waktu pembayaran berakhir';
        };
        tick();
        const timer = setInterval(() => {
            tick();
            if (Date.now() >= deadline) clearInterval(timer);
        }, 1000);
    }
}

const paymentPage = document.querySelector('[data-payment-poll]');
if (paymentPage) {
    let attempts = 0;
    let checking = false;
    const poll = setInterval(async () => {
        if (++attempts > 80) { clearInterval(poll); return; }
        if (document.hidden || checking) return;
        checking = true;
        try {
            const url = new URL(paymentPage.dataset.paymentPoll, location.origin);
            if (url.origin !== location.origin) { clearInterval(poll); return; }
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if ([401, 403, 404, 419, 429].includes(response.status)) { clearInterval(poll); return; }
            if (!response.ok) return;
            const data = await response.json();
            if (data.status !== paymentPage.dataset.paymentStatus || data.gateway_state !== paymentPage.dataset.gatewayState) {
                clearInterval(poll);
                location.reload();
            }
        } catch (_) {
            // Kegagalan jaringan tidak pernah dianggap sebagai pembayaran berhasil.
        } finally { checking = false; }
    }, 15000);
}
