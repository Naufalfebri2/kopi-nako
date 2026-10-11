(function () {
    "use strict";

    var modalEl = document.getElementById("knTambahKaryawan");
    if (!modalEl) return;

    var form = document.getElementById("knEmpForm");
    var panes = modalEl.querySelectorAll("[data-kn-step]");
    var stepItems = modalEl.querySelectorAll("[data-kn-step-item]");
    var bar = modalEl.querySelector(".kn-wiz-bar");
    var barFill = bar.querySelector(".progress-bar");
    var stepText = document.getElementById("knWizStepText");
    var stepName = document.getElementById("knWizStepName");
    var btnPrev = document.getElementById("knWizPrev");
    var btnNext = document.getElementById("knWizNext");
    var btnSubmit = document.getElementById("knWizSubmit");

    var TOTAL = 3;
    var NAMES = {
        1: "Data Diri & Kontak",
        2: "Data Kepegawaian",
        3: "Konfirmasi",
    };
    var STEP_FIELDS = {
        1: ["name", "email", "no_phone"],
        2: ["nik", "job_id", "id_role", "password", "password_confirmation"],
    };
    var current = parseInt(modalEl.dataset.startStep, 10) || 1;

    function el(name) {
        return form.elements[name];
    }

    function normalizePhone(v) {
        return v.replace(/[\s\-()]+/g, "");
    }

    var RULES = {
        name: function (v) {
            return v.trim() ? "" : "Nama lengkap wajib diisi.";
        },
        email: function (v) {
            v = v.trim();
            if (!v) return "Email wajib diisi.";
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
                ? ""
                : "Format email tidak valid.";
        },
        no_phone: function (v) {
            var n = normalizePhone(v);
            if (!n) return "Nomor telepon wajib diisi.";
            return /^(\+62|62|0)8\d{8,12}$/.test(n)
                ? ""
                : "Nomor telepon tidak valid. Contoh: 081234567890.";
        },
        nik: function (v) {
            return v.trim() ? "" : "NIK wajib diisi.";
        },
        job_id: function (v) {
            return v ? "" : "Jabatan wajib dipilih.";
        },
        id_role: function (v) {
            return v ? "" : "Role akses wajib dipilih.";
        },
        password: function (v) {
            return v.length >= 8 ? "" : "Kata sandi minimal 8 karakter.";
        },
        password_confirmation: function (v) {
            return v === el("password").value
                ? ""
                : "Konfirmasi kata sandi tidak cocok.";
        },
    };

    function setError(name, msg) {
        var input = el(name);
        var box = document.getElementById("kn-err-" + name);
        input.classList.toggle("is-invalid", !!msg);
        input.setAttribute("aria-invalid", msg ? "true" : "false");
        if (box) box.textContent = msg || "";
    }

    function validateStep(step) {
        var firstInvalid = null;
        (STEP_FIELDS[step] || []).forEach(function (name) {
            var msg = RULES[name](el(name).value);
            setError(name, msg);
            if (msg && !firstInvalid) firstInvalid = el(name);
        });
        if (firstInvalid) firstInvalid.focus();
        return !firstInvalid;
    }

    function fillSummary() {
        var text = {
            name: el("name").value.trim(),
            email: el("email").value.trim().toLowerCase(),
            no_phone: normalizePhone(el("no_phone").value),
            nik: el("nik").value.trim(),
            job_id: el("job_id").selectedOptions[0]
                ? el("job_id").selectedOptions[0].text
                : "",
            id_role: el("id_role").selectedOptions[0]
                ? el("id_role").selectedOptions[0].text
                : "",
            password: "••••••••",
        };
        Object.keys(text).forEach(function (k) {
            modalEl.querySelector('[data-kn-sum="' + k + '"]').textContent =
                text[k];
        });
    }

    function showStep(n, moveFocus) {
        current = n;
        panes.forEach(function (p) {
            p.classList.toggle("d-none", parseInt(p.dataset.knStep, 10) !== n);
        });
        stepItems.forEach(function (li) {
            var i = parseInt(li.dataset.knStepItem, 10);
            li.classList.toggle("is-active", i === n);
            li.classList.toggle("is-done", i < n);
            if (i === n) li.setAttribute("aria-current", "step");
            else li.removeAttribute("aria-current");
        });
        var pct = Math.round((n / TOTAL) * 100);
        barFill.style.width = pct + "%";
        bar.setAttribute("aria-valuenow", String(pct));
        stepText.textContent = "Langkah " + n + " dari " + TOTAL;
        stepName.textContent = NAMES[n];
        btnPrev.classList.toggle("invisible", n === 1);
        btnNext.classList.toggle("d-none", n === TOTAL);
        btnSubmit.classList.toggle("d-none", n !== TOTAL);
        if (n === TOTAL) fillSummary();

        if (moveFocus) {
            var pane = modalEl.querySelector('[data-kn-step="' + n + '"]');
            var target = pane.querySelector("input:not([type=hidden]), select");
            (target || stepName).focus();
        }
    }

    btnNext.addEventListener("click", function () {
        if (current < TOTAL && validateStep(current))
            showStep(current + 1, true);
    });
    btnPrev.addEventListener("click", function () {
        if (current > 1) showStep(current - 1, true);
    });

    // Enter di input pindah langkah, bukan langsung kirim.
    form.addEventListener("keydown", function (e) {
        if (
            e.key === "Enter" &&
            e.target.tagName === "INPUT" &&
            current < TOTAL
        ) {
            e.preventDefault();
            btnNext.click();
        }
    });

    form.addEventListener("input", function (e) {
        if (e.target.name && RULES[e.target.name]) setError(e.target.name, "");
    });

    form.addEventListener("submit", function (e) {
        if (current < TOTAL || !validateStep(1) || !validateStep(2)) {
            e.preventDefault();
            return;
        }
        btnSubmit.disabled = true;
        btnSubmit.textContent = "Menyimpan...";
    });

    modalEl.querySelectorAll("[data-kn-toggle]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var input = document.getElementById(btn.dataset.knToggle);
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.textContent = show ? "Sembunyi" : "Lihat";
            btn.setAttribute("aria-pressed", show ? "true" : "false");
        });
    });

    modalEl.addEventListener("shown.bs.modal", function () {
        showStep(current, true);
    });

    showStep(current, false);

    if (modalEl.dataset.reopen === "1" && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
})();
