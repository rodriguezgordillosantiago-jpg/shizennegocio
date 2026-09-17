(function () {
  'use strict';

  document.querySelectorAll('[data-auto-dismiss]').forEach(function (alert) {
    window.setTimeout(function () {
      alert.remove();
    }, 5000);
  });
}());
