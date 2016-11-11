<?php defined('C5_EXECUTE') or die("Access Denied."); ?>
<div class="danger-empty ccm-system-errors alert alert-danger alert-dismissable" style="display:none;"><?php echo t('Please select fields to export!'); ?></div>
<h3><?= t("Export Products to CSV")?></h3>
<br/>
<p>
  <h4><?php echo t('Fields to Export:'); ?></h4>
  <a href="javascript:" id="check-all">[<?php echo t('check all'); ?>]</a>
  <a href="javascript:" id="uncheck-all">[<?php echo t('uncheck all'); ?>]</a>
</p>
<form method="post" action="<?=$view->action('export_csv')?>" id="export_form">
<div class="well">
  <?php foreach($exportFields as $area => $vals){ ?>
    <h4><?= t($area) ?></h4>
    <ul style="list-style:none">
      <?php foreach($vals as $tColumn => $tDesc ){ ?>
        <li>
          <div class="checkbox">
            <label>
              <input type="checkbox" class="exportField" value="1" name="exportField[<?= $area?>][<?= $tColumn?>]"><?php echo t($tDesc); ?>
            </label>
          </div>
        </li>
      <?php } ?>
    </ul>
  <?php } ?>
</div>
<div class="ccm-dashboard-form-actions-wrapper">
  <div class="ccm-dashboard-form-actions">
      <div class="btn-toolbar pull-right">
          <button type="submit" id="export_button" class="btn btn-primary"><?php echo t('Export to CSV'); ?></button>
      </div>
  </div>
</div>
</form>
<script type="text/javascript">
$(document).ready(function() {
  $("#export_form").submit(function(e) {
    if($("#export_form").serialize()!=""){
      $('.danger-empty').hide();
			$.ajax({
			    url: $('#export_form').attr('action'),
			    data: $("#export_form").serialize(),
			    type: 'POST',
			    success: function(fields){

            var params = [
			    		{'name': 'auth', 'value': '<?php echo $auth?>'},
						  {'name': 'exportFields', 'value': fields}
					  ];
            ccm_triggerProgressiveOperation(
              "<?php echo URL::to('/dashboard/store/product_importer/export', 'processQueue');?>",
              params,
              '<?php echo t("Exporting products, dont close your browser."); ?>',
              function(successMessage) {
                $('.ui-dialog-content').dialog('close');

							window.location.replace("<?php echo URL::to('/dashboard/store/product_importer/export/output_csv'); ?>");
              },
              function() {
                alert('error');
              }
            );

				  }
			});
    }else{
      $('.danger-empty').show();
      $('html').scrollTop(0);
    }
			e.preventDefault(); // avoid to execute the actual submit of the form.
		});
  $('#check-all').click(function() {
    $('.exportField').prop('checked', 'checked');
  });

  $('#uncheck-all').click(function() {
    $('.exportField').removeAttr('checked');
  });

  $('#check-all').click(function() {
    $('.exportField').prop('checked', 'checked');
  });

  $('#uncheck-all').click(function() {
    $('.exportField').removeAttr('checked');
  });
});
</script>
