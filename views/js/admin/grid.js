/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */
document.addEventListener('DOMContentLoaded', function () {
  const searchButton = document.querySelector('.grid-search-button');
  const resetButton = document.querySelector('.grid-reset-button');

  resetButton.addEventListener('click', function () {
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
});