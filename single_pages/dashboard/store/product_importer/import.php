<?php
defined('C5_EXECUTE') or die("Access Denied.");
$importViews = array('view','import','productsimported','importproducts');
$ps = Core::make('helper/form/page_selector');

use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product as StoreProduct;

if (in_array($controller->getTask(),$importViews)){ ?>

  <?php if ($controller->getTask() == 'view') { ?>

    <h3><?= t("Import Products With CSV")?></h3>
    <br>

    <form method="post" enctype="multipart/form-data" id="form_import" action="<?= $view->action('importproducts')?>">
      <?php if($headers){ ?>
        <hr>
        <h4><?= t("Table Mapping") ?></h4><br>
        <div class="alert alert-info">
            <?= t("Map the columns in your CSV file to the product fields.")?>
        </div>
      <?php foreach($importFields as $area => $vals):?>
        <?php if($area != "Options" && $area != "Detail Page") {?>
        <h4><?= t($area) ?></h4>
        <div class="table-responsive form-group">
          <table class="table table-striped">
            <tbody>
        <?php } ?>
              <?php foreach($vals as $tColumn => $tDesc ): ?>
                <?php if($tColumn == "pVariations" || $tColumn == "selectPageTemplate"){ ?>
                  <input type="hidden" name="column[<?= $tColumn ?>]" value="<?= strlen($tDesc['default'])==0 ? "" : $tDesc['default']  ?>"/>
                <?php  }else{ ?>
                  <tr>
                    <td><label><?= t($tDesc['label']) ?></label><?php echo $tDesc['label']=="In Product Groups" ? " (Separated by comma) " : ""; ?> <em><?= strlen($tDesc['default'])==0 ? "" : "(Default: ".$tDesc['default'].")"   ?></em></td>

                    <td style="width: 200px;">

                      <select class="form-control" name="column[<?= $tColumn ?>]">
                        <option value="" selected>Select Column</option>
                        <?php
                        $ctr = 0;

                          foreach($headers as $head){?>
                            <option value="<?= $ctr++ ?>"><?= $head ?></option>
                          <?php }?>
                      </select>
                    </td>
                  </tr>
                <?php } ?>
              <?php endforeach; ?>
        <?php if($area != "Options" && $area != "Detail Page") {?>
            </tbody>
          </table>
        </div>
        <?php } ?>
      <?php endforeach; ?>
      <div class="form-group">
        <div class="checkbox">
            <label>
              <input name="wipeProducts" type="checkbox" id="wipeProducts">
              <?php echo t('Wipe product list clean before import.'); ?>
            </label>
        </div>
      </div>
      <div class="form-group">
        <input type="button" class="btn btn-primary import-submit-btn btn ccm-input-submit" data-action="<?= $view->action('beginImport')?>" id="importbutton" name="importbutton" value="<?= t('Begin Import');?>"/>
      </div>
      <?php }else{ ?>
      <?php
      	$pk=Package::getByHandle('community_store_product_importer');
      	$ppath=$pk->getRelativePath();
      ?>
      <div class="form-group">
          <div class="ccm-system-errors alert alert-danger alert-dismissable import-error" style="display:none;"><?php echo t('Please upload a valid CSV file!'); ?></div>

          <label class="control-label"><?php echo t('Upload CSV file'); ?></label>
          <div><input type="file" name="csv" id="csv"></div>


      </div>
      <div class="form-group">
        <input type="button" class="btn btn-primary import-submit-btn btn ccm-input-submit" id="upload" name="upload" value="<?= t('Upload');?>"/>
        <span id="loading_img" style="display: none;"><img src="<?php echo $ppath; ?>/images/gif-load.gif" /> <?php echo t('Uploading CSV file...'); ?></span>
      </div>

      <?php } ?>
    </form>
    <script type="text/javascript">
    	$(document).ready(function () {

    		$('#upload').on('click', function() {

    			var action  = $('input[name=action]:checked').val();
    			var file    = $('input[type="file"]').val();
    			var get_ext = file.split('.').reverse()[0].toLowerCase();
          $('#loading_img').show();

    			if(file){

    				if(get_ext != 'csv') {
    					$('.alert-danger').show();
    				}else {
              var data = new FormData($('form')[0]);
    					$('.alert-danger').hide();

    					$.each($('#csv')[0].files, function(i, file) {
    					    data.append('file[]', file);
    					});
    					data.append('auth', '<?php echo $auth; ?>');

    					$.ajax({
    					    url: $('#form_import').attr('action'),
    					    data: data,
    					    cache: false,
    					    contentType: false,
    					    processData: false,
    					    type: 'POST',
    					    success: function(attributes){

    					    	var params = [
    					    		{'name': 'auth', 'value': '<?php echo $auth?>'},
      								{'name': 'attr', 'value': attributes}
      							];
                    window.location.replace("<?= \URL::to('/dashboard/store/product_importer', 'import')?>");


    					    }
    					});
    				}

    			}else {
    				$('.alert-danger').show();
    			}

                return false; //prevent the form for submiting or redirecting
    	    });


          $('#importbutton').on('click', function() {
            if($('select[name="column[pSKU]"]').val()!="" && $('select[name="column[pName]"]').val()!=""  ){
                if($('#wipeProducts').is(':checked')){
                  answer = confirm("Are you sure you want to delete all products before import? Clicking Cancel will proceed with import without cleaning up product list.");
                  if(!answer){
                    $("#wipeProducts").prop('checked', false);
                  }
                }
                var postData = new FormData($('form')[0]);
                postData.append('auth', '<?php echo $auth; ?>');
                if($('#wipeProducts').is(':checked')){
                  postData.append('wipeProducts', 'true');
                }
                $.ajax({
                    url :  $(this).attr('data-action'),
                    type: "POST",
                    data : postData,
                    processData: false,
                    contentType: false,
                    success:function(result){
                      var params = [
                        {'name': 'auth', 'value': '<?php echo $auth?>'},
                        {'name': 'attr', 'value': result}
                      ];
                      ccm_triggerProgressiveOperation(
                        "<?php echo URL::to('/dashboard/store/product_importer/import', 'processQueue');?>",
                        params,
                        '<?php echo t("Importing CSV file, dont close your browser."); ?>',
                        function(successMessage) {
                          $('.ui-dialog-content').dialog('close');
                          alert(successMessage.result);
                          window.location.replace("<?= \URL::to('/dashboard/store/products/')?>");
                        },
                        function() {
                          alert('error');
                        }
                      );

                    }
                });

            }else{
              alert("Product name and SKU required.");
            }

            return false; //prevent the form for submiting or redirecting
      	  });



        });
    </script>

  <?php } ?>
<?php }  ?>
