<?php 

/**
* RentPress Admin Options Manager
*/
$rentPress_gotten_options=[];

global $rentPress_gotten_options;

class rentPress_Options
{
  /**
   * RentPress Options Constants
   * These constants are used for RentPress core system indicators for checking whether the plugin is installed, what version it is, and some other helpful 
   * string literals
   */
  const OPTION_INSTALLED = '_installed';
  const OPTION_VERSION = '_version';
  const MANAGE_OPTIONS = 'manage_options';
  const RP_FALSE = 'false';
  const RP_TRUE = 'true';

  /**
   * Option keys as string literals
   * @var string
   */
  public $disablePricing_key = 'disable_pricing';
  public $disablePricingUrl_key = 'disable_pricing_url';
  public $disablePricingMessage_key = 'disable_pricing_message';

  /**
   * Default option values
   * @var [type]
   */
  public $defaultOptionValues = [
    'use_avail_units_before_this_date' => 45,
    'unit_rent_type' => 'best_price',
    'can_refresh' => self::RP_FALSE,
    'disable_pricing_message' => 'Call for pricing',
    'disable_pricing' => self::RP_FALSE,
    'disable_pricing_url' => '/contact/',
    'phone_number_format' => 'noPhoneFormat',
    'templates_accent_color' => '#0099CC',
    'google_js_default_map_center_latitude' => 39.809734,
    'google_js_default_map_center_longitude' => -98.555620,
    'override_how_floorplan_pricing_is_display' => 'starting-at',
    'override_apply_links_targets' => '_self',
    'archive_floorplans_default_sort' => 'avail:asc',
    'archive_property_default_sort' => 'avail:asc',
    'single_floorplan_content_position' => 'single_floorplan_content_bottom',
    'archive_properties_content_position' => 'archive_properties_content_bottom',
    'archive_floorplan_content_position' => 'archive_floorplan_content_bottom',
    'override_unit_visibility' => 'unit_visibility_1',
    'show_waitlist_ctas' => 'false',
    'hide_floorplan_availability_counter' => false,
    'hide_floorplans_without_availability' => false,
    'property_trackingphone' => '',
    'show_waitlist_override_url' => '/waitlist',
    'single_floorplan_schedule_a_tour_url' => '/tour/',
    'single_floorplan_request_more_info_url' => '/contact/',
    'property_grid_featured_image_text' => '',
    'properties_default_featured_image' => RENTPRESS_PLUGIN_ASSETS."images/placeholder/property-placeholder.png",
    'floorplans_default_featured_image' => RENTPRESS_PLUGIN_ASSETS."images/placeholder/floorplan-placeholder.png",
    'cities_default_featured_image'     => RENTPRESS_PLUGIN_ASSETS."images/placeholder/city-placeholder.png",
    'choose_archive_properties_template_file' => 'rentPress_choose_archive_basic',
  ];

  public function __construct()
  {
    $this->notifications = new rentPress_Notifications_Notification();
  }

  public function saveOrUpdate()
  {
    foreach ($_REQUEST as $optionName => $optionValue) {

      if ( $optionName == 'action' ) { // Make sure we don't try to add the action as a meta value
        continue;
      }

      if ( in_array($optionName, ['api_username', 'api_token']) ) { // sanitize the text fields
        $optionValue = sanitize_text_field($optionValue);
      }

      $this->updateOption($optionName, $optionValue);
    }
    echo $this->notifications->successResponse('Successfully updated RentPress settings!');
    die();
  }

  /**
  * Query MySQL DB for its version
  * @return string|false
  */
  public function getMySqlVersion() 
  {
    global $wpdb;
    $rows = $wpdb->get_results('select version() as mysqlversion');
    if ( !empty($rows) ) {
      return $rows[0]->mysqlversion;
    }
    return false;
  }

  /**
  * Cleanup: remove all options from the DB
  * @return void
  */
  protected function deleteSavedOptions() 
  {
    $optionMetaData = $this->getOptionMetaData();
    if (is_array($optionMetaData)) {
      foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
        $prefixedOptionName = $this->prefix($aOptionKey); // how it is stored in DB
        delete_option($prefixedOptionName);
      }
    }
  }

  /**
  * @return string display name of the plugin to show as a name/title in HTML.
  * Just returns the class name. Override this method to return something more readable
  */
  public function getPluginDisplayName() 
  {
    return 'rentPress';
  }

  /**
  * Get the prefixed version input $name suitable for storing in WP options
  * Idempotent: if $optionName is already prefixed, it is not prefixed again, it is returned without change
  * @param  $name string option name to prefix. Defined in settings.php and set as keys of $this->optionMetaData
  * @return string
  */
  public function prefix($name) {
    $optionNamePrefix = $this->getOptionNamePrefix();
      if (strpos($name, $optionNamePrefix) === 0) { // 0 but not false
        return $name; // already prefixed
      }
    return $optionNamePrefix . $name;
  }

  /**
  * Remove the prefix from the input $name.
  * Idempotent: If no prefix found, just returns what was input.
  * @param  $name string
  * @return string $optionName without the prefix.
  */
  public function &unPrefix($name) 
  {
    $optionNamePrefix = $this->getOptionNamePrefix();
    if (strpos($name, $optionNamePrefix) === 0) {
      return substr($name, strlen($optionNamePrefix));
    }
    return $name;
  }

  /**
  * A wrapper function delegating to WP get_option() but it prefixes the input $optionName
  * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
  * @param $optionName string defined in settings.php and set as keys of $this->optionMetaData
  * @param $default string default value to return if the option is not set
  * @return string the value from delegated call to get_option(), or optional default value
  * if option is not set.
  */
  public function getOption($optionName, $default = null) 
  {

    global $rentPress_gotten_options;

    $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
    
    if (isset($rentPress_gotten_options[ $prefixedOptionName ])) {
      $retVal = $rentPress_gotten_options[$prefixedOptionName];
    }
    else {
      $retVal = get_option($prefixedOptionName);
      $rentPress_gotten_options[$prefixedOptionName] = $retVal;
    }

    if ( !$retVal && $default ) {
      $retVal = $default;
    }

    return $retVal;
  }

  /**
  * A wrapper function delegating to WP delete_option() but it prefixes the input $optionName
  * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
  * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
  * @return bool from delegated call to delete_option()
  */
  public function deleteOption($optionName) 
  {
    $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
    return delete_option($prefixedOptionName);
  }

  /**
  * A wrapper function delegating to WP add_option() but it prefixes the input $optionName
  * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
  * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
  * @param  $value mixed the new value
  * @return null from delegated call to delete_option()
  */
  public function addOption($optionName, $value) 
  {
    global $rentPress_gotten_options;

    $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB
    
    $rentPress_gotten_options[$prefixedOptionName] = $value;

    return add_option($prefixedOptionName, $value);
  }

  /**
  * A wrapper function delegating to WP add_option() but it prefixes the input $optionName
  * to enforce "scoping" the options in the WP options table thereby avoiding name conflicts
  * @param  $optionName string defined in settings.php and set as keys of $this->optionMetaData
  * @param  $value mixed the new value
  * @return null from delegated call to delete_option()
  */
  public function updateOption($optionName, $value) 
  {
    global $rentPress_gotten_options;

    $prefixedOptionName = $this->prefix($optionName); // how it is stored in DB

    $rentPress_gotten_options[$prefixedOptionName] = $value;
    
    return update_option($prefixedOptionName, $value);
  }

  /**
  * A Role Option is an option defined in getOptionMetaData() as a choice of WP standard roles, e.g.
  * 'CanDoOperationX' => array('Can do Operation X', 'Administrator', 'Editor', 'Author', 'Contributor', 'Subscriber')
  * The idea is use an option to indicate what role level a user must minimally have in order to do some operation.
  * So if a Role Option 'CanDoOperationX' is set to 'Editor' then users which role 'Editor' or above should be
  * able to do Operation X.
  * Also see: canUserDoRoleOption()
  * @param  $optionName
  * @return string role name
  */
  public function getRoleOption($optionName) 
  {
    $roleAllowed = $this->getOption($optionName);
    if ( !$roleAllowed || $roleAllowed == '' ) {
      $roleAllowed = 'Administrator';
    }
    return $roleAllowed;
  }

  /**
  * Given a WP role name, return a WP capability which only that role and roles above it have
  * http://codex.wordpress.org/Roles_and_Capabilities
  * @param  $roleName
  * @return string a WP capability or '' if unknown input role
  */
  protected function roleToCapability($roleName) 
  {
    $realRoleName = '';
    switch ($roleName) {
      case 'Super Admin': // Will use the same as the Administrator option
      case 'Administrator':
        $realRoleName = self::MANAGE_OPTIONS;
      case 'Editor':
        $realRoleName = 'publish_pages';
      case 'Author':
        $realRoleName = 'publish_posts';
      case 'Contributor':
        $realRoleName = 'edit_posts';
      case 'Subscriber': // Will use the same as the Subscriber option
      case 'Anyone':
        $realRoleName = 'read';
      default:
        $realRoleName = '';
    }
    return $realRoleName;
  }

  /**
  * @param $roleName string a standard WP role name like 'Administrator'
  * @return bool
  */
  public function isUserRoleEqualOrBetterThan($roleName) 
  {
    if ('Anyone' == $roleName) {
      return true;  
    }
    $capability = $this->roleToCapability($roleName);
    return current_user_can($capability);
  }

  /**
  * @param  $optionName string name of a Role option (see comments in getRoleOption())
  * @return bool indicates if the user has adequate permissions
  */
  public function canUserDoRoleOption($optionName) 
  {
    $roleAllowed = $this->getRoleOption($optionName);
    if ('Anyone' == $roleAllowed) {
      return true;
    }
    return $this->isUserRoleEqualOrBetterThan($roleAllowed);
  }

  public function getOptionNamePrefix() 
  {
    return 'rentPress_';
  }

  /**
  * @return bool indicating if the plugin is installed already
  */
  public function isInstalled() 
  {
    return $this->getOption(self::OPTION_INSTALLED);
  }

  /**
  * Note in DB that the plugin is installed
  * @return null
  */
  public function markAsInstalled() 
  {
    return $this->updateOption(self::OPTION_INSTALLED, true);
  }

  /**
  * Note in DB that the plugin is uninstalled
  * @return bool returned form delete_option.
  * true implies the plugin was installed at the time of this call,
  * false implies it was not.
  */
  public function markAsUnInstalled() 
  {
    return $this->deleteOption(self::OPTION_INSTALLED);
  }

  /**
   * __get() is triggered when trying to access a property of the class that may or may not exist
   * @param  [string] $name [Property key]
   * @return [mixed]        [Value of desired property]
   */
  public function __get($name) {
    if ( property_exists($this, $name) ) {
      return $this->$name;
    }
  }    

}