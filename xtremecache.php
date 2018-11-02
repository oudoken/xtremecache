<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Serve cached pages with no request processing
 * @author Pavol Ďurko
 * @based on Salerno Simone
 * @version 1.0.8
 * @license MIT
 */

require __DIR__.DS.'config.php';
require __DIR__.DS.'classes'.DS.'PSCache.php';

class XtremeCache extends Module {

    private $cacheKey;
    private $ps_cache;
    private $_activeCache;
    
    public function __construct()
    {
        $this->name = 'xtremecache';
        $this->tab = 'front_office_features';
        $this->version = '1.0.8';
        $this->author = 'Pavol Ďurko';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Xtreme cache');
        $this->description = $this->l('Cache all front office pages.');
    }
    
    /**
     * Handle non-explicitly handled hooks
     * @param string $name hook name
     * @param array $arguments
     */
    public function __call($name, $arguments)
    {        
        if (0 === strpos(strtolower($name), 'hookaction')) {
            $this->_clearCache();
        }
    }

    /**
     * Install and register hooks
     * @return bool
     */
    public function install()
    {        
        return parent::install() && 
                $this->registerHook('actionDispatcher') &&
                $this->registerHook('actionRequestComplete') &&
                $this->registerHook('actionCategoryAdd') &&
                $this->registerHook('actionCategoryUpdate') &&
                $this->registerHook('actionCategoryDelete') &&
                $this->registerHook('actionProductAdd') &&
                $this->registerHook('actionProductUpdate') &&
                $this->registerHook('actionProductDelete') &&
                $this->registerHook('actionProductSave');
    }
    
    /**
     * Uninstall and clear cache
     * @return bool
     */
    public function uninstall()
    {
        //delete all cached files
        $this->_clearCache(null, null, null, true);
        
        return $this->unregisterHook('actionDispatcher') &&
                $this->unregisterHook('actionRequestComplete') &&
                parent::uninstall();
    }
    
    /**
     * Check if page exists in cache
     * If it exists, serve and abort
     * @param array $params
     */
    public function hookActionDispatcher(&$params)
    {
        if (!$this->isActive()){
          return;
        }
        
        //if not in the checkout process, probably not necessary, checkout cant continue without products in cart (isActive)
        if ($params['controller_class'] !== 'OrderController' && 
            $params['controller_class'] !== 'OrderOpcController'){
            
            $cached = $this->getFromCache();
            if ($cached !== false){
              exit($cached);
            }
        }
    }
    
    /**
     * Cache page content for front pages
     * @param string $params
     */
    public function hookActionRequestComplete(&$params)
    {
    
        if (!$this->isActive()){
          return;
        }
        
        if (!is_subclass_of($this->context->controller, 'OrderController') &&
            !is_subclass_of($this->context->controller, 'OrderOpcController') &&
            !is_subclass_of($this->context->controller, 'PageNotFoundController') // do not cache 404
            && !$this->isMaintenance() // comment this line to cache pages during maintenance too
            ){
                //Logger::addLog('999 - TRY TO SAVE ', 1);
                if ($this->saveToCache($params['output'])){
                }else{
                    //echo 'Unable to write cache.'; // inform about cache issues, misconfigurations..
                } // we can do stats
            }
    }
    
    private function getFromCache()
    {
        //Logger::addLog('999 - READ TO CACHE '.$this->cacheKey, 1);
        return $this->ps_cache->retrieve($this->cacheKey);
    }
    
    private function saveToCache(&$data)
    {
        $debugInfo = sprintf(
            '<!-- [%s from %s on %s] -->',
            $this->cacheKey,
            str_replace('Cache', '', (DRIVER === 'prestashop') ? _PS_CACHING_SYSTEM_ : DRIVER),
            date('Y-m-d H:i:s'));
        
        //Logger::addLog('999 - WERITE TO CACHE '.$this->cacheKey, 1);
        
        return $this->ps_cache->store($this->cacheKey, $debugInfo . chr(0x0D) . chr(0x0A) . $data, CACHE_TTL);
    }

    /**
     * Check if we should use cache
     * checks for: dev mode, profilling, front controller, maintenance mode?, customer, shopping cart, AJAX and POST requests
     * @return boolean
     */
    private function isActive()
    {
        //turn off if we are not in front office
        if($this->context->controller->controller_type !== 'front')
        {
        
            // i dont like unnecessary overrides, this is workaround for clearing cache
            if ($_GET['empty_smarty_cache'] == 1 || $_GET['empty_sf2_cache'] == 1)
                $this->_clearCache();
            return $this->stopCache();
        }
        
        // make sure processing occurs only once and whole code will not execute in hookActionRequestComplete again
        // we get all information at first time
        
        if ($this->_activeCache !== true || $this->_activeCache !== false){
        
          //turn off on debug mode and in profilling
          if (_PS_MODE_DEV_ || _PS_DEBUG_PROFILING_){
            return $this->stopCache();
          }
          
          //disable on ajax and non-GET requests
          $active = !(isset($this->context->controller->ajax) ? $this->ajax : false);
          $active = $active && $_SERVER['REQUEST_METHOD'] === 'GET';
          
          if (!$active){
            return $this->stopCache();
          }
          // if enabled, during maintenance mode there will be no cache
          if (CHECK_FOR_MAINTENANCE && !((bool)Configuration::get('PS_SHOP_ENABLE', true))){
            return $this->stopCache();
          }
          
          //check that customer is not logged in
          if (isset($this->context->customer)){
            $customer = $this->context->customer;
            if ($customer && $customer instanceof Customer && $customer->id > 0){
              return $this->stopCache();
            }
          }
          
          //for guest checkout, check that cart is empty
          if (isset($this->context->cookie->id_cart)){
            $cart = new Cart($this->context->cookie->id_cart);
            if ($cart && $cart instanceof Cart && $cart->nbProducts() > 0){
              return $this->stopCache();
            }
          }
          
          // we will be working with cache, so we get key and cache handler
          $this->initCache();
        }
        
        return $this->_activeCache;
    }
    
    private function initCache()
    {
        $this->cacheKey = $this->getCacheKey();
        $this->ps_cache = new PSCache();
        $this->_activeCache = true;
    }
    
    private function stopCache()
    {
        $this->_activeCache = false;
        return false;
    }
    
    /**
     * Map lang, shop, currency, device and url to create cache key
     * @return md5 string 
     */
    public function getCacheKey($url = null)
    {
        if ($url === null){
          $url = $_SERVER['REQUEST_URI'];
        }
        
        $device = (SEPARATE_MOBILE_AND_DESKTOP) ? 'device-'.$this->context->getDevice().'|' : '';
        $currency = (MULTICURRENCY) ? 'currency-'.$this->getCurrencyId().'|' : '';
        
        $url = $device.
                'lang-'.$this->context->language->id.
                '|shop-'.$this->context->shop->id.
                '|theme-'.$this->context->shop->theme_name.
                '|puri-'.$this->context->shop->physical_uri.
                '|vuri-'.$this->context->shop->virtual_uri.
                '|domain-'.$this->context->shop->domain.'|'.
                $currency.
                'url-'.$url;
        
        return md5($url);
    }
    
    /**
     * Hack to get protected variable
     * Are we in maintenance?
     */
    private function isMaintenance()
    {
        $reflection = new ReflectionClass($this->context->controller);
        $property = $reflection->getProperty('maintenance');
        $property->setAccessible(true);
        return (bool)$property->getValue($this->context->controller);
    }
    
    /**
     * Look if currency is set in cookies
     * if not, return default currency ID
     * @return integer
     */
    private function getCurrencyId()
    {
        // get currency from cookies
        if (isset($this->context->cookie->id_currency)){
            $currency = $this->context->cookie->id_currency;
        }else{
          // get PrestaShop default currency
          $defaultCurrency = Currency::getDefaultCurrency(); // query for default
          $currency = ($defCurrency === false) ? 1 : $defaultCurrency; // fallback, set currency ID to 1 if not found
        }
        return (int) $currency;
    }
    
    // clear whole cache
    public function _clearCache($template = null, $cache_id = null, $compile_id = null, $deleteAll = false)
    {
        if (!isset($this->ps_cache)){
          $this->ps_cache = new PSCache();
        }
        $this->ps_cache->flush();
    }
}
