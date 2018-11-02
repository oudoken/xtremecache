<?php
/**
 * Cache Time-To-Live in seconds
 */
const CACHE_TTL = 172800;

/**
 * Cache driver
 */
const DRIVER = 'files'; // 'prestashop' or plain files

/**
 * Cache folder for file cache
 */
const CACHE_DIR = 'xcache';

/**
 * Default CHMOD for created files and folders
 * For security, please use 0666 for module and 0644 for cgi
 */
const DEFAULT_CHMOD = 0770;
	
/**
 * Cache mobile and desktop versions separatelly?
 */
const SEPARATE_MOBILE_AND_DESKTOP = false;

/**
 * Do you use more than one currency?
 * Set to false, to disable querying database.
 */
const MULTICURRENCY = false;

/**
 * If value is false, we will serve cached pages during maintenance and do not query DB.
 * If value is true, cache will be completly off during maintenance and we may query DB.
 */
const CHECK_FOR_MAINTENANCE = false;
