(function($){
  function setMessage($form, text, ok){
    var $msg = $form.find('.ssb-message');
    $msg.removeClass('ssb-error ssb-success');
    if (ok) { $msg.addClass('ssb-success'); } else { $msg.addClass('ssb-error'); }
    $msg.text(text || '');
  }

  $(document).on('submit', '.ssb-form', function(e){
    e.preventDefault();
    var $form = $(this);
    var data = $form.serializeArray();
    // ensure ajax action
    if (!data.find(function(i){ return i.name === 'action'; })) {
      data.push({name: 'action', value: 'ssb_subscribe'});
    }

    setMessage($form, 'Working...', true);
    $.post(SSB.ajax_url, data)
      .done(function(resp){
        if (resp && resp.success) {
          setMessage($form, resp.data && resp.data.message ? resp.data.message : 'Subscribed!', true);
          // clear email only
          $form.find('input[type=email]').val('');
        } else {
          var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Error. Please try again.';
          setMessage($form, msg, false);
        }
      })
      .fail(function(xhr){
        var msg = 'Error. Please try again.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        setMessage($form, msg, false);
      });
  });
})(jQuery);
