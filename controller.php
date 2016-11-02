<?php

namespace Concrete\Package\CommunityStoreProductImporter;

use Package;
use Route;
use Whoops\Exception\ErrorException;
use \Concrete\Core\Page\Single as SinglePage;

defined('C5_EXECUTE') or die(_("Access Denied."));

class Controller extends Package
{
    protected $pkgHandle = 'community_store_product_importer';
    protected $appVersionRequired = '5.7.2';
    protected $pkgVersion = '0.9.1';

    public function getPackageDescription()
    {
        return t("Product Importer for Community Store");
    }

    public function getPackageName()
    {
        return t("Product Importer");
    }

    public function install()
    {
        $installed = Package::getInstalledHandles();
        if(!(is_array($installed) && in_array('community_store',$installed)) ) {
            throw new ErrorException(t('This package requires that Community Store be installed'));
        } else {
            $pkg = parent::install();
            $p = SinglePage::add('/dashboard/store/import', $pkg);
            if (is_object($p)) {
                $p->update(array('cName' => t('Product Importer'), 'cDescription' => 'Product Importer Plugin'));
            }
        }

    }


    public function on_start() {
        // Route::register('/checkout/paypalresponse','\Concrete\Package\CommunityStorePaypalStandard\Src\CommunityStore\Payment\Methods\CommunityStorePaypalStandard\CommunityStorePaypalStandardPaymentMethod::validateCompletion');
    }
}
?>
