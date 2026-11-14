    </main>
  </div>
  <script>
    document.getElementById('adminTheme').addEventListener('click', function () {
      var root = document.documentElement;
      var theme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', theme);
      try { localStorage.setItem('apx_theme', theme); } catch (e) {}
    });
  </script>
</body>
</html>