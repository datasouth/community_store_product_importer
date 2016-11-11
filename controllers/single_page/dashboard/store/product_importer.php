<?php
namespace Concrete\Package\CommunityStoreProductImporter\Controller\SinglePage\Dashboard\Store;
use \Concrete\Core\Page\Controller\PageController;

defined('C5_EXECUTE') or die("Access Denied.");

class ProductImporter extends PageController {

    public function view() {
       $this->redirect('/dashboard/store/product_importer/import');

    }

}
