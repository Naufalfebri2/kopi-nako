(function () {
    // Modal tambah shift: tombol/sel dengan data-assign membuka modal, dengan isian awal bila ada
    const assignEl = document.getElementById("assignModal");
    if (assignEl) {
        const assignModal = bootstrap.Modal.getOrCreateInstance(assignEl);
        const assignForm = assignEl.querySelector("form");

        document.querySelectorAll("[data-assign]").forEach((button) => {
            button.addEventListener("click", () => {
                assignForm.reset();

                const { employee, date, shift } = button.dataset;
                if (employee) assignForm.elements.employee_id.value = employee;
                if (date) assignForm.elements.date.value = date;
                if (shift) assignForm.elements.shift_id.value = shift;

                assignModal.show();
            });
        });

        // Buka kembali modal bila penyimpanan gagal, supaya pesan kesalahan terlihat
        if (assignEl.dataset.reopen === "1") {
            assignModal.show();
        }
    }

    // Modal ubah shift: sel yang berisi shift membuka modal dengan data sel itu
    const editEl = document.getElementById("editModal");
    if (editEl) {
        const editModal = bootstrap.Modal.getOrCreateInstance(editEl);
        const editForm = document.getElementById("editForm");
        const deleteForm = document.getElementById("editDeleteForm");

        document.querySelectorAll("[data-edit]").forEach((button) => {
            button.addEventListener("click", () => {
                const data = button.dataset;

                editForm.action = data.updateUrl;
                deleteForm.action = data.destroyUrl;
                document.getElementById("editEmployee").textContent =
                    data.employee;
                document.getElementById("editDate").textContent =
                    data.dateLabel;
                editForm.elements.shift_id.value = data.shift;

                // Hanya karyawan berrole Leader/Head yang bisa ditandai Leader
                const lead = editForm.elements.status_lead;
                lead.checked = data.lead === "1";
                lead.disabled = data.canLead !== "1";

                editModal.show();
            });
        });
    }
})();
