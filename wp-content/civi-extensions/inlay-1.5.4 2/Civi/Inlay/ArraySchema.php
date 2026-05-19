<?php
/**
 * 'Simple' array schema check.
 *
 * @author Rich Lott / Artful Robot
 * Version: 1.0
 *
 */
namespace Civi\Inlay;

/**
 * @class
 *
 * Schema is defined with an array. Each key is either a string or a regex used to match one or more keys in the input.
 *
 * e.g. new ArraySchema(['x' => ..., '/^[0-9]+$/' => ..., '//' => ...']) would allow for
 * a key called 'x', numeric keys, and latterly, *any* key since // matches any string.
 *
 * Each value (...) in above example is also an array.
 * The first positional item is 'MUST' or 'MAY'. If MUST, then this key (pattern) must match at least one item.
 * If 'MAY' then if an input key matches this pattern, its value must match the schema, but if there is no input key matching,
 * that's ok.
 * If you do not include '//' as a key match, then all keys not matching other matches are considered invalid.
 *
 * The second positional value is a string of permitted gettype() values separated by |
 * e.g. 'string|double|NULL' Note that gettype() returns 'boolean' for vars declared as bool. And 'double' for floats.
 *
 * The value MUST match one of these types. If it's NULL, no further checks are done.
 *
 * Beyond the positional (first and second) keys, are optional string keys:
 *
 * - oneOfStrictly and oneOf use in_array to check that the value matches.
 * - gt, lt, gte, lte use > < >= <= to compare the value
 * - regex provides a regex that must match.
 * - schema provides a schema when the value is an array.
 * - recurse provides a means to describe recursive schemas, such as this schema itself!
 *   Its value is an array of keys within the schema. An empty array means recurse from the whole schema.
 *
 * Recurse example. Consider an array containing a representation of a directory, like this:
 *
 * [
 *   ['name' => 'README.md'],
 *   ['name' => 'css', 'children' => [
 *     ['name' => 'web.css'],
 *     ['name' => 'print.css'],
 *   ]],
 * ]
 *
 * This could be validated with:
 *
 * [
 *   '/^\d+$/'    => ['MAY', 'array', 'schema' => [
 *     'name'     => ['MUST', 'string'],
 *     'children' => ['MAY', 'array', 'recurse' => []]
 *   ]]
 * ]
 *
 * If the input for the 'web.css' file used 'nom' instead of 'name', two errors would be generated:
 * - Missing required key at 1»children»0 "name"
 * - Unexpected keys at 1»children»0 ["nom"]
 *
 * ## Coersion
 *
 * If you use setCoerce() then it will make reasonable efforts to coerce scalar values to the desired types.
 * - most things work as you'd expect.
 * - if the type is integer, then '123' is cast, but '123.4' or '123.0' is not.
 * - it will try to cast to the given types in turn. So if the value can't be cast to the first type
 *   it will try the next type.
 * - if NULL is in the list of acceptable types, data will be set NULL, if it could not be cast to
 *   a previous type.
 *
 * ## Fallbacks
 *
 * If you use setFallbacks(defaultsArray) then when a value is wrong, it will try to fish out a valid value
 * from the fallback array.
 *
 *
 */
class ArraySchema {

  public $schema;

  /**
   * If set true, then attempt to coerce values before validating them.
   *
   * @param bool
   */
  protected $coerce = FALSE;

  /**
   * @param bool
   */
  protected $removeUnexpectedKeys = FALSE;

  /**
   * @param ?array
   */
  protected $fallbacks = NULL;

  public static function getOwnSchema(): array {
    return [
      '//' => [ 'MUST', 'array',
        'schema' => [
          0 => ['MUST', 'string', 'oneOfStrictly' => ['MUST', 'MAY']],
          1 => ['MUST', 'string|NULL', 'regex' => '/^(boolean|integer|double|string|array|NULL|numeric|empty)([|](boolean|integer|double|string|array|NULL|numeric|empty))*$/'],
          'schema' => ['MAY', 'array', 'recurse' => []],
          'regex' => ['MAY', 'string'],
          'recurse' => ['MAY', 'array', 'schema' => [
            '//' => ['MAY', 'string']
          ]],
          'oneOfStrictly' => ['MAY', 'array', 'schema' => ['/^[0-9]+$/' => ['MUST', 'integer|string|boolean|double|NULL']]],
          'oneOf' => ['MAY', 'array', 'schema' => ['/^[0-9]+$/' => ['MUST', 'integer|string|numeric|boolean|double|NULL']]],
          'gt' => ['MAY', 'string|integer|double'],
          'lt' => ['MAY', 'string|integer|double'],
          'gte' => ['MAY', 'string|integer|double'],
          'lte' => ['MAY', 'string|integer|double'],
          ]
        ]
      ];
  }

  public function __construct(array $schema, bool $validateSchema = TRUE) {
    $this->schema = $schema;
    if ($validateSchema) {
      $a = new static(static::getOwnSchema(), FALSE);
      $errors = $a->getErrors($this->schema);
      if ($errors) {
        throw new \RuntimeException("Attempted to construct ArraySchema with invalid schema:\n" . $this->formatErrorsAsString($errors));
      }
    }
  }

  /**
   * Returns any validation errors as an array of tuples with 3 elements:
   *
   * - string error message
   * - array list of input keys describing the path to the invalid item.
   * - the value
   *
   * The input data is passed by reference and mutated according to setCoerce and setFallbacks.
   */
  public function getErrors(array &$data) {
    return $this->matches($data, [], $this->schema);
  }

  /**
   * @return static
   */
  public function setRemoveUnexpectedKeys(bool $remove = TRUE) {
    $this->removeUnexpectedKeys = $remove;
    return $this;
  }

  /**
   * @return static
   */
  public function setFallbacks(array $fallbacks = []) {
    $this->fallbacks = $fallbacks;
    return $this;
  }

  /**
   * @return static
   */
  public function setCoerce(bool $coerce = TRUE, ?bool $removeUnexpectedKeys = NULL, ?array $fallbacks = NULL) {
    $this->coerce = $coerce;
    if (is_bool($removeUnexpectedKeys)) {
      $this->setRemoveUnexpectedKeys($removeUnexpectedKeys);
    }
    if (is_array($fallbacks)) {
      $this->setFallbacks($fallbacks);
    }
    return $this;
  }

  /**
   * This is the primary looping function.
   */
  protected function matches(array &$data, array $ancestry, array $schema): array {
    $dataKeys = array_flip(array_keys($data));
    $allErrors = [];
    foreach ($schema as $keyMatch => $schema) {
      $keyWasFound = 0;
      if (substr($keyMatch, 0, 1) === '/') {
        // Regexp: one regexp key may match 0+ actual keys.
        foreach($data as $key => &$value) {
          if (preg_match($keyMatch, $key)) {
            $keyWasFound++;
            // Key is valid, what about value?
            $valueErrors = $this->valueMatch($keyMatch, $schema, $key, $value, [...$ancestry, $key]);
            if ($valueErrors) {
              // print "errors " . json_encode($valueErrors, JSON_PRETTY_PRINT);
              $allErrors = [...$allErrors, ...$valueErrors];
            }
            // As this key is now validated, remove it from $data
            unset($dataKeys[$key]);
          }
        }
        unset($value);
      }
      else {
        // $keyMatch is a simple string. If we didn't find it, use fallback if poss.
        if (!array_key_exists($keyMatch, $data) && $schema[0] === 'MUST') {
          if (is_array($this->fallbacks)) {
            // Can we substitute this in?
            $newValue = NULL;
            if ($this->applyFallback($newValue, [...$ancestry, $keyMatch])) {
              $data[$keyMatch] = $newValue;
            }
          }
        }
        if (array_key_exists($keyMatch, $data)) {
          $keyWasFound++;
          $valueErrors = $this->valueMatch($keyMatch, $schema, $keyMatch, $data[$keyMatch], [...$ancestry, $keyMatch]);
          if ($valueErrors) {
            // print "errors " . json_encode($valueErrors, JSON_PRETTY_PRINT);
            $allErrors = [...$allErrors, ...$valueErrors];
          }
          // As this key is now validated, remove it from $data
          unset($dataKeys[$keyMatch]);
        }
      }
      if ($schema[0] === 'MUST' && $keyWasFound === 0) {
        $allErrors[] = ["Missing required key", $ancestry, $keyMatch];
      }
    }
    if ($dataKeys) {
      if ($this->removeUnexpectedKeys) {
        foreach (array_keys($dataKeys) as $key) {
          unset($data[$key]);
        }
      }
      else {
        $allErrors[] = ["Unexpected keys", $ancestry, array_keys($dataKeys)];
      }
    }
    return $allErrors;
  }

  /**
   * @return array
   *    Each entry is an error, which itself is an array [string message, array ancestry, value]
   *    An empty return value means no errors.
   */
  protected function matchesExpectedType(array $expectedTypes, &$value, array $schema, array $ancestry): array {
    $actualType = gettype($value);
    $typeIsOK = FALSE;
    foreach ($expectedTypes as $type) {
      if ($actualType === 'NULL' && $type === 'NULL') {
        return []; // special case, we don't do any other assertions if we have NULL and we're allowed NULL
      }
      if ($actualType === $type
          && in_array($actualType, ['boolean', 'integer', 'double', 'string', 'array'])
          ) {
        $typeIsOK = TRUE;
        break;
      }
      if ($type === 'numeric' && is_numeric($value)) {
        $typeIsOK = TRUE;
        break;
      }
      if ($type === 'empty' && empty($value)) {
        $typeIsOK = TRUE;
        break;
      }
    }
    if (!$typeIsOK) {
      return [["Expected " . implode("|", $expectedTypes) . " but got $actualType", $ancestry, $value]];
    }
    // By this point we know we have a valid, non-null type.
    if ($actualType === 'array') {
      // Arrays may have further schema to pass...
      if (is_array($schema['schema'] ?? NULL)) {
        // Recursive.
        return $this->matches($value, $ancestry, $schema['schema']);
      }
      if (is_array($schema['recurse'] ?? NULL)) {
        // Recursive schema.
        $arraySchema = $this->schema;
        foreach ($schema['recurse'] as $key) {
          $arraySchema = $arraySchema[$key] ?? NULL;
        }
        if (!is_array($arraySchema)) {
          throw new \RuntimeException("Invalid recurse expression in schema: " . json_encode($schema['recurse']));
        }
        return $this->matches($value, $ancestry, $arraySchema);
      }
      $schemaRequiresNotArray = array_keys(array_intersect_key($schema, array_flip(['oneOf', 'oneOfStrictly', 'regex', 'gt', 'gte', 'lt', 'lte'])));
      if (count($schemaRequiresNotArray)) {
        return [["Got array which can't be compared to schema " . implode(', ', $schemaRequiresNotArray), $ancestry, $value]];
      }
    }

    $enum = $schema['oneOf'] ?? $schema['oneOfStrictly'] ?? NULL;
    $strict = is_array($schema['oneOfStrictly'] ?? NULL);
    if (is_array($enum) && !in_array($value, $enum, $strict)) {
      return [
        [
          "Got something that's not " . ($strict ? 'oneOfStrictly' : 'oneOf')
          . ' ' . json_encode($schema[$strict ? 'oneOfStrictly' : 'oneOf']),
          $ancestry, $value
        ]
      ];
    }

    if (!empty($schema['regex']) && !preg_match($schema['regex'], $value)) {
      return [["Expected regex match $schema[regex]", $ancestry, $value]];
    }

    if (!empty($schema['gt']) && !($value > $schema['gt'])) {
      return [["Expected gt $schema[gt]", $ancestry, $value]];
    }
    if (!empty($schema['gte']) && !($value >= $schema['gte'])) {
      return [["Expected gte $schema[gte]", $ancestry, $value]];
    }
    if (!empty($schema['lt']) && !($value < $schema['lt'])) {
      return [["Expected lt $schema[lt]", $ancestry, $value]];
    }
    if (!empty($schema['lte']) && !($value <= $schema['lte'])) {
      return [["Expected lte $schema[lte]", $ancestry, $value]];
    }
    return [];
  }

  protected function valueMatch(string $keyMatch, array $schema, string $key, &$value, array $ancestry) {
    $expectedTypes = $schema[1];
    if ($expectedTypes === NULL) {
      // e.g. schema says a key must/may exist, but doesn't specify anything about the contents.
      return [];
    }
    $expectedTypesArray = explode('|', $expectedTypes);
    $errors = $this->matchesExpectedType($expectedTypesArray, $value, $schema, $ancestry);
    if ($errors && $this->coerce && $this->coerceValue($expectedTypesArray, $keyMatch, $schema, $key, $value, $ancestry)) {
      // We fixed the type, but check again for other conditions (gt, lte, oneOf etc.)
      $errors = $this->matchesExpectedType($expectedTypesArray, $value, $schema, $ancestry);
    }
    if ($errors && is_array($this->fallbacks)) {
      if ($this->applyFallback($value, $ancestry)) {
        // Check that the fallback fixed it!
        $errors = $this->matchesExpectedType($expectedTypesArray, $value, $schema, $ancestry);
      }
    }
    return $errors;
  }

  /**
   * @return bool
   *    TRUE means we coerced the value
   */
  protected function coerceValue(array $expectedTypesArray, string $keyMatch, array $schema, string $key, &$value, array $ancestry): bool {
    $actualType = gettype($value);
    if (in_array($actualType, ['array', 'object'])) {
      // These types cannot be coerced into other types.
      return FALSE;
    }
    // Find the first type we can cast to.
    foreach ($expectedTypesArray as $acceptableType) {
      if ($acceptableType === 'boolean') {
        if (in_array($actualType, ['integer', 'string', 'double', 'NULL'])) {
          $value = (bool) $value;
          return TRUE;
        }
      }
      elseif ($acceptableType === 'integer') {
        if (in_array($actualType, ['bool', 'double', 'NULL'])
          || ($actualType === 'string' && strval(intval($value)) === $value)
        ) {
          $value = (int) $value;
          return TRUE;
        }
      }
      elseif ($acceptableType === 'double') {
        if (in_array($actualType, ['bool', 'integer', 'NULL'])
          || ($actualType === 'string' && is_numeric($value))
        ) {
          $value = (float) $value;
          return TRUE;
        }
      }
      elseif ($acceptableType === 'string') {
        $value = (string) $value;
        return TRUE;
      }
      elseif ($acceptableType === 'NULL') {
        $value = NULL;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @return bool
   *    TRUE means we found a fallback for the value
   */
  protected function applyFallback(&$value, array $ancestry): bool {
    // See if we can coerce by fallbacks
    $fb = $this->fallbacks;

    foreach (array_slice($ancestry, 0, -1) as $k) {
      if (is_array($fb[$k] ?? NULL)) {
        $fb = $fb[$k];
      }
      else {
        return FALSE;
      }
    }
    $k = end($ancestry);
    if (array_key_exists($k, $fb)) {
      $value = $fb[$k];
      return TRUE;
    }
    return FALSE;
  }

  public function formatErrorsAsString(array $errors): string {
    $messages = [];
    foreach ($errors as $error) {
      $message = array_shift($error);
      $ancestry = array_shift($error);
      $value = $error ? ' ' . json_encode(($error[0]), JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES) : '';
      $ancestry = $ancestry ? implode('»', $ancestry) : '(root)';
      $messages[] = "{$message} at {$ancestry}{$value}";
    }
    return implode("\n", $messages);
  }
}
