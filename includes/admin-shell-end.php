        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const body = document.body;
    document.querySelectorAll('[data-admin-sidebar-open]').forEach((button) => {
        button.addEventListener('click', () => body.classList.add('admin-sidebar-open'));
    });
    document.querySelectorAll('[data-admin-sidebar-close]').forEach((button) => {
        button.addEventListener('click', () => body.classList.remove('admin-sidebar-open'));
    });
})();
</script>
</body>
</html>
