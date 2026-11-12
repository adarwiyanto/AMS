/* Fit near-one-page documents without shrinking lengthy clinical reports. */
(function () {
  function preparePrint() {
    var paper = document.querySelector('.paper');
    if (!paper || paper.classList.contains('ams-print-ready')) return;
    paper.classList.add('ams-print-ready');
    var isRx = document.body.classList.contains('ams-recipe-print');
    if (isRx) return; // resep memakai ukuran kertas sendiri
    // A4 printable area is ~273mm with 12mm margins; compact only if nearly fitting.
    var pxPerMm = 96 / 25.4;
    var height = paper.getBoundingClientRect().height;
    var max = 273 * pxPerMm;
    if (height > max && height <= max * 1.13) paper.classList.add('ams-print-compact');
  }
  function afterImagesReady() {
    var images = Array.prototype.slice.call(document.images);
    return Promise.all(images.map(function (img) {
      if (img.complete) return Promise.resolve();
      return new Promise(function (resolve) {
        img.addEventListener('load', resolve, {once: true});
        img.addEventListener('error', resolve, {once: true});
      });
    }));
  }
  window.addEventListener('load', function () {
    afterImagesReady().then(function () {
      if (document.fonts && document.fonts.ready) return document.fonts.ready;
    }).then(function () {
      preparePrint();
      window.print();
    });
  });
  window.addEventListener('beforeprint', preparePrint);
})();
