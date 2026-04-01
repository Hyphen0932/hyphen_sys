/* ========================= Custom Breadcrumb Steps ========================= */

(function () {
  var steps = document.querySelectorAll('.breadcrumb-steps__item');

  [].forEach.call(steps, function (item, index, array) {
    item.onclick = function () {
      for (var i = 0, l = array.length; i < l; i++) {
        if (index >= i) {
          array[i].classList.add('breadcrumb-steps__item--active');
        } else {
          array[i].classList.remove('breadcrumb-steps__item--active');
        }
      }
    };
  });
})();
