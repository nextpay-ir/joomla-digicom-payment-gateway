<?php


defined('_JEXEC') or die;

?>

<div class="digicom-payment-form col-lg-12">

    <div class="form-actions text-center">
    <a href="#" onclick="Digicom.submitForm('#showFormSubmitModal', event);window.location.replace('<?php echo $url; ?>');" class="btn btn-success"> پرداخت </a>
    </div>

	<?php
	$layoutData = array(

		'selector' => 'showFormSubmitModal',
		'params'   => array(
			'title'		=> JText::_('در حال پردازش ...'),
			'height' 	=> 'auto',
			'width'	 	=> 'auto',
			'closeButton'	=> false
			),

		'body'     => '<div class="container-fluid center">
			<h3>'.JText::_('لطفا صبر کنید. در حال اتصال به بانک ...') . '</h3>
				<div class="progress">
					<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100" style="width: 100%;"></div>
				</div>
		</div>'
	);
	echo JLayoutHelper::render('bt3.modal.main', $layoutData);
	?>
</div>
