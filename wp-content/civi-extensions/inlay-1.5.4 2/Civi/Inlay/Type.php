<?php

namespace Civi\Inlay;

use InvalidArgumentException;
use Civi\Inlay\Config as InlayConfig;
use Civi\Api4\InlayConfigSet;

/**
 * The base class for any type of Inlay.
 *
 */
abstract class Type {
  const INVALID_SUBCLASS = 1;

  /**
   * Typically the version from the info.xml file, but only needs updating when your config changes.
   *
   * @const string
   */
  const CONFIG_VERSION = '';

  /**
   * Human name for the inlay type. e.g. "Signup form"
   *
   * @var string
   */
  public static $typeName;

  /**
   * Human name for the inlay type ex: signup_form. Optional (required for config sets) added in Inlay 1.3.
   *
   * @var string
   */
  public static $machineName;

  /**
   * URL path to edit an inlay, where {id} will be replaced by the
   * ID of the inlay instance being edited. This will be 0 when adding a new
   * one.
   *
   * e.g. 'civicrm/a#/inlays/signupForm/{id}'
   *
   * If you omit this in your Inlay Type subclass: it will be 'civicrm/a#/inlays/<typeMachineName>/{id}'
   *
   * Accessing this directly is deprecated, please use the static getInstanceEditURLTemplate() method.
   *
   * @var string
   */
  public static $editURLTemplate;

  /**
   * @var array
   */
  public static $defaultConfig = [];

  /**
   * Instance configuration (i.e. the unpacked version of the JSON config field)
   *
   * @var array
   */
  public $config;

  /**
   * All field data except the config JSON blob
   *
   * @var array
   */
  public $instanceData;

  /**
   * @param mixed $class
   */
  public static function fromClass($class): Type {
    if (!(is_subclass_of($class, Type::class))) {
      throw new \RuntimeException("Given class '$class' is not an instance of Civi\Inlay\Type");
    }
    $obj = new $class();
    return $obj;
  }

  /**
   * Instantiate the correct class from the data in $array.
   *
   * Typically used to instantiate from an Api Get action's data.
   *
   * @param array<int,mixed> $array
   */
  public static function fromArray(array $array): Type {
    $class = $array['class'] ?? '';
    if (!(is_subclass_of($class, Type::class))) {
      throw new \RuntimeException("Given class '$class' is not an instance of Civi\Inlay\Type", self::INVALID_SUBCLASS);
    }
    $obj = new $class();
    // \Civi::log()->debug('loadFromArray: ' . json_encode($array, JSON_PRETTY_PRINT));
    return $obj->loadFromArray($array);
  }

  /**
   * Instantiate the correct class from the data in $array.
   *
   * Typically used to instantiate from an Api Get action's data.
   *
   */
  public static function fromPublicID(string $publicID): Type {

    $inlayData = \Civi\Api4\Inlay::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('public_id', '=', $publicID)
      ->execute()->first();
    if (!$inlayData) {
      throw new InvalidArgumentException("Invalid inlay public ID");
    }
    return static::fromArray($inlayData);
  }

  /**
   * Instantiate the correct class given its database ID.
   *
   * @throws Civi\Inlay\ApiException
   * @throws RuntimeException
   *
   * @param int $id
   * @return Civi\Inlay\Type
   */
  public static function fromId(int $id): Type {
    $inlayData = \Civi\Api4\Inlay::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('id', '=', $id)
      ->execute()->first();
    if (!$inlayData) {
      throw new ApiException(400, ['error' => 'Invalid Inlay ID']);
    }

    return static::fromArray($inlayData);
  }

  /**
   * Return information about config sets that an inlay type requires.
   *
   * [ <config_set_name> => [
   *   'label' => <human readable name>
   *   'description' => <human readable description>
   *   'required' => <bool> Whether at least one instance (set name 'default') is required. Defaults to FALSE
   *   'multiple' => <bool> Whether multiple are allowed. Defaults to TRUE.
   *   'defaults' => <array> This provides both the defaults and the keys that should be on this config set.
   *   ],
   *   ...
   * ]
   *
   * Note that this is a function, not, say a static array, because Inlay types
   * may conceivably create sets programmatically.
   *
   * The key (i.e. machine name of the config set) must be unique, and typically should be prefixed with the inlay type name.
   *
   */
  public static function getConfigSets(): array {

    $configSets = [];
    // Example.
    //
    // $configSets['myinlay_shared_defaults'] = [
    //     'label' => 'Config shared between all instances of myinlay',
    //     'required' => TRUE,
    //     'multiple' => FALSE,
    //     'defaults' => [ 'submit_text' => 'Submit' ]
    //   ];
    //
    // $configSets['myinlay_styles'] = [
    //     'label' => 'Styling sets',
    //     'description' => 'Create sets of styles that you can reuse between your myinlay instances',
    //     'required' => FALSE,
    //     'multiple' => TRUE,
    //     'defaults' => [ 'css_rules' => '', 'css_class' => '' ]
    //   ];
    return static::applyConfigSetDefaults($configSets);
  }

  /**
   * This should be called to ensure the defaults are present in the config sets.
   *
   * @see getConfigSets()
   */
  public static function applyConfigSetDefaults($configSets) {
    $class = static::class;
    foreach ($configSets as $configSetName => &$configSet) {
      $configSet += [
        'configURLTemplate' => $class::getConfigEditURLTemplate($configSetName),
        'required'          => FALSE,
        'multiple'          => TRUE,
        'defaults'          => [],
        'label'             => $configSetName,
      ];
    }

    return $configSets;
  }

  /**
   * Get a machine name for this type.
   */
  public static function getTypeMachineName(): string {
    if (isset(static::$machineName)) {
      return static::$machineName;
    }
    // Backwards compatibility, create from typeName
    return trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower(static::$typeName)), '_');
  }

  /**
   * Get the URL template for editing instances of this inlay type.
   */
  public static function getInstanceEditURLTemplate(): string {
    if (isset(static::$editURLTemplate)) {
      // Backwards compatibility. In future, better not to define the
      // editURLTemplate at all, to keep things simple, and rely on the default
      // below. But you can set it or override this method.
      return static::$editURLTemplate;
    }
    // Construct the default URL template.
    // Note: because of the way CRM.url works, you MUST put a ? before the #
    return 'civicrm/a?#/inlays/' . static::getTypeMachineName() . '/{id}';
  }

  /**
   * Get the URL template for editing config sets belonging to this inlay type.
   *
   * You may override this, e.g. if you do not want to use CiviCRM's angular
   * pages to configure your config set.
   */
  public static function getConfigEditURLTemplate(string $configSetName): string {
    // Construct the default config edit URL template.
    // Note: because of the way CRM.url works, you MUST put a ? before the #
    return "civicrm/a?#/inlayConfig/$configSetName/{id}";
  }

  /**
   * Load the config from the given Array.
   *
   * @param Array $array
   *
   * @return \Civi\Inlay\Type (this)
   */
  public function loadFromArray(array $array): Type {
    // Copy most fields for instanceData
    $this->instanceData = array_intersect_key($array, array_flip(['id', 'name', 'public_id', 'class', 'status']));
    // Config is special.
    $this->setConfig($array['config']);
    return $this;
  }

  /**
   * Return a list of assets used by this Inlay.
   *
   * We do not need to include our bundle file; Inlay itself looks after that.
   */
  public function getAssets(): array {
    return [];
  }

  /**
   * Return the type name. This is not a machine name.
   */
  public function getTypeName() {
    return static::$typeName;
  }

  /**
   * Return the public ID
   */
  public function getPublicID() {
    return $this->instanceData['public_id'] ?? NULL;
  }

  /**
   * Return the internal id
   */
  public function getID() {
    return $this->instanceData['id'] ?? NULL;
  }

  /**
   * Return the internal name
   */
  public function getName() {
    return $this->instanceData['name'] ?? NULL;
  }

  /**
   * Return the config array.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Return the internal name
   */
  public function getStatus() {
    return $this->instanceData['status'] ?? 'off';
  }

  /**
   * Calculate the bundle URL for given bundle.
   */
  public function getBundleUrl(): string {
    return InlayConfig::singleton()->getBundleUrl($this->getPublicID());
  }

  /**
   * Used to fetch a particular config set, e.g. one referenced in our own
   * config, or some global defaults.
   *
   * It returns the configuration itself (not the row that contains the config).
   *
   * Defaults are merged in.
   *
   * @param string $schemaName
   *    e.g. myinlay_shared_defaults
   * @param string|int $setIdentifier
   *    if int, assumes it's an ID.
   * @param mixed $setIdentifier
   */
  public function fetchConfigSet(string $schemaName, $setIdentifier = 'default'): array {

    $getApi = InlayConfigSet::get(FALSE)->addWhere('schema_name', '=', $schemaName);
    if (is_int($setIdentifier)) {
      $getApi->addWhere('id', '=', $setIdentifier);
    }
    else {
      $getApi->addWhere('set_name', '=', $setIdentifier);
    }
    $config = json_decode(($getApi->execute()->first() ?? [])['config'] ?? '[]', TRUE) ?? [];

    // Apply and limit keys to the defaults.
    $defaults = $this->getConfigSets()[$schemaName]['defaults'] ?? [];
    $config = array_intersect_key($config, $defaults) + $defaults;

    return $config;
  }

  /**
   * Helper method for registering an Inlay Type.
   */
  public static function register($event) {
    $class = static::class;
    $event->inlays[$class] = [
      'class'             => $class,
      'name'              => $class::$typeName,
      'machineName'       => $class::getTypeMachineName(),
      'editURLTemplate'   => $class::getInstanceEditURLTemplate(),
      'defaultConfig'     => $class::$defaultConfig,
      'configSets'        => $class::getConfigSets(),
    ];
  }

  /**
   * Generates a time limited and optionally data-bound CSRF token.
   *
   * @param array
   * - data      array data of simple key=>value pairs to include in the hash.
   * - secret    String data to include in in the hash; defaults to site key.
   * - validFrom integer number of seconds into the future. Default 10s
   * - validTo   integer number of seconds into the future. Default 300s (5 mins)
   *
   * @return String
   */
  public function getCSRFToken($options) {
    // Merge defaults
    $options += [
      'data'      => [],
      'secret'    => CIVICRM_SITE_KEY,
      'validFrom' => 10,
      'validTo'   => 300,
    ];

    $now = time();
    $validFrom = dechex($now + $options['validFrom']);
    $validTo = dechex($now + $options['validTo']);

    $visibleData = "$validFrom.$validTo";

    $dataToHash = "$validFrom$validTo$options[secret]";
    if (!empty($options['data'])) {
      // sort the data to ensure consistency in the hash.
      ksort($options['data']);
      $dataToHash .= serialize($options['data']);
    }

    $hash = sha1($dataToHash);
    $token = "$visibleData.$hash";
    return $token;
  }

  /**
   * Check a token.
   *
   * @param String $token
   * @param array|null $data
   * @param mixed $secret
   *
   * @throws InvalidArgumentException if token invalid.
   */
  public function checkCSRFToken($token, $data = NULL, $secret = NULL) {
    if (!preg_match('/^([0-9a-f]+)\.([0-9a-f]+)\.([0-9a-f]+)$/', $token, $matches)) {
      throw new InvalidArgumentException("TK1 Token syntax incorrect.");
    }
    $validFrom = (int) hexdec($matches[1]);
    $now = time();
    if ($validFrom > $now) {
      throw new InvalidArgumentException("TK2 Token not valid yet.");
    }
    $validTo = (int) hexdec($matches[2]);
    if ($validTo < $now) {
      throw new InvalidArgumentException("TK3 Token has expired. $validTo $now $token");
    }

    // Now check hash itself.
    $dataToHash = "$matches[1]$matches[2]" . ($secret ?: CIVICRM_SITE_KEY);
    if ($data) {
      ksort($data);
      $dataToHash .= serialize($data);
    }
    $hash = sha1($dataToHash);
    if ($matches[3] !== $hash) {
      throw new InvalidArgumentException("TK4 Token invalid. Request had '$matches[3]' which is not expected '$hash' from $dataToHash");
    }

    // @todo if redis is available, use that to store used tokens
    // and check for re-use.

    // Token is valid.
  }

  /**
   * Sets the config from a stored array.
   *
   * There's some code here to assist with config changes over time and validity checks.
   *
   * @param array $config
   *
   * @return \Civi\Inlay\Type (this)
   */
  public function setConfig(array $config): Type {

    // Check if migration needed
    if (!empty(static::CONFIG_VERSION) && ($config['version'] ?? '') !== static::CONFIG_VERSION) {
      $config = $this->migrateConfig($config);
    }

    // Finally, see if we can coerce the config array to being valid.
    // If the config is not validated, the inlay's status is set to 'broken'
    // and you should inspect your logs for 'critical' errors.
    $this->validateConfig($config, TRUE, FALSE);
    $this->config = $config;

    return $this;
  }

  /**
   * Called with an array of config when an Inlay\Type class has a CONFIG_VERSION set
   * that differs from the 'version' in the $config array.
   *
   * **Override this** with suitable migrations. If significant you may wish to put this
   * code in other files.
   *
   * Note: this does not SAVE your migrated config; this will run each time old config is loaded.
   * Your CRM_YourInlay_Upgrader code should do an API call to save migrated config. This is a
   * precaution against automatically applying a migration that doesn't work in a persisted way.
   * However, migrated content *will* get persisted if you edit the inlay's config and hit Save
   * yourself, but it's assumed that you have then verified everything manually if you do that.
   *
   * @param array<int,mixed> $config
   */
  protected function migrateConfig(array $config): array {
    // ... your migrations here ...
    $config['version'] = static::CONFIG_VERSION;
    return $config;
  }

  /**
   * Check the config we have as best we can.
   *
   * Implement getConfigSchema() for a deep check and return errors.
   * Otherwise, we just merge in and limit to top level keys of $defaultConfig.
   *
   * @param array<int,mixed> $config
   * @param mixed $coerce
   * @param mixed $throw
   */
  public function validateConfig(array &$config, $coerce = TRUE, $throw = TRUE): array {
    $errors = [];
    $schema = $this->getConfigSchema();
    if (!empty($schema)) {
      $validator = new ArraySchema($schema);
      if ($coerce) {
        $validator->setCoerce()->setRemoveUnexpectedKeys()->setFallbacks(static::$defaultConfig);
      }
      $errors = $validator->getErrors($config);
      if (!empty($errors)) {
        $data = [
          'id' => $this->getID(),
          'type' => $this->getTypeName(),
          'errors' => $errors,
          'config' => $this->config,
        ];
        \Civi::log()->critical("Inlay {id} {type} has invalid config! This could mean it is broken, and could (possibly) affect other Inlays.", $data);
        $this->instanceData['status'] = 'broken';
        if ($throw) {
          throw new \RuntimeException("Invalid configuration in Inlay $data[id] of type $data[type]. See logs.");
        }
      }
    }
    else {
      // This simply ensures all the defaults exist, and that no
      // other top-level keys exist. It's the implementation used up-to inlay 1.3.5 so
      // seems sensible to keep it. Most configs are fairly simple key => scalar types.
      // You'll need to use a migration if you need to apply new defaults below the top level keys.
      $config = array_intersect_key(
        $config + static::$defaultConfig,
        static::$defaultConfig + ['version' => static::CONFIG_VERSION]
      );
    }
    if (empty($errors) && !in_array($this->getStatus(), ['on', 'off'])) {
      // If it was broken, and is not broken any more, leave it OFF,
      // for safety. We want an admin to turn it ON.
      $this->instanceData['status'] = 'off';
    }

    return $errors;
  }

  /**
   * Optionally you can **override this** with a schema definition for your config.
   *
   * @see \Civi\Inlay\ArraySchema
   */
  public function getConfigSchema(): array {
    return [];
  }

  /**
   * Generates data to be served with the Javascript application code bundle.
   *
   * @return array
   */
  abstract public function getInitData(): array;

  /**
   * Process a request
   *
   * @param \Civi\Inlay\ApiRequest $request with the following properties
   *
   * @return array
   */
  abstract public function processRequest(\Civi\Inlay\ApiRequest $request): array;

  /**
   * Get the Javascript app script.
   *
   * This will be bundled with getInitData() and some other helpers into a file
   * that will be sourced by the client website.
   *
   * @return string Content of a Javascript file.
   */
  abstract public function getExternalScript(): string;

}
