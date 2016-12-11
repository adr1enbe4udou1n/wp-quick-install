$(document).ready(function() {
	var locale = JSON.parse($("#client-locale").html());

	$.validator.addMethod(
		"regex",
		function(value, element, regexp) {
			var re = new RegExp(regexp);
			return this.optional(element) || re.test(value);
		}
	);

	var $response  = $('#response');

	$('#submit').click( function() {

		$('form').validate({
			rules: {
				subdomain: {
					regex: /^[a-z][-a-z0-9]*$/
				},
			},
			messages: {
				subdomain: {
					regex: "This field allow only alphanumeric and hyphen characters"
				}
			},
			highlight: function(element) {
				$(element).closest('.form-group').addClass('has-error');
			},
			unhighlight: function(element) {
				$(element).closest('.form-group').removeClass('has-error');
			},
			errorElement: 'span',
			errorClass: 'help-block',
			errorPlacement: function(error, element) {
				if(element.parent('.input-group').length) {
					error.insertAfter(element.parent());
				} else {
					error.insertAfter(element);
				}
			}
		});

		if ( $('form').valid() ) {

			/*--------------------------*/
			/*	We verify the database connexion and if WP already exists
			/*  If there is no errors we install
			/*--------------------------*/

			$.post(window.location.href + '?action=check_before_upload', $('form').serialize(), function(data) {

				errors = false;
				data = $.parseJSON(data);

				if ( data.db == "error etablishing connection" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; Error Establishing a Database Connection.</p>');
				}

				if ( data.wp == "error directory" ) {
					errors = true;
					$('#errors').show().append('<p style="margin-bottom:0px;">&bull; WordPress seems to be Already Installed.</p>');
				}

				if ( ! errors ) {
					$('form').fadeOut( 'fast', function() {

						$('.progress').show();

						// Fire Step
						// We dowload WordPress
						$response.html("<p>" + locale.download + " ...</p>");

						$.post(window.location.href + '?action=download_wp', $('form').serialize(), function() {
							unzip_wp();
						});
					});
				} else {
					// If there is an error
					$('html,body').animate( { scrollTop: $( 'html,body' ).offset().top } , 'slow' );
				}
			});

		}
		return false;
	});

	// Let's unzip WordPress
	function unzip_wp() {
		$response.html("<p>" + locale.decompress + "...</p>" );
		$('.progress-bar').animate({width: "16.5%"});
		$.post(window.location.href + '?action=unzip_wp', $('form').serialize(), function(data) {
			wp_config();
		});
	}

	// Let's create the wp-config.php file
	function wp_config() {
		$response.html("<p>" + locale.config + "...</p>");
		$('.progress-bar').animate({width: "33%"});
		$.post(window.location.href + '?action=wp_config', $('form').serialize(), function(data) {
			install_wp();
		});
	}

	// CDatabase
	function install_wp() {
		$response.html("<p>" + locale.database + "...</p>");
		$('.progress-bar').animate({width: "49.5%"});
		$.post(window.location.href + '?action=install_wp', $('form').serialize(), function(data) {
			install_theme();
		});
	}

	// Theme
	function install_theme() {
		$response.html("<p>" + locale.theme + "...</p>");
		$('.progress-bar').animate({width: "66%"});
		$.post(window.location.href + '?action=install_theme', $('form').serialize(), function(data) {
			install_plugins();
		});
	}

	// Plugin
	function install_plugins() {
		$response.html("<p>" + locale.plugins + "...</p>");
		$('.progress-bar').animate({width: "82.5%"});
		$.post(window.location.href + '?action=install_plugins', $('form').serialize(), function(data) {
			$response.html(data);
			success();
		});
	}

	// Remove the archive
	function success() {
		$response.html("<p>" + locale.success + "</p>");
		$('.progress-bar').animate({width: "100%"});
		$response.hide();
		$('.progress').delay(500).hide();
		$.post(window.location.href + '?action=success',$('form').serialize(), function(data) {
			$('#success').show().append(data);
		});
	}

});
