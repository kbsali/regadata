var $modal = $('#ajax-modal');
$('.jsonformat').on('click', function() {
  // create the backdrop and wait for next modal to be triggered
  $('body').modalmanager('loading');
  setTimeout(function() {
     $modal.load('/doc/json-format', '', function() {
      $modal.modal();
    });
  }, 1000);
});