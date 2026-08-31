// Applies the saved/system dark-mode preference before the app mounts, so
// there's no flash of the wrong theme. Kept as its own static file (rather
// than an inline <script> in index.html) so the CSP's script-src can drop
// 'unsafe-inline' — a same-origin external file needs no CSP exception at
// all, unlike an inline script block.
(function () {
  var userMode = localStorage.getItem('coreui-free-vue-admin-template-theme')
  var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
  if (userMode === 'dark' || (userMode !== 'light' && systemDark)) {
    document.documentElement.dataset.coreuiTheme = 'dark'
  }
})()
