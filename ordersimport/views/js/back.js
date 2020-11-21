$(document).ready(function(){
	$('#import_form').on('submit',function(){
		$('.moduleconfig-content').html('<img src="/modules/ordersimport/images/loader.gif" id="loader_img"/>');
		$('#start_import').attr('disabled',true);
		$('#start_import').html('In progress...',true);
		$('.moduleconfig-content').show();

		let url = $(this).attr('action');

		formdata = new FormData();
		formdata.append('file',$('input[type=file]').prop('files')[0])
		console.log(formdata);
		$.ajax({
			url: url,
			//data: $('#import_form').serialize(),
			data: formdata,
			processData: false,
			contentType: false,
			type: 'POST',
			success: (msg) => {
				$('.moduleconfig-content').html('<h2>Success</h2>'+msg);
				$('#start_import').html('Start import',true);
				$('#start_import').attr('disabled',false);


			}
		});
		return false;

	})
	});
