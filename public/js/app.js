(function () {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const hamburger = document.getElementById("hamburgerBtn");

    const open = () => {
        sidebar.classList.add("open");
        overlay.classList.add("open");
    };
    const close = () => {
        sidebar.classList.remove("open");
        overlay.classList.remove("open");
    };

    hamburger.addEventListener("click", open);
    overlay.addEventListener("click", close);
})();
