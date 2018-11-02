<?php
/**
 * Clean whole cache, or regenerate specific product cache by product ID
 * @author Pavol Ďurko
 * @license MIT
 */

header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
header('Cache-Control: post-check=0, pre-check=0', false);
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Pragma: no-cache'); // HTTP/1.0
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

if (!isset($_GET['your-secret']) || !isset($_GET['flush']))
{
	header('HTTP/1.0 404 Not Found');
    exit();	
}

include dirname(__FILE__).'/../../config/config.inc.php';
require __DIR__ . DS . 'xtremecache.php';

if (ctype_digit($_GET['flush']))
    $product_id = (int) $_GET['flush'];
else
    $product_id = -1;

if ($product_id < 0) // flush entire cache
{
	$cache = new PSCache();
	try
	{
		$cache->flush();
		echo "cleaned";
	}
	catch (Exception $e)
	{
		echo "Unable to flush cache: " . $e;
	}
}
else // flush specific product
{
	$manage = new ManageCache();

	$deleted_from_cache = $manage->delete_from_cache_by_product_id($product_id) ? true : false;

	if (isset($_GET['regenerate']) && $deleted_from_cache)
	{
		if($manage->regenerate_last())
			echo 'deleted and regenerated';
		else
			echo 'deleted, but cant regenerate';
	}
	else
		if ($deleted_from_cache)
			echo 'deleted';
		else
			echo 'cant delete';
}




class ManageCache
{
    private $last_deleted_url;
    
    private $product;
    private $category;
    private $link;
    private $_cache;
    private $base_url;
    private $context;
    
    public function __construct()
    {
        $this->_cache = new PSCache();
        $this->last_deleted_url = null;
        $this->link = new Link();
        $this->home_url = substr(Tools::getHttpHost(true).__PS_BASE_URI__,0, -1);
        $this->context = Context::getContext();
    }
    
    public function delete_from_cache_by_product_id($product_id)
    {
        $this->product = new Product($product_id);
        $this->category = new Category((int)$this->product->id_category_default, (int)$this->context->language->id);
        
        $url = $this->get_relative_url(
                $this->link->getProductLink(
                    $this->product, null, $this->category->link_rewrite
                    )
            );

        if (strlen($url) > 0)
        {
            $key = $this->getCacheKey($url);
            if ($this->_cache->delete($key))
            {
                $this->last_deleted_url = $url;
                return true;
            }
            else
            {
                $this->last_deleted_url = null;
                return false;
            }
        }
        return false;
    }
    
    public function regenerate_last()
    {
        if (strlen($this->last_deleted_url) > 0)
        {
            $url = $this->home_url . $this->last_deleted_url;
            return (Tools::file_get_contents($url) === false) ? false : true;
        }
        return false;
    }

    private function get_relative_url($absolute_url)
    {
        return str_replace($this->home_url, '', $absolute_url);
    }

    private function getCacheKey($url)
    {
		$xc = new XtremeCache();
		return $xc->getCacheKey($url);
    }
}
