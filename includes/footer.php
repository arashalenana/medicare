</div> <!-- End of #content -->
    
    <?php
    // Footer Include

    // Load centralized path configuration (safe, idempotent)
    if (!defined('PHARMALYNX_PATHS_LOADED')) {
        require_once dirname(__FILE__) . '/../config/paths.php';
    }

    // Include AI Assistant UI only for roles with chatbot permission (Admin only)
    if (!function_exists('hasPermission')) {
        require_once dirname(__FILE__) . '/../config/rbac.php';
    }
    $chatbot_ui_file = dirname(__FILE__) . '/../ai-assistant/ai-assistant-ui.php';
    if (file_exists($chatbot_ui_file) && hasPermission('chatbot')) {
        include $chatbot_ui_file;
    }

    // Include messaging widget for all logged-in users
    if (isset($_SESSION['user_id'])) {
        $msg_widget = dirname(__FILE__) . '/messaging_widget.php';
        if (file_exists($msg_widget)) include $msg_widget;
    }
    ?>

    <!-- Bootstrap JS and dependencies -->
    <!-- Bootstrap JS loaded via defer in header.php -->
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');

            if (sidebarCollapse && sidebar && content) {
                sidebarCollapse.addEventListener('click', function (e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const isClickInside = sidebar.contains(event.target) || sidebarCollapse.contains(event.target);
                    if (!isClickInside && sidebar.classList.contains('active') && window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                        content.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html>