(function () {
    const drawerEl = document.getElementById("detailDrawer");
    const body = document.getElementById("detailBody");
    const drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

    document.querySelectorAll("[data-detail-url]").forEach((row) => {
        row.addEventListener("click", async () => {
            body.innerHTML = '<div class="kn-empty">Memuat…</div>';
            drawer.show();

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
})();
