<?php

namespace Concrete\Package\CommunityStoreProductImporter\Controller\SinglePage\Dashboard\Store;

use \Concrete\Core\Page\Controller\DashboardPageController;
use Core;
use View;
use FilePermissions;
use TaskPermission;
use File;
use PageType;
use GroupList;
use Request;
use Job;

use \Symfony\Component\HttpFoundation\Session\Session as SymfonySession;

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
class Import extends DashboardPageController
{

    // IMPORT PAGE
    public function view()
    {
        $product = new StoreProduct();
        $this->set('importFields', $this->getImportFields());
        $this->requireAsset('css', 'communityStoreDashboard');
        $this->requireAsset('javascript', 'communityStoreFunctions');
        // clear the environment overrides cache first
        $env = \Environment::get();
        $env->clearOverrideCache();
        $this->set('auth', Job::generateAuth());
        $session = new SymfonySession();
        $preview = $session->get('csv_rows');
        $headers = $session->get('csv_headers');
        if($headers) {
            $this->set('headers',$headers);
            $this->set('rows',$preview);

            $session->remove('csv_headers');
            $session->remove('csv_rows');
        }
    }

    public function loadFormAssets()
    {
        $this->requireAsset('core/file-manager');
        $this->requireAsset('core/sitemap');
        $this->requireAsset('css', 'select2');
        $this->requireAsset('javascript', 'select2');

        $this->set('fp',FilePermissions::getGlobal());
        $this->set('tp', new TaskPermission());
        $this->set('al', Core::make('helper/concrete/asset_library'));

        $this->requireAsset('css', 'communityStoreDashboard');
        $this->requireAsset('javascript', 'communityStoreFunctions');

        $attrList = StoreProductKey::getAttributeKeyValueList();
        $this->set('attribs',$attrList);

        $pageType = PageType::getByHandle("store_product");
        $pageTemplates = $pageType->getPageTypePageTemplateObjects();
        $templates = array();
        foreach($pageTemplates as $pt){
            $templates[$pt->getPageTemplateID()] = $pt->getPageTemplateName();
        }
        $this->set('pageTemplates',$templates);
        $taxClasses = array();
        foreach(StoreTaxClass::getTaxClasses() as $taxClass){
            $taxClasses[$taxClass->getID()] = $taxClass->getTaxClassName();
        }
        $this->set('taxClasses',$taxClasses);
    }

    public function validate($args)
    {
        $e = Core::make('helper/validation/error');

        if($args['pName']==""){
            $e->add(t('Please enter a Product Name'));
        }
        if(strlen($args['pName']) > 255){
            $e->add(t('The Product Name can not be greater than 255 Characters'));
        }
        if(!is_numeric($args['pPrice'])){
            $e->add(t('The Price must be set, and numeric'));
        }
        if(!is_numeric($args['pQty']) && !$args['pQtyUnlim']){
            $e->add(t('The Quantity must be set, and numeric'));
        }
        if(!is_numeric($args['pWidth'])){
            $e->add(t('The Product Width must be a number'));
        }
        if(!is_numeric($args['pHeight'])){
            $e->add(t('The Product Height must be a number'));
        }
        if(!is_numeric($args['pLength'])){
            $e->add(t('The Product Length must be a number'));
        }
        if(!is_numeric($args['pWeight'])){
            $e->add(t('The Product Weight must be a number'));
        }

        return $e;

    }
    public function importproducts(){
      ProductImporter::importCsv();
    }

    public function processQueue(){
      ProductImporter::processQueue();
    }
    public function beginImport(){
      $post = \Request::post();
      if($post['wipeProducts']){
        //get all products and delete them
        $productIds = $this->getAllProductIDs();
        foreach ($productIds as $pID) {
          $product = StoreProduct::getByID($pID);
          if ($product) {
              $product->remove();
          }
        }
      }
      echo serialize($post);
      exit;
    }

    public function getImportFields(){
      $attributes =  StoreProductKey::getList();
      $attrList = array();
      foreach($attributes as $attr){
        $attrList[$attr->getAttributeKeyHandle()] = array(
          "default" => "",
          "label" => $attr->getAttributeKeyName()
        );
      }
      $list = array(
        "Overview" => array(
          "pName" => array(
            "default" => "",
            "label" => "Product Name"
          ),
          "pSKU" => array(
            "default" => "",
            "label" => "Code / SKU"
          ),
          "pActive" => array(
            "default" => 0,
            "label" => "Active"
          ),
          "pFeatured" => array(
            "default" => 0,
            "label" => "Featured Product"
          ),
          "pPrice" => array(
            "default" => 0,
            "label" => "Price"
          ),
          "pSalePrice" => array(
            "default" => "",
            "label" => "Sale Price"
          ),
          "pTaxable" => array(
            "default" => 1,
            "label" => "Taxable"
          ),
          "pTaxClass" => array(
            "default" => 1,
            "label" => "Tax Class"
          ),
          "pQty" => array(
            "default" => 0,
            "label" => "Stock Level"
          ),
          "pQtyUnlim" => array(
            "default" => 0,
            "label" => "Is Unlimited"
          ),
          "pNoQty" => array(
            "default" => 0,
            "label" => "Offer quantity selection"
          ),
          "pBackOrder" => array(
            "default" => "",
            "label" => "Allow Back Orders"
          ),
          "pDesc" => array(
            "default" => "",
            "label" => "Short Description"
          ),
          "pDetail" => array(
            "default" => "",
            "label" => "Product Details"
          )
        ),
        "Shipping" => array(
          "pShippable" => array(
            "default" => 1,
            "label" => "Product is Shippable"
          ),
          "pWeight" => array(
            "default" => 0,
            "label" => "Weight"
          ),
          "pNumberItems" => array(
            "default" => "",
            "label" => "Number Of Items"
          ),
          "pLength" => array(
            "default" => 0,
            "label" => "Length"
          ),
          "pWidth" => array(
            "default" => 0,
            "label" => "Width"
          ),
          "pHeight" => array(
            "default" => 0,
            "label" => "Height"
          )
        ),
        "Categories" => array(
          "pProductGroups" => array(
            "default" => "",
            "label" => "In Product Groups"
          )
        ),
        "Images" => array(
          "pfID" => array(
            "default" => 0,
            "label" => "Primary Product Image"
          ),
          "url_upload_1" => array(
            "default" => 0,
            "label" => "Additional Product Image 1"
          ),
          "url_upload_2" => array(
            "default" => 0,
            "label" => "Additional Product Image 2"
          ),
          "url_upload_3" => array(
            "default" => 0,
            "label" => "Additional Product Image 3"
          ),
          "url_upload_4" => array(
            "default" => 0,
            "label" => "Additional Product Image 4"
          ),
          "url_upload_5" => array(
            "default" => 0,
            "label" => "Additional Product Image 5"
          )
        ),
        "Options" => array(
          "pVariations" => array(
            "default" => 0,
            "label" => "Options have different prices, SKUs or stock levels"
          )
        ),
        "Attributes" => $attrList,
        "Memberships and Downloads" => array(
          "pAutoCheckout" => array(
            "default" => "",
            "label" => " Send customer directly to checkout when added to cart"
          ),
          "pExclusive" => array(
            "default" => "",
            "label" => "Prevent this item from being in the cart with other items"
          )
        ),
        "Detail Page" => array(
          "selectPageTemplate" => array(
            "default" => 5,
            "label" => "Page Template"
          )
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
