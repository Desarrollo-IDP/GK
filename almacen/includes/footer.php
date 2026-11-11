    </div> <!-- End Main Content -->

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // User Menu Dropdown
            const userIcon = document.getElementById("user-icon");
            const dropdownMenu = document.getElementById("dropdown-menu");

            function toggleDropdown(event) {
                event.stopPropagation();
                dropdownMenu.classList.toggle("hidden");
            }

            function closeDropdown(event) {
                if (!userIcon.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.add("hidden");
                }
            }

            userIcon.addEventListener("click", toggleDropdown);
            document.addEventListener("click", closeDropdown);

            // Sidebar Toggle for Mobile/Tablet
            const hamburgerBtn = document.getElementById("hamburger-btn");
            const sidebar = document.getElementById("sidebar");
            const sidebarOverlay = document.getElementById("sidebar-overlay");

            function toggleSidebar() {
                sidebar.classList.toggle("active");
                sidebarOverlay.classList.toggle("active");
            }

            function closeSidebar() {
                sidebar.classList.remove("active");
                sidebarOverlay.classList.remove("active");
            }

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener("click", toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener("click", closeSidebar);
            }

            // Cerrar sidebar al hacer clic en un link (en móviles)
            const sidebarLinks = sidebar.querySelectorAll("a");
            sidebarLinks.forEach(link => {
                link.addEventListener("click", function() {
                    if (window.innerWidth <= 1024) {
                        closeSidebar();
                    }
                });
            });

            // Ajustar sidebar en resize
            window.addEventListener("resize", function() {
                if (window.innerWidth > 1024) {
                    sidebar.classList.remove("active");
                    sidebarOverlay.classList.remove("active");
                }
            });
        });
    </script>
</body>
</html>

