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
    var $button = $form.find('.ssb-button');
    var data = $form.serializeArray();
    // ensure ajax action
    if (!data.find(function(i){ return i.name === 'action'; })) {
      data.push({name: 'action', value: 'ssb_subscribe'});
    }

    $button.prop('disabled', true);
    setMessage($form, SSB.i18n.working, true);
    $.post(SSB.ajax_url, data)
      .done(function(resp){
        if (resp && resp.success) {
          setMessage($form, resp.data && resp.data.message ? resp.data.message : SSB.i18n.subscribed, true);
          // clear email only
          $form.find('input[type=email]').val('');
        } else {
          var msg = (resp && resp.data && resp.data.message) ? resp.data.message : SSB.i18n.genericError;
          setMessage($form, msg, false);
        }
      })
      .fail(function(xhr){
        var msg = SSB.i18n.genericError;
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        setMessage($form, msg, false);
      })
      .always(function(){
        $button.prop('disabled', false);
      });
  });
})(jQuery);
