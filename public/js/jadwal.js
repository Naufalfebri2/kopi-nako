(function () {
    // Modal tambah shift: tombol/sel dengan data-assign membuka modal, dengan isian awal bila ada
    const assignEl = document.getElementById("assignModal");
    if (assignEl) {
        const assignModal = bootstrap.Modal.getOrCreateInstance(assignEl);
        const assignForm = assignEl.querySelector("form");

        const employeeBoxes = assignEl.querySelectorAll(
            'input[name="employee_ids[]"]',
        );
        const employeeList = document.getElementById("assignEmployee");
        const employeeSearch = document.getElementById("assignEmployeeSearch");
        const employeeAll = document.getElementById("assignEmployeeAll");
        const dateFrom = assignForm.elements.date;
        const dateTo = assignForm.elements.date_to;
        const clientError = document.getElementById("assignClientError");

        // Kosongkan isian dan hapus sisa pencarian, lalu isi sesuai sel yang diklik
        const resetAssignForm = (button) => {
            const { employee, date, shift } = button.dataset;

            employeeBoxes.forEach((box) => {
                box.checked = employee ? box.value === employee : false;
            });
            employeeAll.checked = false;

            // Hapus filter pencarian supaya semua karyawan tampil lagi
            employeeSearch.value = "";
            employeeSearch.dispatchEvent(new Event("input", { bubbles: true }));

            dateFrom.value =
                date || assignForm.dataset.defaultDate || dateFrom.defaultValue;
            dateTo.value = "";
            assignForm.elements.shift_id.value = shift || "";
            assignForm.elements.status_lead.checked = false;

            clientError.classList.add("d-none");
            clientError.textContent = "";

            // Minta skrip di modal menghitung ulang penghitung dan ringkasan
            employeeList.dispatchEvent(new Event("change", { bubbles: true }));
            dateFrom.dispatchEvent(new Event("change", { bubbles: true }));
        };

        document.querySelectorAll("[data-assign]").forEach((button) => {
            button.addEventListener("click", () => {
                resetAssignForm(button);
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
