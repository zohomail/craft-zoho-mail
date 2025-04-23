
$( document ).ready(function() {
  $('[purpose=copyredirecturi]').click(function(e) {
    var copyText = document.getElementById('zmail_redirection_url');
        copyText.select();
        copyText.setSelectionRange(0, copyText.value.length);
        document.execCommand('copy');
  });
    $("#zohomail_authorize_btn").on("click",function(){
        $self = $(this);
        $.ajax({
            url: Craft.getUrl('admin/zohomail/configureoauth'),
            type: 'POST',
            dataType: 'json',
            data: {
              'domain' : $('[name=zohomail_domain]').val(),
              'client_id' : $('#zohomail_client_id').val(),
              'client_secret' : $("#zohomail_client_secret").val(),
              'CRAFT_CSRF_TOKEN' : $("[name=CRAFT_CSRF_TOKEN]").val()
            },
            beforeSend: function() {
							if($self.find(".loading-spinner").length === 0){
								$self.append($('<div>').addClass("loading-spinner"));
							}else {
								
								return;
							}
            },
            success: function(data) {
              $self.find(".loading-spinner").remove();
              if(data.result === 'success'){
                
                newWindow = window.open(data.authorize_url,'Zoho Mail','width=400,height=400');
                window.addEventListener('message', function(event) {
                  $result = event.data;
                  if($result.result === 'success'){
                    window.location.reload();
                  } else{
                    addZeptoErrorMessage('Invalid client secret');
                    window.removeEventListener('message',function(event){});
                  }
                  
                });
                window.scrollTo(0,0);
              }
              else {
                if(data.hasOwnProperty('error_message')){
                  addZeptoErrorMessage(data['error_message']);
                  window.scrollTo(0,0);
                }
                else if(data.hasOwnProperty('email_error')){
                  $email_error = data.email_error;
                  $.each($email_error,function(index,item){
                    $error_data = item['error']['data'];
                    $error_msg = '';
                    console.log($error_data['moreInfo']);
                    if($error_data['moreInfo']) {
                      $error_msg = $error_data['moreInfo'];
                    }
                    else if($error_data['errorCode']) {
                      $error_msg = $error_data['errorCode'];
                    }
                    $label = $('<label>').attr("id",item['type']+"-error").addClass("zohomail_error").attr("for",item['type']).html($error_msg);
                    $('#'+item['type']).addClass('zohomail_error');
                    $label.insertAfter($('[name='+item['type']+']'));
                    
                  });
                  
                }
              }
              
            }
          });

    });

    
    $("#zohomail_test_btn").on("click",function(){
      $self = $(this);
      $.ajax({
          url: Craft.getUrl('admin/zohomail/testmail'),
          type: 'POST',
          dataType: 'json',
          data: {
            'CRAFT_CSRF_TOKEN' : $("[name=CRAFT_CSRF_TOKEN]").val()
          },
          beforeSend: function() {
            if($self.find(".loading-spinner").length === 0){
              $self.append($('<div>').addClass("loading-spinner"));
            }else {
              
              return;
            }
          },
          success: function(data) {
            $self.find(".loading-spinner").remove();
            if(data.result === 'success'){
              addZeptoSuccessMessage('Plugin configured successfully');
              $('#zohomail_test_btn').removeClass('zmail-dispNone');
              $('#zohomail_mailconfig_btn').addClass('zmail-dispNone');
              $("#zmail_from_address").attr("disabled","disabled");
              $("#zmail_from_name").attr("disabled","disabled");
            }
            else {
              addZeptoErrorMessage(data.message);
            }
            
          }
        });

  });

  $("#zohomail_mailconfig_btn").on("click",function(){
    $self = $(this);
    $.ajax({
        url: Craft.getUrl('admin/zohomail/saveEmail'),
        type: 'POST',
        dataType: 'json',
        data: {
          'from_name' : $('#zmail_from_name').val(),
          'from_address' : $('#zmail_from_address').val(),
          'CRAFT_CSRF_TOKEN' : $("[name=CRAFT_CSRF_TOKEN]").val()
        },
        beforeSend: function() {
          if($self.find(".loading-spinner").length === 0){
            $self.append($('<div>').addClass("loading-spinner"));
          }else {
            
            return;
          }
        },
        success: function(data) {
          $self.find(".loading-spinner").remove();
          if(data.result === 'success'){
            addZeptoSuccessMessage('Plugin configured successfully');
            $('#zohomail_test_btn').removeClass('zmail-dispNone');
            $('#zohomail_mailconfig_btn').addClass('zmail-dispNone');
            $("#zmail_from_address").attr("disabled","disabled");
            $("#zmail_from_name").attr("disabled","disabled");
          }
          else {
            addZeptoErrorMessage(data.message);
          }
          
        }
      });

});
  $('[purpose="configure-accordion"]').click(function(e){
    var $self = $(this);
    
    var $activeForm = $self.parent().siblings(".accordion-body");
    if(!$self.closest('[purpose=accordion-box]').hasClass('zmail-accordion-disabled')){
      $.each($activeForm,function(index,obj){
        if($(obj).attr("is_configured") === 1){
          $(obj).find('[purpose=configure-accordion]').addClass("zmailaccordion__trigger--configured");
        }
      });
      
      $self.removeClass("zmailaccordion__trigger--collapsed  zmailaccordion__trigger--configured").addClass("zmailaccordion__trigger--expanded");
      $self.parent().siblings(".accordion-body").removeClass("zmail-accordion-active").addClass("zmail-accordion-inactive");
      $self.parent(".accordion-body").removeClass("zmail-accordion-inactive").addClass("zmail-accordion-active");
    }
    
  });
  $("[purpose=zmail_client_help]").click(function(){
		$domain = $("[name=zohomail_domain]");
		$url = 'https://www.zoho.com/accounts/protocol/oauth-setup.html';
		window.open($url, '_blank').focus();
	});
	$("[purpose=zmail_generate_client]").click(function(){
		$domain = $("[name=zohomail_domain]").val();
		$url = 'https://api-console.'+ $domain + '/add#web';
		window.open($url, '_blank').focus();
	});
  $('[purpose=reauthorize]').click(function(e) {
    e.preventDefault();
    $("[name=zohomail_domain]").removeAttr("disabled");
    $("#zohomail_client_id").removeAttr("disabled");
    $("#zohomail_client_secret").removeAttr("disabled");
    $("#zohomail_authorize_btn").removeClass("zmail-dispNone");
    $(this).addClass("zmail-dispNone");
  });
  $('[purpose=reconfigure]').click(function(e) {
    e.preventDefault();
    $("#zmail_from_address").removeAttr("disabled");
    $("#zmail_from_name").removeAttr("disabled");
    $('#zmail_modify_config').addClass('zmail-dispNone');
    $('#zohomail_test_btn').addClass('zmail-dispNone');
    $('#zohomail_mailconfig_btn').removeClass('zmail-dispNone');
  });
  $("[purpose=zmail_client_help]").click(function(){
		$domain = $("[name=zohomail_domain]");
		$url = 'https://www.zoho.com/accounts/protocol/oauth-setup.html';
		window.open($url, '_blank').focus();
	});
	$("[purpose=zmail_generate_client]").click(function(){
		$domain = $("[name=zohomail_domain]").val();
		$url = 'https://api-console.'+ $domain + '/add#web';
		window.open($url, '_blank').focus();
	});
});
  