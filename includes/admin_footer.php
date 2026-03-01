</main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Page transition: fade in on load
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('page-loaded');
        });

        // Page transition: fade out on navigation
        document.addEventListener('click', function (e) {
            // Find the closest anchor tag
            var target = e.target.closest('a');
            if (!target) return;

            var href = target.getAttribute('href');
            if (!href) return;

            // Skip: external links, anchors, javascript:, mailto:, tel:, target="_blank", form submits
            if (
                href.startsWith('#') ||
                href.startsWith('javascript') ||
                href.startsWith('mailto') ||
                href.startsWith('tel') ||
                target.getAttribute('target') === '_blank' ||
                e.ctrlKey || e.metaKey || e.shiftKey
            ) return;

            e.preventDefault();
            document.body.classList.remove('page-loaded');
            document.body.classList.add('page-leaving');

            setTimeout(function () {
                window.location.href = href;
            }, 350);
        });

        // Handle form submissions with fade-out (optional, for non-AJAX forms)
        document.addEventListener('submit', function (e) {
            var form = e.target;
            // Only fade if not an AJAX form and not prevented
            if (form.getAttribute('data-no-transition') !== null) return;
            document.body.classList.remove('page-loaded');
            document.body.classList.add('page-leaving');
        });
    </script>
</body>
</html>
