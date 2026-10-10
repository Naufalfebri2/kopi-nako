(function () {
    // Panel detail pengajuan, dimuat lewat fetch
    const detailEl = document.getElementById("detailDrawer");
    if (detailEl) {
        const body = document.getElementById("detailBody");
        const detailDrawer = bootstrap.Offcanvas.getOrCreateInstance(detailEl);

        document.querySelectorAll("[data-detail-url]").forEach((row) => {
            row.addEventListener("click", async () => {
                body.innerHTML = '<div class="kn-empty">Memuat…</div>';
                detailDrawer.show();

                try {
                    const res = await fetch(row.dataset.detailUrl, {
                        headers: { "X-Requested-With": "XMLHttpRequest" },
                    });
                    if (!res.ok) throw new Error(res.status);
                    body.innerHTML = await res.text();
                } catch (err) {
                    body.innerHTML =
                        '<div class="kn-empty">Gagal memuat detail pengajuan.</div>';
                }
            });
        });
    }

    // Form Ajukan Baru
    const submitEl = document.getElementById("submitDrawer");
    if (!submitEl) return;

    const submitDrawer = bootstrap.Offcanvas.getOrCreateInstance(submitEl);
    document
        .getElementById("openSubmit")
        .addEventListener("click", () => submitDrawer.show());

    const form = document.getElementById("submitForm");
    if (!form.elements.detail_id) {
        // Tidak ada jadwal mendatang: formulir hanya menampilkan pesan
        if (submitEl.dataset.reopen === "1") submitDrawer.show();
        return;
    }

    const map = JSON.parse(
        document.getElementById("submitMap").textContent || "{}",
    );
    const izinFields = document.getElementById("izinFields");
    const tukarFields = document.getElementById("tukarFields");
    const hint = document.getElementById("tukarHint");
    const reason = form.elements.reason;
    const counter = document.getElementById("submitCount");

    const currentType = () =>
        form.querySelector('input[name="jenis"]:checked').value;

    function refreshType() {
        const type = currentType();
        izinFields.classList.toggle("d-none", type !== "izin");
        tukarFields.classList.toggle("d-none", type !== "tukar");
        form.elements.leave_id.disabled = type !== "izin";
        form.elements.partner_detail_id.disabled = type !== "tukar";
        form.elements.leave_id.required = type === "izin";
        form.elements.partner_detail_id.required = type === "tukar";
    }

    function refreshPartners() {
        const select = form.elements.partner_detail_id;
        const info = map[form.elements.detail_id.value];

        select.innerHTML = '<option value="">Pilih rekan kerja</option>';
        hint.textContent = "";

        if (!info) return;

        if (!info.canSwap) {
            hint.textContent = "Leader pada shift ini tidak bisa tukar shift.";
            return;
        }

        if (info.partners.length === 0) {
            hint.textContent =
                "Tidak ada rekan yang bisa diajak tukar pada tanggal ini.";
            return;
        }

        info.partners.forEach((partner) => {
            const option = document.createElement("option");
            option.value = partner.id;
            option.textContent = partner.label;
            select.appendChild(option);
        });
    }

    form.querySelectorAll('input[name="jenis"]').forEach((radio) =>
        radio.addEventListener("change", refreshType),
    );
    form.elements.detail_id.addEventListener("change", refreshPartners);
    reason.addEventListener("input", () => {
        counter.textContent = reason.value.length + " karakter";
    });

    // Pulihkan isian bila penyimpanan gagal
    const { oldJenis, oldDetail, oldPartner, oldLeave } = form.dataset;

    if (oldJenis) {
        const radio = form.querySelector(
            `input[name="jenis"][value="${oldJenis}"]`,
        );
        if (radio) radio.checked = true;
    }
    if (oldDetail) form.elements.detail_id.value = oldDetail;

    refreshType();
    refreshPartners();

    if (oldPartner) form.elements.partner_detail_id.value = oldPartner;
    if (oldLeave) form.elements.leave_id.value = oldLeave;
    counter.textContent = reason.value.length + " karakter";

    if (submitEl.dataset.reopen === "1") submitDrawer.show();
})();
