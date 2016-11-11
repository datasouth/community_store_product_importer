<?php
namespace Concrete\Package\CommunityStoreProductImporter\Controller\SinglePage\Dashboard\Store\ProductImporter;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Page;
use Core;
use PageList;
use Loader;
use Config;
use Queue;
use Response;
use stdClass;
use Job;

use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\Product as StoreProduct;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductFile as StoreProductFile;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductGroup as StoreProductGroup;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductImage as StoreProductImage;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductList as StoreProductList;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductLocation as StoreProductLocation;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductUserGroup as StoreProductUserGroup;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductVariation\ProductVariation as StoreProductVariation;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Product\ProductOption\ProductOption as StoreProductOption;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Group\Group as StoreGroup;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Group\GroupList as StoreGroupList;
use \Concrete\Package\CommunityStore\Src\Attribute\Key\StoreProductKey;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Tax\TaxClass as StoreTaxClass;
use \Concrete\Package\CommunityStoreProductImporter\Src\CommunityStore\Utilities\ProductImporter;

defined('C5_EXECUTE') or die('Access Denied.');

class Export extends DashboardPageController {

	  public function on_start() {
        parent::on_start();
        // clear the environment overrides cache first
        $env = \Environment::get();
        $env->clearOverrideCache();
        $this->set('auth', Job::generateAuth());
    }

    public function view($error=false) {
      $this->set('exportFields', $this->getExportFields());

    }

    public function output_csv() {
			ob_clean();
	    header('Pragma: public');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	    header('Cache-Control: private', false);
	    header('Content-Type: text/csv');
	    header('Content-Disposition: attachment;filename=' . 'products.csv');
			$fp = fopen('php://output', 'w');
			$queue = Queue::get('export_products');
			while($queue->count()>0){
				$messages = $queue->receive();
				if($messages){
					foreach($messages as $message){
						$row = unserialize($message->body);
						fputcsv($fp, $row);
						$queue->deleteMessage($message);
					}
				}

			}
			fclose($fp);
			ob_flush();

      exit;
    }

    public function export_csv() {
    	if(\Request::isPost()) {
           $data  = \Request::post();
					//  var_dump($data['exportField']);
					//  exit;
           if(empty($data) || empty($data['exportField'])) {
             $this->redirect('/dashboard/store/product_importer/export/error');
               exit;
           }else {
             $exportFields = array();
             foreach($data['exportField'] as $area => $value){
							 foreach($value as $column => $val){
								 if($val ==1){
	                 $exportFields[$area][] = $column;
	               }
							 }

             }
              $this->sendToQueue($exportFields);
           }

        }
    }

    protected function sendToQueue($exportFields = '') {

        if(!empty($exportFields)) {
            $queue = Queue::get('product_ids');

            $productIDs = $this->getAllProductIDs();
            foreach($productIDs as $pID){
              $queue->send(serialize($pID));
            }
						$headers = array();
						$base = $this->getExportFields();
						foreach($exportFields as $area =>$fields){
							foreach($fields as $field){
								$headers[] = $base[$area][$field];
							}
						}
						$queue = Queue::get('export_products');
						$queue->send(serialize($headers));
            echo serialize($exportFields);
        }
        exit;

    }

		public function processQueue() {

      if (!ini_get('safe_mode')) {
            @set_time_limit(0);
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        $q = Queue::get('product_ids');

        if(\Request::isPost()) {

            $post = \Request::post();
            $obj  = new stdClass;
            $obj->error = false;

            if (Job::authenticateRequest($post['auth'])) {

                    if($post['process']) {

                        try {

                            $exportFields = unserialize($post['exportFields']);
                            $messages = $q->receive(1);
                            foreach ($messages as $message) {  //loop through the queue
															$assoc = array();

															$pID  = unserialize($message->body);
															$product = StoreProduct::getByID($pID);
															foreach($exportFields as $area => $fields) {
																foreach($fields as $field){
																	if($area == "Categories"){
																		if($field == "pProductGroups"){
																			$groups = $product->getGroups();
																			$groupNames = array();
																			foreach ($groups as $pgroup) {
																				$group = $pgroup->getGroup();
																				$groupNames[] = $group->getGroupName();
																			}
																			$assoc[] = implode(", ",$groupNames);
																		}
																	}else if($area == "Attributes"){
																		$ak = StoreProductKey::getByHandle($field);
																		if(is_object($ak)){
																			$akID = $ak->getAttributeKeyID();
																			$av = $product->getAttributeValueByID($akID);
																			if(is_object($av)){
																				$assoc[] = $av->getValue();
																			}
																		}


																	}else{
																		switch ($field) {
																			case 'pName':
																				$assoc[] = $product->getName();
																				break;
																			case 'pSKU':
																				$assoc[] = $product->getSKU();
																				break;
																			case 'pActive':
																				$assoc[] = $product->isActive() ? $product->isActive() : '0';
																				break;
																			case 'pFeatured':
																				$assoc[] = $product->isFeatured() ? $product->isFeatured() : '0';
																				break;
																			case 'pPrice':
																				$assoc[] = $product->getPrice();
																				break;
																			case 'pSalePrice':
																				$assoc[] = $product->getSalePrice();
																				break;
																			case 'pTaxable':
																				$assoc[] = $product->isTaxable() ? $product->isTaxable() : '0';
																				break;
																			case 'pTaxClass':
																				$assoc[] = $product->getTaxClassID();
																				break;
																			case 'pQty':
																				$assoc[] = $product->getQty();
																				break;
																			case 'pQtyUnlim':
																				$assoc[] = $product->isUnlimited() ? $product->isUnlimited() : '0';
																				break;
																			case 'pNoQty':
																				$assoc[] = $product->allowQuantity() ? $product->allowQuantity() : '0';
																				break;
																			case 'pBackOrder':
																				$assoc[] = $product->allowBackOrders() ? $product->allowBackOrders() : '0';
																				break;
																			case 'pDesc':
																				$assoc[] = $this->filterNewLines($product->getDesc());
																				break;
																			case 'pDetail':
																				$assoc[] = $this->filterNewLines($product->getDetail());
																				break;
																			case 'pShippable':
																				$assoc[] = $product->isShippable() ? $product->isShippable() : '0';
																				break;
																			case 'pWeight':
																				$assoc[] = $product->getWeight();
																				break;
																			case 'pNumberItems':
																				$assoc[] = $product->getNumberItems();
																				break;
																			case 'pLength':
																				$assoc[] = $product->getDimensions('l');
																				break;
																			case 'pWidth':
																				$assoc[] = $product->getDimensions('w');
																				break;
																			case 'pHeight':
																				$assoc[] = $product->getDimensions('h');
																				break;
																			case 'pAutoCheckout':
																				$assoc[] = $product->autoCheckout() ? $product->autoCheckout() : '0';
																				break;
																			case 'pExclusive':
																				$assoc[] = $product->isExclusive() ? $product->isExclusive() : '0';
																				break;
																			default:
																				break;
																		}

																	}
																}


															}
															$queue = Queue::get('export_products');
															$queue->send(serialize($assoc));

                              $q->deleteMessage($message);

                            }

                            $totalItems = $q->count();
                            $obj->totalItems = $totalItems;
                            $obj->message = 'Success';
                            if ($q->count() == 0) {
                                $result = 'Success';
                                $obj->result = $result;
                                $obj->error = false;
                                $obj->totalItems = $totalItems;

                            }

                        } catch (\Exception $e) {
                                $obj->error = true;
                                $obj->message = print_r($e); // needed for progressive library.
                        }

                        $response->setStatusCode(Response::HTTP_OK);
                        $response->setContent(json_encode($obj));
                        $response->send();
                        \Core::shutdown();

                    }else {
                        $totalItems = $q->count();
                        \View::element('progress_bar', array(
                            'totalItems' => $totalItems,
                            'totalItemsSummary' => t2("%d item", "%d items", $totalItems)
                        ));
                         \Core::shutdown();

                    }
            }else {
                $obj->error = t('Access Denied');
                $response->setStatusCode(Response::HTTP_FORBIDDEN);
                $response->setContent(json_encode($obj));
                $response->send();
                \Core::shutdown();
            }

       }

    }


    protected function filterNewLines($val='') {
        if(!empty($val) && is_string($val)) {
            $val = nl2br($val);
            $val = str_replace('<br />', '[br]', $val);
            $val = trim(preg_replace('/\s+/', ' ', $val));
        }
        return $val;
    }


    public function getExportFields(){
      $attributes =  StoreProductKey::getList();
      $attrList = array();
      foreach($attributes as $attr){
        $attrList[$attr->getAttributeKeyHandle()] = $attr->getAttributeKeyName();
      }
      $list = array(
        "Overview" => array(
          "pName" => "Product Name",
          "pSKU" =>  "Code / SKU",
          "pActive" => "Active",
          "pFeatured" => "Featured Product",
          "pPrice" => "Price",
          "pSalePrice" => "Sale Price",
          "pTaxable" => "Taxable",
          "pTaxClass" => "Tax Class",
          "pQty" => "Stock Level",
          "pQtyUnlim" => "Is Unlimited",
          "pNoQty" => "Offer quantity selection",
          "pBackOrder" => "Allow Back Orders",
          "pDesc" => "Short Description",
          "pDetail" => "Product Details"
        ),
        "Shipping" => array(
          "pShippable" => "Product is Shippable",
          "pWeight" => "Weight",
          "pNumberItems" => "Number Of Items",
          "pLength" => "Length",
          "pWidth" => "Width",
          "pHeight" => "Height"
        ),
        "Categories" => array(
          "pProductGroups" => "Product Groups"
        ),
        "Attributes" => $attrList,
        "Memberships and Downloads" => array(
          "pAutoCheckout" => "Send customer directly to checkout when added to cart",
          "pExclusive" => "Prevent this item from being in the cart with other items"
        )
      );
      return $list;
    }


    public function getAllProductIDs(){
      $db = \Database::connection();
      $results = $db->getCol("SELECT pID FROM CommunityStoreProducts");
      return $results;
    }

}
