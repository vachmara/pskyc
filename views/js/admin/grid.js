/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */


document.addEventListener('DOMContentLoaded', function () {
  const searchButton = document.querySelector('.grid-search-button');
  const resetButton = document.querySelector('.grid-reset-button');
  const collapseIcon = document.querySelector('.collapse-icon');
  const cronCopy = document.getElementById('cronCopy');

  resetButton?.addEventListener('click', function () {
    const url = this.getAttribute('data-url');
    const redirect = this.getAttribute('data-redirect');
    fetch(url, { method: 'POST' }).then(response => {
      if (!response.ok)
        return;
      window.location.href = redirect;
    }).catch(error => {
      console.error('Error resetting grid:', error);
    });
  });

  cronCopy?.addEventListener('click', function (event) {
    const cronUrlInput = document.getElementById('cronUrl');
    cronUrlInput.select();
    cronUrlInput.setSelectionRange(0, 99999); // For mobile devices

    try {
      navigator.clipboard.writeText(cronUrlInput.value);

      // Show success feedback
      const button = event.target.closest('button');
      const originalHtml = button.innerHTML;
      button.classList.remove('btn-outline-primary');
      button.classList.add('btn-success');

      setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-primary');
      }, 2000);
    } catch (err) {
      console.error('Failed to copy cron URL:', err);

      // Fallback: show error feedback
      const button = event.target.closest('button');
      const originalHtml = button.innerHTML;
      button.classList.remove('btn-outline-primary');
      button.classList.add('btn-danger');

      setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-danger');
        button.classList.add('btn-outline-primary');
      }, 2000);
    }
  });


});