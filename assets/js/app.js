// AccountPro global JS — page-specific logic lives inline on each screen for now.
document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss alerts after 5s
  document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; }, 5000);
  });
});
