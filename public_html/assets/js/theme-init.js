(function () {
    try {
        var savedTheme = localStorage.getItem('theme');
        var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var isDark = savedTheme === 'dark' || (savedTheme !== 'light' && systemDark);

        document.documentElement.classList.toggle('dark', isDark);
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

        var isCollapsed = localStorage.getItem('promerp_sidebar_collapsed') === '1';
        if (isCollapsed) {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
    } catch (e) {}
}());
